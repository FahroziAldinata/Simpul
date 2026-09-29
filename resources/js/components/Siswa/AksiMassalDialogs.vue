<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertCircle, ArrowRightLeft, Loader2, UserCog } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
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
import type { RombelOption } from '@/types/siswa';

const props = defineProps<{
    selectedIds: string[];
    rombelList: RombelOption[];
    openRombel: boolean;
    openStatus: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:openRombel', value: boolean): void;
    (e: 'update:openStatus', value: boolean): void;
    (e: 'success'): void;
}>();

// Form Rombel
const isSubmittingRombel = ref(false);
const rombelForm = reactive({
    rombel_id: '',
    alasan: '',
});
const rombelErrors = ref<Record<string, string>>({});

// Form Status
const isSubmittingStatus = ref(false);
const statusForm = reactive({
    status: 'lulus',
    tanggal: new Date().toISOString().substring(0, 10),
    alasan: '',
    sekolah_tujuan: '',
});
const statusErrors = ref<Record<string, string>>({});

const selectedRombelData = computed(() => {
    return props.rombelList.find((r) => r.id === rombelForm.rombel_id);
});

const isOverQuotaWarning = computed(() => {
    if (!selectedRombelData.value) return false;
    const r = selectedRombelData.value;
    if (r.kuota === null || r.kuota === undefined) return false;
    return (r.jumlah_siswa ?? 0) + props.selectedIds.length > r.kuota;
});

function submitUbahRombel() {
    if (!rombelForm.rombel_id) {
        rombelErrors.value = { rombel_id: 'Rombel tujuan wajib dipilih.' };
        return;
    }

    rombelErrors.value = {};
    isSubmittingRombel.value = true;

    router.post(
        '/siswa/aksi-massal/rombel',
        {
            siswa_ids: props.selectedIds,
            rombel_id: rombelForm.rombel_id,
            alasan: rombelForm.alasan || 'Aksi massal pindah rombel',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:openRombel', false);
                emit('success');
                rombelForm.rombel_id = '';
                rombelForm.alasan = '';
            },
            onError: (errs) => {
                rombelErrors.value = errs as Record<string, string>;
            },
            onFinish: () => {
                isSubmittingRombel.value = false;
            },
        }
    );
}

function submitUbahStatus() {
    statusErrors.value = {};
    const isKeluar = ['keluar', 'mutasi_keluar', 'pindah'].includes(statusForm.status);

    if (isKeluar) {
        if (!statusForm.tanggal) {
            statusErrors.value.tanggal = 'Tanggal mutasi keluar wajib diisi.';
        }
        if (!statusForm.alasan) {
            statusErrors.value.alasan = 'Alasan mutasi keluar wajib diisi.';
        }
        if (!statusForm.sekolah_tujuan) {
            statusErrors.value.sekolah_tujuan = 'Sekolah tujuan mutasi keluar wajib diisi.';
        }
        if (Object.keys(statusErrors.value).length > 0) {
            return;
        }
    }

    isSubmittingStatus.value = true;

    router.post(
        '/siswa/aksi-massal/status',
        {
            siswa_ids: props.selectedIds,
            status: statusForm.status,
            tanggal: statusForm.tanggal,
            alasan: statusForm.alasan,
            sekolah_tujuan: isKeluar ? statusForm.sekolah_tujuan : undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:openStatus', false);
                emit('success');
                statusForm.alasan = '';
                statusForm.sekolah_tujuan = '';
            },
            onError: (errs) => {
                statusErrors.value = errs as Record<string, string>;
            },
            onFinish: () => {
                isSubmittingStatus.value = false;
            },
        }
    );
}
</script>

