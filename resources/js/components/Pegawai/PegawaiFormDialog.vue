<script setup lang="ts">
import {
    AlertCircle,
    Briefcase,
    Clock,
    Loader2,
    Save,
    User,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import type { PegawaiItem } from '@/types/pegawai';

const props = defineProps<{
    open: boolean;
    pegawai: PegawaiItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success', initialPasswordData?: { nama: string; email: string; password: string } | null): void;
}>();

const isSubmitting = ref(false);
const errorMsg = ref<string | null>(null);
const activeTab = ref<'biodata' | 'kepegawaian' | 'beban'>('biodata');

// Form state
const nama = ref('');
const email = ref('');
const nip = ref('');
const nuptk = ref('');
const jenis = ref<'guru' | 'tu' | 'kepsek'>('guru');
const statusKepegawaian = ref<'pns' | 'pppk' | 'gty' | 'gtt' | 'honorer'>('gty');
const jenisKelamin = ref<'L' | 'P'>('L');
const tempatLahir = ref('');
const tanggalLahir = ref('');
const agama = ref('');
const alamat = ref('');
const noHp = ref('');
const jamMaksPerMinggu = ref(24);
const hariTidakMengajar = ref<string[]>([]);
const additionalRoles = ref<string[]>([]);

const assignableRoles = [
    { value: 'waka_kurikulum', label: 'Waka Kurikulum', desc: 'Hak akses menyusun dan mengelola jadwal pelajaran' },
    { value: 'wali_kelas', label: 'Wali Kelas', desc: 'Hak akses data siswa dan rombel binaan' },
    { value: 'operator', label: 'Operator Tambahan', desc: 'Hak akses administrasi data induk dan kepegawaian' },
];

const toggleRole = (role: string) => {
    if (additionalRoles.value.includes(role)) {
        additionalRoles.value = additionalRoles.value.filter((r) => r !== role);
    } else {
        additionalRoles.value.push(role);
    }
};

const hariOptions = [
    { value: 'senin', label: 'Senin' },
    { value: 'selasa', label: 'Selasa' },
    { value: 'rabu', label: 'Rabu' },
    { value: 'kamis', label: 'Kamis' },
    { value: 'jumat', label: 'Jumat' },
    { value: 'sabtu', label: 'Sabtu' },
];

const toggleHari = (hari: string) => {
    if (hariTidakMengajar.value.includes(hari)) {
        hariTidakMengajar.value = hariTidakMengajar.value.filter((h) => h !== hari);
    } else {
        hariTidakMengajar.value.push(hari);
    }
};

const isEdit = computed(() => !!props.pegawai);

watch(
    () => props.open,
    (val) => {
        if (val) {
            errorMsg.value = null;
            activeTab.value = 'biodata';

            if (props.pegawai) {
                nama.value = props.pegawai.nama || '';
                email.value = props.pegawai.email || props.pegawai.user?.email || '';
                nip.value = props.pegawai.nip || '';
                nuptk.value = props.pegawai.nuptk || '';
                jenis.value = props.pegawai.jenis || 'guru';
                statusKepegawaian.value = props.pegawai.status_kepegawaian || 'gty';
                jenisKelamin.value = props.pegawai.jenis_kelamin || 'L';
                tempatLahir.value = props.pegawai.tempat_lahir || '';
                tanggalLahir.value = props.pegawai.tanggal_lahir?.substring(0, 10) || '';
                agama.value = props.pegawai.agama || '';
                alamat.value = props.pegawai.alamat || '';
                noHp.value = props.pegawai.no_hp || '';
                jamMaksPerMinggu.value = props.pegawai.jam_maks_per_minggu ?? 24;
                hariTidakMengajar.value = Array.isArray(props.pegawai.hari_tidak_mengajar)
                    ? [...props.pegawai.hari_tidak_mengajar]
                    : [];
                additionalRoles.value = Array.isArray(props.pegawai.roles)
                    ? props.pegawai.roles.filter(
                        (r) => r !== props.pegawai?.jenis && !(props.pegawai?.jenis === 'tu' && r === 'operator')
                    )
                    : [];
            } else {
                nama.value = '';
                email.value = '';
                nip.value = '';
                nuptk.value = '';
                jenis.value = 'guru';
                statusKepegawaian.value = 'gty';
                jenisKelamin.value = 'L';
                tempatLahir.value = '';
                tanggalLahir.value = '';
                agama.value = '';
                alamat.value = '';
                noHp.value = '';
                jamMaksPerMinggu.value = 24;
                hariTidakMengajar.value = [];
                additionalRoles.value = [];
            }
        }
    }
);

const handleSubmit = async () => {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const payload = {
            nama: nama.value,
            email: email.value,
            nip: nip.value || null,
            nuptk: nuptk.value || null,
            jenis: jenis.value,
            status_kepegawaian: statusKepegawaian.value,
            jenis_kelamin: jenisKelamin.value,
            tempat_lahir: tempatLahir.value || null,
            tanggal_lahir: tanggalLahir.value || null,
            agama: agama.value || null,
            alamat: alamat.value || null,
            no_hp: noHp.value || null,
            jam_maks_per_minggu: jamMaksPerMinggu.value,
            hari_tidak_mengajar: hariTidakMengajar.value,
            roles: additionalRoles.value,
        };

        const url = isEdit.value ? `/pegawai/${props.pegawai?.id}` : '/pegawai';
        const method = isEdit.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0] : data.message;
            throw new Error(Array.isArray(firstError) ? firstError[0] : ((firstError as string) || 'Gagal menyimpan data pegawai.'));
        }

        emit('update:open', false);

        if (!isEdit.value && data.initial_password) {
            emit('success', {
                nama: nama.value,
                email: email.value,
                password: data.initial_password,
            });
        } else {
            emit('success', null);
        }
    } catch (err: unknown) {
        errorMsg.value = err instanceof Error ? err.message : 'Terjadi kesalahan sistem.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => emit('update:open', val)">
        <DialogContent class="sm:max-w-[700px] max-h-[90vh] flex flex-col p-0">
            <DialogHeader class="p-6 pb-3 border-b">
                <DialogTitle class="text-lg font-semibold flex items-center gap-2">
                    <Briefcase class="h-5 w-5 text-primary" />
                    {{ isEdit ? 'Ubah Data Pegawai' : 'Tambah Pegawai Baru' }}
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground mt-1">
                    {{ isEdit ? 'Perbarui data kepegawaian dan informasi profil pegawai.' : 'Akun pengguna akan dibuat otomatis dengan kata sandi acak sekali tampil.' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="errorMsg" class="px-6 pt-3">
                <Alert variant="destructive" class="py-2">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription class="text-xs">{{ errorMsg }}</AlertDescription>
                </Alert>
            </div>

            <!-- Tabs Navigation -->
            <div class="px-6 pt-3 flex-1 overflow-y-auto space-y-4">
                <div class="grid w-full grid-cols-3 p-1 bg-muted rounded-lg text-xs font-medium">
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center py-1.5 px-3 rounded-md transition-all',
                            activeTab === 'biodata' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'biodata'"
                    >
                        <User class="h-3.5 w-3.5 mr-1.5" />
                        Identitas & Kontak
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center py-1.5 px-3 rounded-md transition-all',
                            activeTab === 'kepegawaian' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'kepegawaian'"
                    >
                        <Briefcase class="h-3.5 w-3.5 mr-1.5" />
                        Kepegawaian
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center py-1.5 px-3 rounded-md transition-all',
                            activeTab === 'beban' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'beban'"
                    >
                        <Clock class="h-3.5 w-3.5 mr-1.5" />
                        Beban Mengajar
                    </button>
                </div>

                <!-- TAB 1: IDENTITAS & KONTAK -->
                <div v-show="activeTab === 'biodata'" class="space-y-3.5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        <div class="space-y-1.5">
                            <Label for="pegawai-nama" class="text-xs font-medium">Nama Lengkap *</Label>
                            <Input
                                id="pegawai-nama"
                                v-model="nama"
                                placeholder="Nama lengkap beserta gelar"
                                class="h-9 text-xs"
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="pegawai-email" class="text-xs font-medium">Alamat Email (Akun Login) *</Label>
                            <Input
                                id="pegawai-email"
                                v-model="email"
                                type="email"
                                placeholder="email@sekolah.sch.id"
                                class="h-9 text-xs"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                        <div class="space-y-1.5">
                            <Label for="pegawai-jk" class="text-xs font-medium">Jenis Kelamin</Label>
                            <Select v-model="jenisKelamin">
                                <SelectTrigger id="pegawai-jk" class="h-9 text-xs">
                                    <SelectValue placeholder="Pilih Jenis Kelamin" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="L">Laki-laki (L)</SelectItem>
                                    <SelectItem value="P">Perempuan (P)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="pegawai-tempat" class="text-xs font-medium">Tempat Lahir</Label>
                            <Input
                                id="pegawai-tempat"
                                v-model="tempatLahir"
                                placeholder="Kota kelahiran"
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="pegawai-tanggal" class="text-xs font-medium">Tanggal Lahir</Label>
                            <Input
                                id="pegawai-tanggal"
                                v-model="tanggalLahir"
                                type="date"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        <div class="space-y-1.5">
                            <Label for="pegawai-agama" class="text-xs font-medium">Agama</Label>
                            <Input
                                id="pegawai-agama"
                                v-model="agama"
                                placeholder="Islam, Kristen, dsb."
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="pegawai-nohp" class="text-xs font-medium">Nomor WhatsApp / HP</Label>
                            <Input
                                id="pegawai-nohp"
                                v-model="noHp"
                                placeholder="08xxxxxxxxxx"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="pegawai-alamat" class="text-xs font-medium">Alamat Domisili</Label>
                        <textarea
                            id="pegawai-alamat"
                            v-model="alamat"
                            rows="2"
                            placeholder="Alamat lengkap tempat tinggal"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                </div>

                <!-- TAB 2: KEPEGAWAIAN -->
                <div v-show="activeTab === 'kepegawaian'" class="space-y-3.5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        <div class="space-y-1.5">
                            <Label for="pegawai-jenis" class="text-xs font-medium">Jenis Pegawai (Peran Sistem) *</Label>
                            <Select v-model="jenis">
                                <SelectTrigger id="pegawai-jenis" class="h-9 text-xs">
                                    <SelectValue placeholder="Pilih Jenis" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="guru">Guru (Pendidik)</SelectItem>
                                    <SelectItem value="tu">Tenaga Kependidikan / Tata Usaha (Operator)</SelectItem>
                                    <SelectItem value="kepsek">Kepala Sekolah</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-[11px] text-muted-foreground">
                                Peran akun login akan disesuaikan otomatis berdasarkan jenis pegawai ini.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="pegawai-status" class="text-xs font-medium">Status Kepegawaian *</Label>
                            <Select v-model="statusKepegawaian">
                                <SelectTrigger id="pegawai-status" class="h-9 text-xs">
                                    <SelectValue placeholder="Pilih Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pns">PNS (Pegawai Negeri Sipil)</SelectItem>
                                    <SelectItem value="pppk">PPPK</SelectItem>
                                    <SelectItem value="gty">GTY (Guru Tetap Yayasan)</SelectItem>
                                    <SelectItem value="gtt">GTT (Guru Tidak Tetap)</SelectItem>
                                    <SelectItem value="honorer">Honorer</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        <div class="space-y-1.5">
                            <Label for="pegawai-nip" class="text-xs font-medium">NIP (Nomor Induk Pegawai)</Label>
                            <Input
                                id="pegawai-nip"
                                v-model="nip"
                                placeholder="18 digit angka NIP (bila ada)"
                                class="h-9 text-xs font-mono"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="pegawai-nuptk" class="text-xs font-medium">NUPTK</Label>
                            <Input
                                id="pegawai-nuptk"
                                v-model="nuptk"
                                placeholder="16 digit angka NUPTK (bila ada)"
                                class="h-9 text-xs font-mono"
                            />
                        </div>
                    </div>

                    <!-- Peran Tambahan (Hak Akses Khusus) -->
                    <div class="space-y-2 pt-2 border-t">
                        <Label class="text-xs font-medium">Peran Tambahan (Hak Akses Penugasan)</Label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label
                                v-for="r in assignableRoles"
                                :key="r.value"
                                class="flex items-start gap-2.5 p-2 rounded-md border text-xs cursor-pointer hover:bg-muted/50 transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    :value="r.value"
                                    :checked="additionalRoles.includes(r.value)"
                                    class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4 mt-0.5"
                                    @change="toggleRole(r.value)"
                                />
                                <div class="flex flex-col">
                                    <span class="font-medium text-foreground">{{ r.label }}</span>
                                    <span class="text-[11px] text-muted-foreground">{{ r.desc }}</span>
                                </div>
                            </label>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Peran dasar sesuai jenis pegawai selalu aktif. Peran super_admin dibatasi khusus tingkat platform.
                        </p>
                    </div>
                </div>

                <!-- TAB 3: BEBAN MENGAJAR (T-06.05) -->
                <div v-show="activeTab === 'beban'" class="space-y-4">
                    <div class="bg-muted/40 p-3 rounded-md text-xs text-muted-foreground flex items-center gap-2">
                        <Clock class="h-4 w-4 text-primary shrink-0" />
                        <span>Beban mengajar dan preferensi hari menjadi kendala penyusunan jadwal otomatis.</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="pegawai-jammaks" class="text-xs font-medium">
                            Beban Mengajar Maksimal per Minggu (Jam Pelajaran) *
                        </Label>
                        <Input
                            id="pegawai-jammaks"
                            v-model.number="jamMaksPerMinggu"
                            type="number"
                            min="1"
                            max="60"
                            class="h-9 text-xs w-[140px]"
                            required
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Standar beban kerja guru umumnya 24 s/d 40 jam pelajaran per minggu.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-xs font-medium">
                            Preferensi Hari Tidak Mengajar (Guru Paruh Waktu / Dosen Tamu)
                        </Label>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button
                                v-for="h in hariOptions"
                                :key="h.value"
                                type="button"
                                :class="[
                                    'px-3 py-1.5 text-xs font-medium rounded-md border transition-all cursor-pointer',
                                    hariTidakMengajar.includes(h.value)
                                        ? 'bg-destructive/10 border-destructive text-destructive font-semibold shadow-xs'
                                        : 'bg-background border-border text-muted-foreground hover:border-primary/40'
                                ]"
                                @click="toggleHari(h.value)"
                            >
                                {{ h.label }}
                                <span v-if="hariTidakMengajar.includes(h.value)" class="ml-1 text-[10px]">✕</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Hari yang dipilih akan dihindari oleh sistem saat menjadwalkan kelas untuk guru ini.
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter class="p-4 border-t bg-muted/20">
                <Button variant="outline" size="sm" class="text-xs" @click="emit('update:open', false)">
                    Batal
                </Button>
                <Button
                    size="sm"
                    class="text-xs"
                    :disabled="isSubmitting || !nama.trim() || !email.trim()"
                    @click="handleSubmit"
                >
                    <Loader2 v-if="isSubmitting" class="h-3.5 w-3.5 animate-spin mr-1.5" />
                    <Save v-else class="h-3.5 w-3.5 mr-1.5" />
                    {{ isEdit ? 'Simpan Perubahan' : 'Buat Pegawai' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
