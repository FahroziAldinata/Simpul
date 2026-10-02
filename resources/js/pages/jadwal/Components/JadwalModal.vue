<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Clock, BookOpen, User, DoorOpen, Users } from '@lucide/vue';
import type { GuruItem, JadwalItem, JamKerjaItem, MataPelajaranItem, RombelItem, RuangItem } from '../types';
import { useConflictEvaluator } from '../useConflictEvaluator';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    open: boolean;
    semesterId: string;
    editingJadwal?: JadwalItem | null;
    defaultHari?: number;
    defaultJamMulai?: number;
    defaultRombelId?: string;
    defaultGuruId?: string;
    defaultRuangId?: string;
    rombels: RombelItem[];
    gurus: GuruItem[];
    ruangs: RuangItem[];
    mapels: MataPelajaranItem[];
    jamKerjaList: JamKerjaItem[];
    allSchedules: JadwalItem[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const { evaluateSlot, HARI_NAMES } = useConflictEvaluator();

const durasiJam = ref(2);

const form = useForm({
    semester_id: props.semesterId,
    rombel_id: '',
    mata_pelajaran_id: '',
    guru_id: '',
    ruang_id: '' as string | null,
    hari: 1,
    jam_mulai_ke: 1,
    jam_selesai_ke: 3,
});

// Update form saat modal dibuka atau editingJadwal berubah
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;

        form.clearErrors();
        form.semester_id = props.semesterId;

        if (props.editingJadwal) {
            form.rombel_id = props.editingJadwal.rombel_id;
            form.mata_pelajaran_id = props.editingJadwal.mata_pelajaran_id;
            form.guru_id = props.editingJadwal.guru_id;
            form.ruang_id = props.editingJadwal.ruang_id ?? '';
            form.hari = props.editingJadwal.hari;
            form.jam_mulai_ke = props.editingJadwal.jam_mulai_ke;
            form.jam_selesai_ke = props.editingJadwal.jam_selesai_ke;
            durasiJam.value = Math.max(1, props.editingJadwal.jam_selesai_ke - props.editingJadwal.jam_mulai_ke);
        } else {
            form.rombel_id = props.defaultRombelId ?? (props.rombels[0]?.id ?? '');
            form.mata_pelajaran_id = props.mapels[0]?.id ?? '';
            form.guru_id = props.defaultGuruId ?? (props.gurus[0]?.id ?? '');
            form.ruang_id = props.defaultRuangId ?? '';
            form.hari = props.defaultHari ?? 1;
            form.jam_mulai_ke = props.defaultJamMulai ?? 1;
            durasiJam.value = 2;
            form.jam_selesai_ke = form.jam_mulai_ke + durasiJam.value;
        }
    },
    { immediate: true }
);

// Sinkronkan jam_selesai_ke saat jam_mulai_ke atau durasi berubah
watch([() => form.jam_mulai_ke, durasiJam], ([mulai, durasi]) => {
    form.jam_selesai_ke = Number(mulai) + Number(durasi);
});

const selectedMapel = computed(() => {
    return props.mapels.find((m) => m.id === form.mata_pelajaran_id);
});

// Evaluasi bentrok real-time pada formulir sebelum submit
const clientConflict = computed(() => {
    if (!form.guru_id || !form.rombel_id || !form.hari || !form.jam_mulai_ke) {
        return null;
    }

    const candidate: JadwalItem = {
        id: props.editingJadwal?.id ?? 'temp-candidate',
        sekolah_id: '',
        semester_id: props.semesterId,
        rombel_id: form.rombel_id,
        mata_pelajaran_id: form.mata_pelajaran_id,
        guru_id: form.guru_id,
        ruang_id: form.ruang_id || null,
        hari: Number(form.hari),
        jam_mulai_ke: Number(form.jam_mulai_ke),
        jam_selesai_ke: Number(form.jam_selesai_ke),
    };

    const evaluation = evaluateSlot(
        candidate.hari,
        candidate.jam_mulai_ke,
        candidate,
        props.allSchedules,
        props.jamKerjaList
    );

    return evaluation.isValid ? null : evaluation;
});