<template>
    <!-- Dialog Ubah Rombel Massal -->
    <Dialog :open="openRombel" @update:open="(val: boolean) => emit('update:openRombel', val)">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="p-2 rounded-lg bg-primary/10 text-primary">
                        <ArrowRightLeft class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle>Ubah Rombel Massal</DialogTitle>
                        <DialogDescription>
                            Pindahkan <strong>{{ selectedIds.length }}</strong> siswa terpilih ke rombel tujuan.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submitUbahRombel" class="space-y-4 py-2">
                <!-- Global / Field Error Notice -->
                <div v-if="rombelErrors.siswa_ids" class="p-3 rounded-lg bg-destructive/10 border border-destructive/20 text-xs text-destructive flex items-start gap-2">
                    <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                    <span>{{ rombelErrors.siswa_ids }}</span>
                </div>

                <div class="space-y-1.5">
                    <Label for="bulk-rombel-id">Rombel Tujuan <span class="text-destructive">*</span></Label>
                    <Select v-model="rombelForm.rombel_id">
                        <SelectTrigger id="bulk-rombel-id">
                            <SelectValue placeholder="Pilih rombel tujuan..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="r in rombelList"
                                :key="r.id"
                                :value="r.id"
                            >
                                Tingkat {{ r.tingkat }} - {{ r.nama }} ({{ r.jumlah_siswa ?? 0 }}/{{ r.kuota ?? '∞' }} siswa)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="rombelErrors.rombel_id" class="text-xs text-destructive">
                        {{ rombelErrors.rombel_id }}
                    </p>
                </div>

                <!-- Over Quota Warning Notice -->
                <div v-if="isOverQuotaWarning" class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-xs text-amber-700 dark:text-amber-400 flex items-start gap-2">
                    <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium">Peringatan Kuota Terlampaui</p>
                        <p class="mt-0.5">
                            Jumlah siswa di rombel ini akan menjadi {{ (selectedRombelData?.jumlah_siswa ?? 0) + selectedIds.length }} dari kuota {{ selectedRombelData?.kuota }}. Pemindahan tetap dapat diproses.
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="bulk-rombel-alasan">Alasan Perpindahan (Opsional)</Label>
                    <Input
                        id="bulk-rombel-alasan"
                        v-model="rombelForm.alasan"
                        placeholder="Contoh: Penyeimbangan rombel semester genap"
                    />
                </div>

                <DialogFooter class="pt-3">
                    <Button type="button" variant="outline" @click="emit('update:openRombel', false)">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="isSubmittingRombel">
                        <Loader2 v-if="isSubmittingRombel" class="h-4 w-4 mr-2 animate-spin" />
                        Pindahkan Siswa
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog Ubah Status Massal -->
    <Dialog :open="openStatus" @update:open="(val: boolean) => emit('update:openStatus', val)">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="p-2 rounded-lg bg-primary/10 text-primary">
                        <UserCog class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle>Ubah Status Siswa Massal</DialogTitle>
                        <DialogDescription>
                            Perbarui status untuk <strong>{{ selectedIds.length }}</strong> siswa terpilih.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submitUbahStatus" class="space-y-4 py-2">
                <!-- Global / Field Error Notice -->
                <div v-if="statusErrors.siswa_ids" class="p-3 rounded-lg bg-destructive/10 border border-destructive/20 text-xs text-destructive flex items-start gap-2">
                    <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                    <span>{{ statusErrors.siswa_ids }}</span>
                </div>

                <div class="space-y-1.5">
                    <Label for="bulk-status-select">Status Baru <span class="text-destructive">*</span></Label>
                    <Select v-model="statusForm.status">
                        <SelectTrigger id="bulk-status-select">
                            <SelectValue placeholder="Pilih status baru..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="lulus">Lulus</SelectItem>
                            <SelectItem value="keluar">Keluar / Pindah Sekolah</SelectItem>
                            <SelectItem value="drop_out">Drop Out (DO)</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Fields for Mutasi Keluar -->
                <template v-if="['keluar', 'mutasi_keluar', 'pindah'].includes(statusForm.status)">
                    <div class="space-y-1.5">
                        <Label for="bulk-status-sekolah-tujuan">
                            Sekolah Tujuan <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="bulk-status-sekolah-tujuan"
                            v-model="statusForm.sekolah_tujuan"
                            placeholder="Contoh: SMA Negeri 1 Jakarta"
                            required
                        />
                        <p v-if="statusErrors.sekolah_tujuan" class="text-xs text-destructive">
                            {{ statusErrors.sekolah_tujuan }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="bulk-status-alasan">
                            Alasan Keluar <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="bulk-status-alasan"
                            v-model="statusForm.alasan"
                            placeholder="Contoh: Mengikuti domisili orang tua"
                            required
                        />
                        <p v-if="statusErrors.alasan" class="text-xs text-destructive">
                            {{ statusErrors.alasan }}
                        </p>
                    </div>
                </template>

                <!-- Optional Alasan for Lulus / DO -->
                <template v-else>
                    <div class="space-y-1.5">
                        <Label for="bulk-status-alasan-opt">Alasan (Opsional)</Label>
                        <Input
                            id="bulk-status-alasan-opt"
                            v-model="statusForm.alasan"
                            placeholder="Keterangan tambahan..."
                        />
                    </div>
                </template>

                <div class="space-y-1.5">
                    <Label for="bulk-status-tanggal">Tanggal Efektif <span class="text-destructive">*</span></Label>
                    <Input
                        id="bulk-status-tanggal"
                        type="date"
                        v-model="statusForm.tanggal"
                        required
                    />
                    <p v-if="statusErrors.tanggal" class="text-xs text-destructive">
                        {{ statusErrors.tanggal }}
                    </p>
                </div>

                <DialogFooter class="pt-3">
                    <Button type="button" variant="outline" @click="emit('update:openStatus', false)">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="isSubmittingStatus">
                        <Loader2 v-if="isSubmittingStatus" class="h-4 w-4 mr-2 animate-spin" />
                        Simpan Perubahan Status
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
