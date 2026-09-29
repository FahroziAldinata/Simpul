<script setup lang="ts">
import {
    AlertCircle,
    ArrowRightLeft,
    CheckCircle2,
    Clock,
    History,
    Loader2,
    PlusCircle,
    RotateCcw,
    School,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import type { MutasiItem, RiwayatKelasItem, RombelOption, SemesterInfo, SiswaItem } from '@/types/siswa';

const props = defineProps<{
    open: boolean;
    siswa: SiswaItem | null;
    rombelList: RombelOption[];
    currentSemester?: SemesterInfo | null;
    canManage: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const activeTab = ref<'riwayat-kelas' | 'riwayat-mutasi' | 'catat-mutasi'>('riwayat-mutasi');
const isLoading = ref(false);
const isSubmitting = ref(false);
const errorMsg = ref<string | null>(null);
const successMsg = ref<string | null>(null);

// Data fetched from server
const mutasiList = ref<MutasiItem[]>([]);
const riwayatKelasList = ref<RiwayatKelasItem[]>([]);
const currentStatus = ref<string>('aktif');

// Form state for new mutation
const formTipe = ref<'masuk' | 'keluar' | 'pindah_rombel' | 'naik_kelas' | 'tinggal_kelas' | 'lulus' | 'drop_out'>('pindah_rombel');
const formTanggal = ref<string>(new Date().toISOString().substring(0, 10));
const formKeRombelId = ref<string>('');
const formAsalSekolah = ref<string>('');
const formSekolahTujuan = ref<string>('');
const formAlasan = ref<string>('');

// Cancellation state
const isCancelDialogOpen = ref(false);
const selectedMutasiToCancel = ref<MutasiItem | null>(null);
const alasanBatal = ref('');
const isCancelling = ref(false);
const cancelError = ref<string | null>(null);

const latestUncancelledMutasiId = computed(() => {
    const uncancelled = mutasiList.value.filter((m) => !m.is_batal);
    if (uncancelled.length === 0) return null;
    return uncancelled[0].id;
});

const needsRombelTujuan = computed(() => {
    return ['masuk', 'pindah_rombel', 'naik_kelas', 'tinggal_kelas'].includes(formTipe.value);
});

const needsAsalSekolah = computed(() => formTipe.value === 'masuk');
const needsSekolahTujuan = computed(() => formTipe.value === 'keluar');

const fetchHistory = async () => {
    if (!props.siswa) return;
    isLoading.value = true;
    errorMsg.value = null;

    try {
        const response = await fetch(`/siswa/${props.siswa.id}/mutasi`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Gagal memuat data mutasi.');
        }

        const data = await response.json();
        mutasiList.value = data.mutasi || [];
        riwayatKelasList.value = data.riwayat_kelas || [];
        currentStatus.value = data.status || 'aktif';
    } catch (err: unknown) {
        errorMsg.value = err instanceof Error ? err.message : 'Terjadi kesalahan sistem.';
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.open,
    (val) => {
        if (val && props.siswa) {
            activeTab.value = 'riwayat-mutasi';
            successMsg.value = null;
            errorMsg.value = null;
            formTanggal.value = new Date().toISOString().substring(0, 10);
            formKeRombelId.value = '';
            formAsalSekolah.value = '';
            formSekolahTujuan.value = '';
            formAlasan.value = '';
            fetchHistory();
        }
    }
);

const getTipeBadge = (tipe: string) => {
    switch (tipe) {
        case 'masuk':
            return { label: 'Siswa Masuk', variant: 'outline', class: 'border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' };
        case 'keluar':
            return { label: 'Mutasi Keluar', variant: 'outline', class: 'border-rose-500 text-rose-600 dark:text-rose-400 bg-rose-500/10' };
        case 'pindah_rombel':
            return { label: 'Pindah Rombel', variant: 'outline', class: 'border-blue-500 text-blue-600 dark:text-blue-400 bg-blue-500/10' };
        case 'naik_kelas':
            return { label: 'Naik Kelas', variant: 'outline', class: 'border-indigo-500 text-indigo-600 dark:text-indigo-400 bg-indigo-500/10' };
        case 'tinggal_kelas':
            return { label: 'Tinggal Kelas', variant: 'outline', class: 'border-amber-500 text-amber-600 dark:text-amber-400 bg-amber-500/10' };
        case 'lulus':
            return { label: 'Lulus', variant: 'outline', class: 'border-purple-500 text-purple-600 dark:text-purple-400 bg-purple-500/10' };
        case 'drop_out':
            return { label: 'Drop Out', variant: 'outline', class: 'border-zinc-500 text-zinc-600 dark:text-zinc-400 bg-zinc-500/10' };
        default:
            return { label: tipe, variant: 'outline', class: '' };
    }
};

const handleStoreMutasi = async () => {
    if (!props.siswa) return;
    isSubmitting.value = true;
    errorMsg.value = null;
    successMsg.value = null;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const payload: Record<string, string | null> = {
            tipe: formTipe.value,
            tanggal: formTanggal.value,
            alasan: formAlasan.value || null,
        };

        if (needsRombelTujuan.value) {
            payload.ke_rombel_id = formKeRombelId.value;
        }
        if (needsAsalSekolah.value) {
            payload.asal_sekolah = formAsalSekolah.value;
        }
        if (needsSekolahTujuan.value) {
            payload.sekolah_tujuan = formSekolahTujuan.value;
        }

        const response = await fetch(`/siswa/${props.siswa.id}/mutasi`, {
            method: 'POST',
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
            throw new Error(Array.isArray(firstError) ? firstError[0] : ((firstError as string) || 'Gagal menyimpan mutasi.'));
        }

        successMsg.value = data.message || 'Mutasi berhasil dicatat.';
        // Reset form
        formAlasan.value = '';
        formAsalSekolah.value = '';
        formSekolahTujuan.value = '';
        formKeRombelId.value = '';

        await fetchHistory();
        activeTab.value = 'riwayat-mutasi';
        emit('success');
    } catch (err: unknown) {
        errorMsg.value = err instanceof Error ? err.message : 'Terjadi kesalahan sistem.';
    } finally {
        isSubmitting.value = false;
    }
};

const openCancelDialog = (mutasi: MutasiItem) => {
    selectedMutasiToCancel.value = mutasi;
    alasanBatal.value = '';
    cancelError.value = null;
    isCancelDialogOpen.value = true;
};

const handleCancelMutasi = async () => {
    if (!selectedMutasiToCancel.value) return;
    isCancelling.value = true;
    cancelError.value = null;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const response = await fetch(`/mutasi/${selectedMutasiToCancel.value.id}/batal`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                alasan_batal: alasanBatal.value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0] : data.message;
            throw new Error(Array.isArray(firstError) ? firstError[0] : ((firstError as string) || 'Gagal membatalkan mutasi.'));
        }

        isCancelDialogOpen.value = false;
        successMsg.value = data.message || 'Mutasi berhasil dibatalkan.';
        await fetchHistory();
        emit('success');
    } catch (err: unknown) {
        cancelError.value = err instanceof Error ? err.message : 'Terjadi kesalahan saat membatalkan.';
    } finally {
        isCancelling.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => emit('update:open', val)">
        <DialogContent class="sm:max-w-[750px] max-h-[90vh] flex flex-col p-0">
            <DialogHeader class="p-6 pb-3 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <DialogTitle class="text-lg font-semibold flex items-center gap-2">
                            <ArrowRightLeft class="h-5 w-5 text-primary" />
                            Mutasi & Riwayat Kelas
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-1">
                            Siswa: <strong class="text-foreground">{{ siswa?.nama }}</strong> (NISN: {{ siswa?.nisn }})
                            • Status Saat Ini:
                            <Badge variant="outline" class="ml-1 text-[11px] font-normal uppercase">
                                {{ currentStatus }}
                            </Badge>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Alerts -->
            <div v-if="successMsg" class="px-6 pt-3">
                <Alert class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 py-2">
                    <CheckCircle2 class="h-4 w-4" />
                    <AlertDescription class="text-xs">{{ successMsg }}</AlertDescription>
                </Alert>
            </div>
            <div v-if="errorMsg" class="px-6 pt-3">
                <Alert variant="destructive" class="py-2">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription class="text-xs">{{ errorMsg }}</AlertDescription>
                </Alert>
            </div>

            <!-- Tabs Navigation -->
            <div class="px-6 pt-2 flex-1 overflow-y-auto">
                <div class="grid w-full grid-cols-3 p-1 bg-muted rounded-lg mb-4 text-xs font-medium">
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center py-1.5 px-3 rounded-md transition-all',
                            activeTab === 'riwayat-mutasi' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'riwayat-mutasi'"
                    >
                        <Clock class="h-3.5 w-3.5 mr-1.5" />
                        Riwayat Mutasi
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center py-1.5 px-3 rounded-md transition-all',
                            activeTab === 'riwayat-kelas' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'riwayat-kelas'"
                    >
                        <History class="h-3.5 w-3.5 mr-1.5" />
                        Riwayat Kelas
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        :class="[
                            'flex items-center justify-center py-1.5 px-3 rounded-md transition-all',
                            activeTab === 'catat-mutasi' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'catat-mutasi'"
                    >
                        <PlusCircle class="h-3.5 w-3.5 mr-1.5" />
                        Catat Mutasi Baru
                    </button>
                </div>

                <!-- TAB 1: RIWAYAT MUTASI -->
                <div v-if="activeTab === 'riwayat-mutasi'" class="space-y-4">
                        <div v-if="isLoading" class="flex items-center justify-center py-12 text-muted-foreground text-sm">
                            <Loader2 class="h-5 w-5 animate-spin mr-2" />
                            Memuat riwayat mutasi...
                        </div>

                        <div v-else-if="mutasiList.length === 0" class="text-center py-12 border rounded-lg border-dashed">
                            <Clock class="h-8 w-8 mx-auto text-muted-foreground mb-2 opacity-50" />
                            <p class="text-sm font-medium text-foreground">Belum ada riwayat mutasi</p>
                            <p class="text-xs text-muted-foreground mt-0.5">Siswa belum pernah mengalami perpindahan atau perubahan status.</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="item in mutasiList"
                                :key="item.id"
                                :class="[
                                    'p-4 rounded-lg border text-xs transition-colors',
                                    item.is_batal
                                        ? 'bg-muted/30 border-muted opacity-60 line-through-text'
                                        : 'bg-card border-border hover:border-primary/40'
                                ]"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Badge :variant="getTipeBadge(item.tipe).variant as any" :class="getTipeBadge(item.tipe).class">
                                            {{ getTipeBadge(item.tipe).label }}
                                        </Badge>
                                        <span class="font-mono text-muted-foreground">{{ item.tanggal }}</span>
                                        <span v-if="item.semester" class="text-muted-foreground font-medium">• Semester: {{ item.semester.nama }}</span>
                                    </div>

                                    <!-- Status / Batalkan Button -->
                                    <div>
                                        <Badge v-if="item.is_batal" variant="destructive" class="text-[10px] uppercase font-mono">
                                            Dibatalkan
                                        </Badge>
                                        <Button
                                            v-else-if="canManage && item.id === latestUncancelledMutasiId"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 text-xs text-destructive border-destructive/30 hover:bg-destructive/10"
                                            @click="openCancelDialog(item)"
                                        >
                                            <RotateCcw class="h-3 w-3 mr-1" />
                                            Batalkan Mutasi
                                        </Button>
                                    </div>
                                </div>

                                <!-- Detail Perjalanan Rombel / Asal-Tujuan -->
                                <div class="mt-2.5 space-y-1 text-foreground">
                                    <div v-if="item.dari_rombel || item.ke_rombel" class="flex items-center gap-2">
                                        <span class="text-muted-foreground">Rombel:</span>
                                        <span class="font-medium">{{ item.dari_rombel?.nama || '(Belum ada rombel)' }}</span>
                                        <span class="text-muted-foreground">→</span>
                                        <span class="font-semibold text-primary">{{ item.ke_rombel?.nama || '-' }}</span>
                                    </div>

                                    <div v-if="item.asal_sekolah" class="flex items-center gap-2">
                                        <span class="text-muted-foreground">Asal Sekolah:</span>
                                        <span class="font-medium">{{ item.asal_sekolah }}</span>
                                    </div>

                                    <div v-if="item.sekolah_tujuan" class="flex items-center gap-2">
                                        <span class="text-muted-foreground">Sekolah Tujuan:</span>
                                        <span class="font-medium">{{ item.sekolah_tujuan }}</span>
                                    </div>

                                    <div v-if="item.alasan" class="text-muted-foreground italic mt-1">
                                        "{{ item.alasan }}"
                                    </div>
                                </div>

                                <!-- Info Pembatalan jika dibatalkan -->
                                <div v-if="item.is_batal" class="mt-2 pt-2 border-t border-border/50 text-[11px] text-destructive flex flex-col gap-0.5">
                                    <span class="font-medium">Alasan Pembatalan: {{ item.alasan_batal || '-' }}</span>
                                    <span class="text-muted-foreground">
                                        Dibatalkan pada {{ item.dibatalkan_at?.substring(0, 16) }} oleh {{ item.dibatalkan_oleh_user?.name || 'Sistem' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                </div>

                <!-- TAB 2: RIWAYAT KELAS (T-06.02) -->
                <div v-if="activeTab === 'riwayat-kelas'" class="space-y-4">
                    <div class="bg-muted/40 p-3 rounded-md text-xs text-muted-foreground flex items-center gap-2">
                        <History class="h-4 w-4 text-primary shrink-0" />
                        <span>Riwayat penempatan kelas tersimpan permanen per semester dan tidak pernah ditimpa.</span>
                    </div>

                    <div v-if="isLoading" class="flex items-center justify-center py-12 text-muted-foreground text-sm">
                        <Loader2 class="h-5 w-5 animate-spin mr-2" />
                        Memuat riwayat kelas...
                    </div>

                    <div v-else-if="riwayatKelasList.length === 0" class="text-center py-12 border rounded-lg border-dashed">
                        <School class="h-8 w-8 mx-auto text-muted-foreground mb-2 opacity-50" />
                        <p class="text-sm font-medium text-foreground">Belum ada riwayat kelas</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Siswa belum ditempatkan pada rombel mana pun.</p>
                    </div>

                    <div v-else class="border rounded-md overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/60 border-b">
                                <tr class="text-left font-medium text-muted-foreground">
                                    <th class="p-2.5">Semester</th>
                                    <th class="p-2.5">Tingkat</th>
                                    <th class="p-2.5">Rombel</th>
                                    <th class="p-2.5">Wali Kelas</th>
                                    <th class="p-2.5 text-center">Status Semester</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="rk in riwayatKelasList" :key="rk.id" class="hover:bg-muted/30">
                                    <td class="p-2.5 font-medium text-foreground">
                                        {{ rk.semester?.nama || '-' }}
                                    </td>
                                    <td class="p-2.5 tabular-nums">
                                        Tingkat {{ rk.rombel?.tingkat || '-' }}
                                    </td>
                                    <td class="p-2.5 font-semibold text-primary">
                                        {{ rk.rombel?.nama || '-' }}
                                    </td>
                                    <td class="p-2.5 text-muted-foreground">
                                        {{ rk.rombel?.wali_kelas?.nama || '-' }}
                                    </td>
                                    <td class="p-2.5 text-center">
                                        <Badge v-if="rk.semester?.is_aktif" variant="outline" class="border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-[10px]">
                                            Aktif
                                        </Badge>
                                        <Badge v-else variant="outline" class="text-muted-foreground border-border text-[10px]">
                                            Arsip
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: CATAT MUTASI BARU (T-06.03) -->
                <div v-if="canManage && activeTab === 'catat-mutasi'" class="space-y-4 pb-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Jenis Mutasi -->
                            <div class="space-y-1.5">
                                <Label for="mutasi-tipe" class="text-xs font-medium">Jenis Mutasi *</Label>
                                <Select v-model="formTipe">
                                    <SelectTrigger id="mutasi-tipe" class="h-9 text-xs">
                                        <SelectValue placeholder="Pilih jenis mutasi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pindah_rombel">Pindah Rombel</SelectItem>
                                        <SelectItem value="masuk">Siswa Masuk (Pindahan)</SelectItem>
                                        <SelectItem value="naik_kelas">Naik Kelas</SelectItem>
                                        <SelectItem value="tinggal_kelas">Tinggal Kelas</SelectItem>
                                        <SelectItem value="keluar">Mutasi Keluar (Pindah Sekolah)</SelectItem>
                                        <SelectItem value="lulus">Lulus</SelectItem>
                                        <SelectItem value="drop_out">Drop Out</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Tanggal Efektif Mutasi -->
                            <div class="space-y-1.5">
                                <Label for="mutasi-tanggal" class="text-xs font-medium">Tanggal Efektif Mutasi *</Label>
                                <Input
                                    id="mutasi-tanggal"
                                    v-model="formTanggal"
                                    type="date"
                                    class="h-9 text-xs"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Rombel Tujuan (Untuk Masuk, Pindah Rombel, Naik Kelas, Tinggal Kelas) -->
                        <div v-if="needsRombelTujuan" class="space-y-1.5">
                            <Label for="mutasi-ke-rombel" class="text-xs font-medium">Rombel Tujuan *</Label>
                            <Select v-model="formKeRombelId">
                                <SelectTrigger id="mutasi-ke-rombel" class="h-9 text-xs">
                                    <SelectValue placeholder="Pilih rombel tujuan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="r in rombelList"
                                        :key="r.id"
                                        :value="r.id"
                                    >
                                        {{ r.nama }} (Tingkat {{ r.tingkat }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-[11px] text-muted-foreground">
                                Hanya rombel pada semester aktif yang dapat dijadikan tujuan mutasi.
                            </p>
                        </div>

                        <!-- Asal Sekolah (Khusus Masuk) -->
                        <div v-if="needsAsalSekolah" class="space-y-1.5">
                            <Label for="mutasi-asal-sekolah" class="text-xs font-medium">Asal Sekolah *</Label>
                            <Input
                                id="mutasi-asal-sekolah"
                                v-model="formAsalSekolah"
                                placeholder="Nama sekolah asal sebelumnya"
                                class="h-9 text-xs"
                                required
                            />
                        </div>

                        <!-- Sekolah Tujuan (Khusus Keluar) -->
                        <div v-if="needsSekolahTujuan" class="space-y-1.5">
                            <Label for="mutasi-sekolah-tujuan" class="text-xs font-medium">Sekolah Tujuan *</Label>
                            <Input
                                id="mutasi-sekolah-tujuan"
                                v-model="formSekolahTujuan"
                                placeholder="Nama sekolah tujuan kepindahan"
                                class="h-9 text-xs"
                                required
                            />
                        </div>

                        <!-- Alasan Mutasi -->
                        <div class="space-y-1.5">
                            <Label for="mutasi-alasan" class="text-xs font-medium">Alasan / Catatan Mutasi</Label>
                            <textarea
                                id="mutasi-alasan"
                                v-model="formAlasan"
                                placeholder="Tuliskan keterangan pendukung atau nomor surat mutasi..."
                                rows="3"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>

                        <div class="pt-2 flex justify-end">
                            <Button
                                :disabled="isSubmitting || (needsRombelTujuan && !formKeRombelId) || (needsAsalSekolah && !formAsalSekolah) || (needsSekolahTujuan && !formSekolahTujuan)"
                                class="h-9 text-xs"
                                @click="handleStoreMutasi"
                            >
                                <Loader2 v-if="isSubmitting" class="h-3.5 w-3.5 animate-spin mr-1.5" />
                                Eksekusi Mutasi
                            </Button>
                        </div>
                    </div>
            </div>

            <DialogFooter class="p-4 border-t bg-muted/20">
                <Button variant="outline" size="sm" class="text-xs" @click="emit('update:open', false)">
                    Tutup
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Dialog Konfirmasi Pembatalan Mutasi -->
    <Dialog :open="isCancelDialogOpen" @update:open="(val: boolean) => (isCancelDialogOpen = val)">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="text-base font-semibold flex items-center gap-2 text-destructive">
                    <ShieldAlert class="h-5 w-5" />
                    Konfirmasi Pembatalan Mutasi
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground mt-1">
                    Anda akan membatalkan mutasi <strong>{{ getTipeBadge(selectedMutasiToCancel?.tipe || '').label }}</strong>
                    tanggal {{ selectedMutasiToCancel?.tanggal }}.
                    Status siswa dan rombel akan dikembalikan ke kondisi sebelum mutasi.
                </DialogDescription>
            </DialogHeader>

            <div v-if="cancelError" class="py-1">
                <Alert variant="destructive" class="py-2 text-xs">
                    {{ cancelError }}
                </Alert>
            </div>

            <div class="space-y-2 py-2">
                <Label for="alasan-batal" class="text-xs font-medium">Alasan Pembatalan *</Label>
                <textarea
                    id="alasan-batal"
                    v-model="alasanBatal"
                    placeholder="Contoh: Koreksi salah input entri rombel..."
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    required
                />
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" size="sm" class="text-xs" @click="isCancelDialogOpen = false">
                    Batal
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="text-xs"
                    :disabled="isCancelling || alasanBatal.trim().length < 3"
                    @click="handleCancelMutasi"
                >
                    <Loader2 v-if="isCancelling" class="h-3.5 w-3.5 animate-spin mr-1.5" />
                    Ya, Batalkan Mutasi
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