function handleSubmit() {
    form.ruang_id = form.ruang_id ? form.ruang_id : null;

    if (props.editingJadwal) {
        form.put(`/jadwal/${props.editingJadwal.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved');
            },
        });
    } else {
        form.post('/jadwal', {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved');
            },
        });
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[540px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Clock class="h-5 w-5 text-primary" />
                    <span>{{ editingJadwal ? 'Ubah Slot Jadwal' : 'Tambah Slot Jadwal' }}</span>
                </DialogTitle>
                <DialogDescription>
                    Atur slot pelajaran, rombel, guru pengampu, dan ruang kelas. Deteksi bentrok aktif secara otomatis.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-2">
                <!-- Peringatan bentrok real-time jika terdeteksi -->
                <div
                    v-if="clientConflict"
                    class="flex items-start gap-2.5 rounded-md border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-800 dark:text-rose-300"
                >
                    <AlertCircle class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                    <div>
                        <strong class="font-semibold block">Peringatan Bentrok Jadwal</strong>
                        <span>{{ clientConflict.message }}</span>
                    </div>
                </div>

                <!-- Peringatan jika mapel butuh ruang khusus -->
                <div
                    v-if="selectedMapel?.butuh_ruang_kategori"
                    class="flex items-center gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-2.5 text-xs text-amber-800 dark:text-amber-300"
                >
                    <DoorOpen class="h-4 w-4 shrink-0 text-amber-600" />
                    <span>
                        Mapel ini memerlukan ruang khusus berkategori:
                        <strong>{{ selectedMapel.butuh_ruang_kategori }}</strong>.
                    </span>
                </div>

                <!-- Rombel & Mata Pelajaran -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="rombel" class="text-xs font-semibold flex items-center gap-1">
                            <Users class="h-3.5 w-3.5" /> Rombel / Kelas
                        </Label>
                        <select
                            id="rombel"
                            v-model="form.rombel_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                            required
                        >
                            <option v-for="r in rombels" :key="r.id" :value="r.id">
                                {{ r.nama }} (Tingkat {{ r.tingkat }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="mapel" class="text-xs font-semibold flex items-center gap-1">
                            <BookOpen class="h-3.5 w-3.5" /> Mata Pelajaran
                        </Label>
                        <select
                            id="mapel"
                            v-model="form.mata_pelajaran_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                            required
                        >
                            <option v-for="m in mapels" :key="m.id" :value="m.id">
                                {{ m.nama }} ({{ m.kelompok }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Guru Pengampu & Ruang -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="guru" class="text-xs font-semibold flex items-center gap-1">
                            <User class="h-3.5 w-3.5" /> Guru Pengampu
                        </Label>
                        <select
                            id="guru"
                            v-model="form.guru_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                            required
                        >
                            <option v-for="g in gurus" :key="g.id" :value="g.id">
                                {{ g.nama }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="ruang" class="text-xs font-semibold flex items-center gap-1">
                            <DoorOpen class="h-3.5 w-3.5" /> Ruang Belajar
                        </Label>
                        <select
                            id="ruang"
                            v-model="form.ruang_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                        >
                            <option value="">-- Tanpa Ruang Khusus --</option>
                            <option v-for="ru in ruangs" :key="ru.id" :value="ru.id">
                                {{ ru.nama }} ({{ ru.kategori }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Hari, Jam Mulai & Durasi (JP) -->
                <div class="grid grid-cols-3 gap-3 border-t border-border/40 pt-3">
                    <div class="space-y-1.5">
                        <Label for="hari" class="text-xs font-semibold">Hari</Label>
                        <select
                            id="hari"
                            v-model="form.hari"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                            required
                        >
                            <option v-for="h in [1, 2, 3, 4, 5, 6]" :key="h" :value="h">
                                {{ HARI_NAMES[h] }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="jam_mulai" class="text-xs font-semibold">Jam Mulai Ke</Label>
                        <select
                            id="jam_mulai"
                            v-model.number="form.jam_mulai_ke"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                            required
                        >
                            <option v-for="j in 10" :key="j" :value="j">
                                Jam ke-{{ j }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="durasi" class="text-xs font-semibold">Durasi (JP)</Label>
                        <select
                            id="durasi"
                            v-model.number="durasiJam"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring"
                            required
                        >
                            <option :value="1">1 Jam Pelajaran</option>
                            <option :value="2">2 Jam Pelajaran</option>
                            <option :value="3">3 Jam Pelajaran</option>
                            <option :value="4">4 Jam Pelajaran</option>
                        </select>
                    </div>
                </div>

                <div class="text-[11px] text-muted-foreground bg-muted/30 rounded p-2 flex items-center justify-between">
                    <span>Rentang Slot Terjadwal:</span>
                    <strong class="font-mono text-foreground">
                        Jam ke-{{ form.jam_mulai_ke }} s.d. {{ form.jam_selesai_ke - 1 }} ({{ durasiJam }} JP)
                    </strong>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="emit('update:open', false)">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : editingJadwal ? 'Simpan Perubahan' : 'Tambah Jadwal' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
