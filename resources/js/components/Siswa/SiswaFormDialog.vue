<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    FileText,
    GraduationCap,
    Home,
    Loader2,
    Save,
    User,
    Users,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAutosave } from '@/composables/useAutosave';
import type { RombelOption, SemesterInfo, SiswaItem } from '@/types/siswa';

const props = defineProps<{
    open: boolean;
    siswa?: SiswaItem | null;
    rombelList: RombelOption[];
    currentSemester?: SemesterInfo | null;
    isWaliKelas?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const currentStep = ref(1);
const totalSteps = 5;

const steps = [
    { number: 1, title: 'Identitas', icon: User },
    { number: 2, title: 'Orang Tua / Wali', icon: Users },
    { number: 3, title: 'Alamat & Kontak', icon: Home },
    { number: 4, title: 'Berkas', icon: FileText },
    { number: 5, title: 'Akademik', icon: GraduationCap },
];

const isEdit = computed(() => !!props.siswa);

interface FormWaliItem {
    id?: string;
    jenis_wali: 'ayah' | 'ibu' | 'wali';
    nama: string;
    hubungan: string;
    pekerjaan: string;
    no_hp: string;
    alamat: string;
}

interface SiswaFormData {
    nisn: string;
    nik: string;
    nama: string;
    jenis_kelamin: 'L' | 'P';
    tempat_lahir: string;
    tanggal_lahir: string;
    agama: string;
    alamat: string;
    no_hp: string;
    status: 'aktif' | 'lulus' | 'mutasi_keluar' | 'drop_out' | 'non_aktif';
    rombel_id: string;
    wali: FormWaliItem[];
}

const defaultFormData = (): SiswaFormData => ({
    nisn: '',
    nik: '',
    nama: '',
    jenis_kelamin: 'L',
    tempat_lahir: '',
    tanggal_lahir: '',
    agama: 'Islam',
    alamat: '',
    no_hp: '',
    status: 'aktif',
    rombel_id: '',
    wali: [
        { jenis_wali: 'ayah', nama: '', hubungan: 'Ayah Kandung', pekerjaan: '', no_hp: '', alamat: '' },
        { jenis_wali: 'ibu', nama: '', hubungan: 'Ibu Kandung', pekerjaan: '', no_hp: '', alamat: '' },
    ],
});

const form = useForm<SiswaFormData>(defaultFormData());

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const draftStorageKey = computed(() => `simpul_siswa_draft_${authUser.value?.id ?? 'guest'}`);

// Autosave integration (for create mode)
const { hasSavedDraft, restoreDraft, clearDraft } = useAutosave({
    key: draftStorageKey,
    formData: form,
    enabled: computed(() => !isEdit.value && props.open),
});

// NISN Check State
const nisnCheckStatus = ref<'idle' | 'checking' | 'available' | 'taken' | 'invalid'>('idle');
const nisnCheckMessage = ref('');
let nisnTimer: ReturnType<typeof setTimeout> | null = null;

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function verifyNisn(nisn: string) {
    if (!nisn || nisn.length !== 10) {
        nisnCheckStatus.value = 'idle';
        nisnCheckMessage.value = '';
        return;
    }

    nisnCheckStatus.value = 'checking';
    nisnCheckMessage.value = 'Memeriksa NISN...';

    try {
        const response = await fetch('/siswa/check-nisn', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                nisn,
                ignore_id: props.siswa?.id,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            if (data.available) {
                nisnCheckStatus.value = 'available';
                nisnCheckMessage.value = 'NISN tersedia dan belum digunakan.';
            } else {
                nisnCheckStatus.value = 'taken';
                nisnCheckMessage.value = data.message || 'NISN sudah terdaftar di sistem.';
            }
        } else {
            nisnCheckStatus.value = 'idle';
            nisnCheckMessage.value = '';
        }
    } catch {
        nisnCheckStatus.value = 'idle';
        nisnCheckMessage.value = '';
    }
}

watch(
    () => form.nisn,
    (newVal) => {
        if (nisnTimer) clearTimeout(nisnTimer);
        if (!newVal || newVal.length !== 10) {
            nisnCheckStatus.value = 'idle';
            nisnCheckMessage.value = '';
            return;
        }
        nisnTimer = setTimeout(() => {
            void verifyNisn(newVal);
        }, 400);
    }
);

// NIK vs Tanggal Lahir Cross-check
const nikBirthMismatch = computed(() => {
    if (!form.nik || form.nik.length !== 16 || !form.tanggal_lahir) {
        return null;
    }

    try {
        // NIK Format: [6 digit wilayah][2 digit tgl][2 digit bln][2 digit thn][4 digit serial]
        const rawDate = parseInt(form.nik.substring(6, 8), 10);
        const rawMonth = parseInt(form.nik.substring(8, 10), 10);
        const rawYear = parseInt(form.nik.substring(10, 12), 10);

        // Female add 40 to date
        const isFemale = form.jenis_kelamin === 'P';
        const expectedDate = isFemale ? rawDate - 40 : rawDate;

        const [tYear, tMonth, tDate] = form.tanggal_lahir.split('-').map(Number);
        const lastTwoYear = tYear % 100;

        if (expectedDate !== tDate || rawMonth !== tMonth || rawYear !== lastTwoYear) {
            return `Format tanggal lahir pada NIK terdeteksi ${String(expectedDate).padStart(2, '0')}/${String(rawMonth).padStart(2, '0')}/${String(rawYear).padStart(2, '0')}, berbeda dengan tanggal lahir ${String(tDate).padStart(2, '0')}/${String(tMonth).padStart(2, '0')}/${tYear}. Periksa kembali data untuk memastikan ketepatan.`;
        }
    } catch {
        return null;
    }

    return null;
});

// Sync data when editing
watch(
    () => props.siswa,
    (val) => {
        if (val) {
            form.nisn = val.nisn || '';
            form.nik = val.nik || '';
            form.nama = val.nama || '';
            form.jenis_kelamin = val.jenis_kelamin || 'L';
            form.tempat_lahir = val.tempat_lahir || '';
            form.tanggal_lahir = val.tanggal_lahir ? val.tanggal_lahir.substring(0, 10) : '';
            form.agama = val.agama || 'Islam';
            form.alamat = val.alamat || '';
            form.no_hp = val.no_hp || '';
            form.status = val.status || 'aktif';
            form.rombel_id = val.anggota_rombel_aktif?.rombel_id || '';

            if (val.wali && val.wali.length > 0) {
                form.wali = val.wali.map((w) => ({
                    id: w.id,
                    jenis_wali: w.jenis_wali,
                    nama: w.nama,
                    hubungan: w.hubungan ?? '',
                    pekerjaan: w.pekerjaan ?? '',
                    no_hp: w.no_hp ?? '',
                    alamat: w.alamat ?? '',
                }));
            } else {
                form.wali = [
                    { jenis_wali: 'ayah', nama: '', hubungan: 'Ayah Kandung', pekerjaan: '', no_hp: '', alamat: '' },
                    { jenis_wali: 'ibu', nama: '', hubungan: 'Ibu Kandung', pekerjaan: '', no_hp: '', alamat: '' },
                ];
            }
        } else {
            form.reset();
            form.clearErrors();
        }
        currentStep.value = 1;
        nisnCheckStatus.value = 'idle';
    },
    { immediate: true }
);

function addWali() {
    form.wali.push({
        jenis_wali: 'wali',
        nama: '',
        hubungan: 'Wali Murid',
        pekerjaan: '',
        no_hp: '',
        alamat: '',
    });
}

function removeWali(index: number) {
    form.wali.splice(index, 1);
}

function nextStep() {
    if (currentStep.value < totalSteps) {
        currentStep.value++;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

function submitForm(andNew = false) {
    if (nisnCheckStatus.value === 'taken') {
        currentStep.value = 1;
        return;
    }

    if (isEdit.value && props.siswa) {
        // Edit mode
        form.put(`/siswa/${props.siswa.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                emit('update:open', false);
            },
        });
    } else {
        // Create mode
        form.post('/siswa', {
            preserveScroll: true,
            onSuccess: () => {
                clearDraft();
                emit('success');
                if (andNew) {
                    form.reset();
                    currentStep.value = 1;
                    nisnCheckStatus.value = 'idle';
                } else {
                    emit('update:open', false);
                }
            },
        });
    }
}

// Keyboard shortcuts (Ctrl+Enter, Ctrl+Shift+Enter)
function handleKeydown(event: KeyboardEvent) {
    if (!props.open) return;

    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        if (event.shiftKey && !isEdit.value) {
            submitForm(true);
        } else {
            submitForm(false);
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (nisnTimer) clearTimeout(nisnTimer);
});
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => emit('update:open', val)">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <DialogTitle class="text-xl">
                            {{ isEdit ? (isWaliKelas ? 'Perbarui Kontak & Wali Siswa' : 'Edit Data Siswa') : 'Tambah Siswa Baru' }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ isWaliKelas ? 'Wali Kelas hanya berhak memperbarui alamat, nomor kontak, dan data orang tua/wali siswa.' : 'Lengkapi 5 langkah informasi identitas, wali, dan akademik siswa.' }}
                        </DialogDescription>
                    </div>
                </div>

                <!-- Autosave Draft Notification -->
                <div v-if="!isEdit && hasSavedDraft" class="flex items-center justify-between rounded-md bg-amber-500/10 border border-amber-500/20 px-3 py-2 text-xs text-amber-700 dark:text-amber-400 mt-2">
                    <span>Ditemukan draf formulir yang belum disimpan.</span>
                    <div class="flex items-center gap-2">
                        <Button type="button" size="sm" variant="ghost" class="h-6 px-2 text-xs" @click="restoreDraft">
                            Pulihkan Draf
                        </Button>
                        <Button type="button" size="sm" variant="ghost" class="h-6 px-2 text-xs text-destructive" @click="clearDraft">
                            Hapus
                        </Button>
                    </div>
                </div>
            </DialogHeader>

            <!-- Stepper Progress Bar -->
            <div class="grid grid-cols-5 gap-2 border-y py-3 my-2">
                <button
                    v-for="step in steps"
                    :key="step.number"
                    type="button"
                    class="flex flex-col items-center gap-1 p-1 rounded-md text-xs transition-colors"
                    :class="[
                        currentStep === step.number
                            ? 'font-semibold text-primary'
                            : currentStep > step.number
                              ? 'text-muted-foreground hover:text-foreground'
                              : 'text-muted-foreground/60',
                    ]"
                    @click="currentStep = step.number"
                >
                    <div
                        class="flex h-7 w-7 items-center justify-center rounded-full border text-xs"
                        :class="[
                            currentStep === step.number
                                ? 'border-primary bg-primary text-primary-foreground'
                                : currentStep > step.number
                                  ? 'border-primary/50 bg-primary/10 text-primary'
                                  : 'border-muted-foreground/30',
                        ]"
                    >
                        <component :is="step.icon" class="h-3.5 w-3.5" />
                    </div>
                    <span class="truncate max-w-[80px]">{{ step.title }}</span>
                </button>
            </div>

            <!-- Form Content -->
            <form @submit.prevent="submitForm(false)" class="space-y-4">
                <!-- STEP 1: IDENTITAS -->
                <div v-show="currentStep === 1" class="space-y-4">
                    <div v-if="isWaliKelas" class="rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                        Identitas siswa (NISN, NIK, Nama, Tanggal Lahir, Status) dikunci. Hanya Operator atau Super Admin yang dapat mengubah data identitas pokok.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- NISN -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="siswa-nisn">NISN (10 Digit) <span class="text-destructive">*</span></Label>
                                <span v-if="nisnCheckStatus === 'checking'" class="text-xs text-muted-foreground flex items-center gap-1">
                                    <Loader2 class="h-3 w-3 animate-spin" /> Memeriksa...
                                </span>
                                <span v-else-if="nisnCheckStatus === 'available'" class="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <CheckCircle2 class="h-3 w-3" /> Tersedia
                                </span>
                                <span v-else-if="nisnCheckStatus === 'taken'" class="text-xs text-destructive flex items-center gap-1">
                                    <AlertCircle class="h-3 w-3" /> Terdaftar
                                </span>
                            </div>
                            <Input
                                id="siswa-nisn"
                                v-model="form.nisn"
                                maxlength="10"
                                placeholder="10 digit nomor NISN"
                                :disabled="isWaliKelas || form.processing"
                            />
                            <p v-if="form.errors.nisn" class="text-xs text-destructive">{{ form.errors.nisn }}</p>
                            <p v-else-if="nisnCheckMessage && nisnCheckStatus === 'taken'" class="text-xs text-destructive">{{ nisnCheckMessage }}</p>
                        </div>

                        <!-- NIK -->
                        <div class="space-y-2">
                            <Label for="siswa-nik">NIK (16 Digit) <span class="text-destructive">*</span></Label>
                            <Input
                                id="siswa-nik"
                                v-model="form.nik"
                                maxlength="16"
                                placeholder="16 digit NIK kependudukan"
                                :disabled="isWaliKelas || form.processing"
                            />
                            <p v-if="form.errors.nik" class="text-xs text-destructive">{{ form.errors.nik }}</p>
                        </div>
                    </div>

                    <!-- NIK Warning Banner -->
                    <Alert v-if="nikBirthMismatch" variant="destructive" class="border-amber-500/50 bg-amber-500/10 text-amber-800 dark:text-amber-300">
                        <AlertCircle class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        <AlertTitle class="text-xs font-semibold">Peringatan Kesesuaian NIK</AlertTitle>
                        <AlertDescription class="text-xs">{{ nikBirthMismatch }}</AlertDescription>
                    </Alert>

                    <!-- Nama Lengkap -->
                    <div class="space-y-2">
                        <Label for="siswa-nama">Nama Lengkap <span class="text-destructive">*</span></Label>
                        <Input
                            id="siswa-nama"
                            v-model="form.nama"
                            placeholder="Nama lengkap sesuai akta / ijazah"
                            :disabled="isWaliKelas || form.processing"
                        />
                        <p v-if="form.errors.nama" class="text-xs text-destructive">{{ form.errors.nama }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Jenis Kelamin -->
                        <div class="space-y-2">
                            <Label for="siswa-gender">Jenis Kelamin <span class="text-destructive">*</span></Label>
                            <Select
                                :model-value="form.jenis_kelamin"
                                @update:model-value="(val: any) => (form.jenis_kelamin = val)"
                                :disabled="isWaliKelas || form.processing"
                            >
                                <SelectTrigger id="siswa-gender">
                                    <SelectValue placeholder="Pilih jenis kelamin" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="L">Laki-laki (L)</SelectItem>
                                    <SelectItem value="P">Perempuan (P)</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.jenis_kelamin" class="text-xs text-destructive">{{ form.errors.jenis_kelamin }}</p>
                        </div>

                        <!-- Tempat Lahir -->
                        <div class="space-y-2">
                            <Label for="siswa-tempat-lahir">Tempat Lahir</Label>
                            <Input
                                id="siswa-tempat-lahir"
                                v-model="form.tempat_lahir"
                                placeholder="Kota/Kabupaten kelahiran"
                                :disabled="isWaliKelas || form.processing"
                            />
                            <p v-if="form.errors.tempat_lahir" class="text-xs text-destructive">{{ form.errors.tempat_lahir }}</p>
                        </div>

                        <!-- Tanggal Lahir -->
                        <div class="space-y-2">
                            <Label for="siswa-tgl-lahir">Tanggal Lahir</Label>
                            <Input
                                id="siswa-tgl-lahir"
                                type="date"
                                v-model="form.tanggal_lahir"
                                :disabled="isWaliKelas || form.processing"
                            />
                            <p v-if="form.errors.tanggal_lahir" class="text-xs text-destructive">{{ form.errors.tanggal_lahir }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Agama -->
                        <div class="space-y-2">
                            <Label for="siswa-agama">Agama</Label>
                            <Select
                                :model-value="form.agama"
                                @update:model-value="(val: any) => (form.agama = val)"
                                :disabled="isWaliKelas || form.processing"
                            >
                                <SelectTrigger id="siswa-agama">
                                    <SelectValue placeholder="Pilih agama" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Islam">Islam</SelectItem>
                                    <SelectItem value="Kristen">Kristen</SelectItem>
                                    <SelectItem value="Katolik">Katolik</SelectItem>
                                    <SelectItem value="Hindu">Hindu</SelectItem>
                                    <SelectItem value="Buddha">Buddha</SelectItem>
                                    <SelectItem value="Konghucu">Konghucu</SelectItem>
                                    <SelectItem value="Lainnya">Lainnya</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.agama" class="text-xs text-destructive">{{ form.errors.agama }}</p>
                        </div>

                        <!-- Status Siswa -->
                        <div class="space-y-2">
                            <Label for="siswa-status">Status Siswa</Label>
                            <Select
                                :model-value="form.status"
                                @update:model-value="(val: any) => (form.status = val)"
                                :disabled="isWaliKelas || form.processing"
                            >
                                <SelectTrigger id="siswa-status">
                                    <SelectValue placeholder="Pilih status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="aktif">Aktif</SelectItem>
                                    <SelectItem value="mutasi_keluar">Mutasi Keluar</SelectItem>
                                    <SelectItem value="drop_out">Drop Out</SelectItem>
                                    <SelectItem value="lulus">Lulus</SelectItem>
                                    <SelectItem value="non_aktif">Non-Aktif</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: ORANG TUA / WALI -->
                <div v-show="currentStep === 2" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted-foreground">
                            Data orang tua kandung atau wali sah siswa.
                        </p>
                        <Button type="button" variant="outline" size="sm" @click="addWali">
                            + Tambah Data Wali
                        </Button>
                    </div>

                    <div
                        v-for="(w, idx) in form.wali"
                        :key="idx"
                        class="rounded-lg border p-4 space-y-3 bg-card"
                    >
                        <div class="flex items-center justify-between border-b pb-2">
                            <span class="text-sm font-semibold capitalize flex items-center gap-2">
                                <Badge variant="secondary">{{ w.jenis_wali }}</Badge>
                                {{ w.nama || `Entri #${idx + 1}` }}
                            </span>
                            <Button
                                v-if="form.wali.length > 1"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive h-7 px-2 text-xs"
                                @click="removeWali(idx)"
                            >
                                Hapus
                            </Button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <Label :for="`wali-jenis-${idx}`" class="text-xs">Jenis Entri</Label>
                                <Select
                                    :model-value="w.jenis_wali"
                                    @update:model-value="(val: any) => (w.jenis_wali = val)"
                                >
                                    <SelectTrigger :id="`wali-jenis-${idx}`">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ayah">Ayah</SelectItem>
                                        <SelectItem value="ibu">Ibu</SelectItem>
                                        <SelectItem value="wali">Wali Murid</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-1 md:col-span-2">
                                <Label :for="`wali-nama-${idx}`" class="text-xs">Nama Lengkap <span class="text-destructive">*</span></Label>
                                <Input :id="`wali-nama-${idx}`" v-model="w.nama" placeholder="Nama lengkap orang tua / wali" />
                                <p v-if="form.errors[`wali.${idx}.nama` as keyof SiswaFormData]" class="text-xs text-destructive">
                                    {{ form.errors[`wali.${idx}.nama` as keyof SiswaFormData] }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <Label :for="`wali-hub-${idx}`" class="text-xs">Hubungan</Label>
                                <Input :id="`wali-hub-${idx}`" v-model="w.hubungan" placeholder="Misal: Paman, Kakek" />
                            </div>

                            <div class="space-y-1">
                                <Label :for="`wali-pek-${idx}`" class="text-xs">Pekerjaan</Label>
                                <Input :id="`wali-pek-${idx}`" v-model="w.pekerjaan" placeholder="Pekerjaan orang tua / wali" />
                            </div>

                            <div class="space-y-1">
                                <Label :for="`wali-hp-${idx}`" class="text-xs">No HP / WhatsApp</Label>
                                <Input :id="`wali-hp-${idx}`" v-model="w.no_hp" placeholder="08xxxxxxxxxx" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label :for="`wali-alamat-${idx}`" class="text-xs">Alamat Domisili</Label>
                            <Input :id="`wali-alamat-${idx}`" v-model="w.alamat" placeholder="Alamat tempat tinggal orang tua / wali" />
                        </div>
                    </div>
                </div>

                <!-- STEP 3: ALAMAT & KONTAK -->
                <div v-show="currentStep === 3" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="siswa-alamat">Alamat Domisili Siswa</Label>
                        <textarea
                            id="siswa-alamat"
                            v-model="form.alamat"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Alamat lengkap tempat tinggal siswa saat ini"
                        />
                        <p v-if="form.errors.alamat" class="text-xs text-destructive">{{ form.errors.alamat }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="siswa-nohp">Nomor HP / WhatsApp Siswa</Label>
                        <Input
                            id="siswa-nohp"
                            v-model="form.no_hp"
                            placeholder="08xxxxxxxxxx"
                        />
                        <p v-if="form.errors.no_hp" class="text-xs text-destructive">{{ form.errors.no_hp }}</p>
                    </div>
                </div>

                <!-- STEP 4: BERKAS DIGITAL (PLACEHOLDER) -->
                <div v-show="currentStep === 4" class="space-y-4">
                    <Alert class="border-blue-500/30 bg-blue-500/10 text-blue-800 dark:text-blue-300">
                        <FileText class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <AlertTitle class="text-xs font-semibold">Penyimpanan Berkas Digital (MinIO)</AlertTitle>
                        <AlertDescription class="text-xs leading-relaxed">
                            Fitur upload berkas siswa (Kartu Keluarga, Akta Kelahiran, Ijazah, KIP) akan diintegrasikan dengan object storage MinIO pada rilis selanjutnya. Saat ini belum ada berkas yang terunggah.
                        </AlertDescription>
                    </Alert>

                    <div class="rounded-lg border border-dashed p-8 text-center text-muted-foreground text-sm space-y-1">
                        <FileText class="h-8 w-8 mx-auto text-muted-foreground/50 mb-2" />
                        <p class="font-medium text-foreground">Belum ada berkas terunggah</p>
                        <p class="text-xs">Tabel berkas telah dimigrasikan untuk kesiapan schema di backend.</p>
                    </div>
                </div>

                <!-- STEP 5: AKADEMIK & ROMBEL -->
                <div v-show="currentStep === 5" class="space-y-4">
                    <div v-if="isWaliKelas" class="rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                        Penempatan rombel siswa dikunci bagi peran Wali Kelas. Hubungi Operator Sekolah untuk perpindahan rombel.
                    </div>

                    <div v-else-if="currentSemester && !currentSemester.is_aktif" class="rounded-lg bg-amber-500/10 border border-amber-500/20 p-3 text-xs text-amber-700 dark:text-amber-400">
                        Periode arsip (Semester non-aktif): penempatan rombel hanya dapat diubah pada semester yang aktif.
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="siswa-rombel">Penempatan Rombel Aktif</Label>
                            <Badge v-if="currentSemester" variant="outline" class="text-xs">
                                {{ currentSemester.nama }} {{ currentSemester.is_aktif ? '(Aktif)' : '(Arsip)' }}
                            </Badge>
                        </div>

                        <Select
                            :model-value="form.rombel_id || '__NONE__'"
                            @update:model-value="(val: any) => (form.rombel_id = val === '__NONE__' ? '' : val)"
                            :disabled="isWaliKelas || (currentSemester && !currentSemester.is_aktif) || form.processing"
                        >
                            <SelectTrigger id="siswa-rombel">
                                <SelectValue placeholder="Pilih rombel aktif" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__NONE__">-- Belum Masuk Rombel --</SelectItem>
                                <SelectItem
                                    v-for="r in rombelList"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    Tingkat {{ r.tingkat }} - {{ r.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.rombel_id" class="text-xs text-destructive">{{ form.errors.rombel_id }}</p>
                    </div>
                </div>

                <DialogFooter class="flex items-center justify-between gap-2 border-t pt-4">
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="currentStep === 1 || form.processing"
                            @click="prevStep"
                        >
                            Sebelumnya
                        </Button>
                        <Button
                            v-if="currentStep < totalSteps"
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="form.processing"
                            @click="nextStep"
                        >
                            Selanjutnya
                        </Button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="form.processing"
                            @click="emit('update:open', false)"
                        >
                            Batal
                        </Button>

                        <Button
                            v-if="!isEdit"
                            type="button"
                            variant="secondary"
                            size="sm"
                            :disabled="form.processing || nisnCheckStatus === 'taken'"
                            @click="submitForm(true)"
                        >
                            <Save class="h-3.5 w-3.5 mr-1" />
                            Simpan & Tambah Baru
                        </Button>

                        <Button
                            type="submit"
                            size="sm"
                            :disabled="form.processing || nisnCheckStatus === 'taken'"
                        >
                            <Loader2 v-if="form.processing" class="h-3.5 w-3.5 mr-1 animate-spin" />
                            <Save v-else class="h-3.5 w-3.5 mr-1" />
                            {{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}
                        </Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
