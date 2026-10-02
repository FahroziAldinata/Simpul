<script setup lang="ts">
import { ref, computed } from 'vue';
import { Plus, Ban, CalendarOff } from '@lucide/vue';
import type { JadwalItem, JamKerjaItem, ViewMode } from '../types';
import DraggableCard from './DraggableCard.vue';
import ConflictOverlay from './ConflictOverlay.vue';
import { useConflictEvaluator } from '../useConflictEvaluator';

const props = defineProps<{
    jadwals: JadwalItem[];
    allSchedules: JadwalItem[];
    jamKerjaList: JamKerjaItem[];
    viewMode: ViewMode;
    canManage: boolean;
}>();

const emit = defineEmits<{
    (e: 'add-slot', hari: number, jamMulai: number): void;
    (e: 'move-slot', payload: { id: string; hari: number; jam_mulai_ke: number; jam_selesai_ke: number }): void;
    (e: 'edit-slot', jadwal: JadwalItem): void;
    (e: 'delete-slot', jadwal: JadwalItem): void;
}>();

const { evaluateSlot, HARI_NAMES } = useConflictEvaluator();

// State interaksi Drag & Drop dan Tap-to-move
const activeCard = ref<JadwalItem | null>(null);
const isDragging = ref(false);
const dragOverKey = ref<string | null>(null);

// Konfigurasi Grid Harian (1=Senin s/d 6=Sabtu)
const days = [1, 2, 3, 4, 5, 6];

// Hitung jam operasional maksimal di seluruh hari kerja (default 10)
const maxJamOperasional = computed(() => {
    let max = 10;
    for (const jk of props.jamKerjaList) {
        if (!jk.is_libur && jk.jumlah_jam_pelajaran > max) {
            max = jk.jumlah_jam_pelajaran;
        }
    }
    return max;
});

// Peta jam kerja per hari
const jamKerjaMap = computed(() => {
    const map = new Map<number, JamKerjaItem>();
    for (const jk of props.jamKerjaList) {
        map.set(jk.hari, jk);
    }
    return map;
});

function getJamKerjaHari(hari: number): JamKerjaItem | undefined {
    return jamKerjaMap.value.get(hari);
}

function isHariLibur(hari: number): boolean {
    const jk = getJamKerjaHari(hari);
    return jk ? jk.is_libur : false;
}

function isOutOfOperationalHours(hari: number, jam: number): boolean {
    const jk = getJamKerjaHari(hari);
    if (!jk || jk.is_libur) return true;
    return jam > jk.jumlah_jam_pelajaran;
}

// Cari jadwal yang dimulai persis pada (hari, jam)
function getScheduleAt(hari: number, jam: number): JadwalItem | undefined {
    return props.jadwals.find((j) => j.hari === hari && j.jam_mulai_ke === jam);
}

// Cek apakah slot (hari, jam) tercakup dalam rentang jadwal jam sebelumnya
function isCoveredByPreviousSchedule(hari: number, jam: number): boolean {
    return props.jadwals.some(
        (j) => j.hari === hari && j.jam_mulai_ke < jam && j.jam_selesai_ke > jam
    );
}

// Evaluasi bentrok real-time jika ada kartu yang sedang diangkat atau dipilih
function getEvaluation(hari: number, jam: number) {
    if (!activeCard.value) return null;
    return evaluateSlot(
        hari,
        jam,
        activeCard.value,
        props.allSchedules,
        props.jamKerjaList
    );
}

// Handlers Drag & Drop
function handleCardDragStart(event: DragEvent, jadwal: JadwalItem) {
    activeCard.value = jadwal;
    isDragging.value = true;
}

function handleCardDragEnd() {
    activeCard.value = null;
    isDragging.value = false;
    dragOverKey.value = null;
}

function handleDragOver(event: DragEvent, hari: number, jam: number) {
    event.preventDefault();
    dragOverKey.value = `${hari}-${jam}`;
}

function handleDragLeave(hari: number, jam: number) {
    if (dragOverKey.value === `${hari}-${jam}`) {
        dragOverKey.value = null;
    }
}

function handleDrop(event: DragEvent, hari: number, jam: number) {
    event.preventDefault();
    if (!activeCard.value) return;

    const evaluation = evaluateSlot(
        hari,
        jam,
        activeCard.value,
        props.allSchedules,
        props.jamKerjaList
    );

    if (evaluation.isValid) {
        const duration = Math.max(1, activeCard.value.jam_selesai_ke - activeCard.value.jam_mulai_ke);
        emit('move-slot', {
            id: activeCard.value.id,
            hari,
            jam_mulai_ke: jam,
            jam_selesai_ke: jam + duration,
        });
    }

    activeCard.value = null;
    isDragging.value = false;
    dragOverKey.value = null;
}

// Handlers Tap-to-Move (untuk tablet/mobile dan aksesibilitas)
function handleCardSelect(jadwal: JadwalItem) {
    if (!props.canManage) return;
    if (activeCard.value?.id === jadwal.id) {
        activeCard.value = null; // deselect
    } else {
        activeCard.value = jadwal;
    }
}

function handleCellClick(hari: number, jam: number) {
    if (!props.canManage) return;

    // Jika sedang memilih kartu, coba pindahkan ke sel ini
    if (activeCard.value) {
        const evaluation = evaluateSlot(
            hari,
            jam,
            activeCard.value,
            props.allSchedules,
            props.jamKerjaList
        );

        if (evaluation.isValid) {
            const duration = Math.max(1, activeCard.value.jam_selesai_ke - activeCard.value.jam_mulai_ke);
            emit('move-slot', {
                id: activeCard.value.id,
                hari,
                jam_mulai_ke: jam,
                jam_selesai_ke: jam + duration,
            });
            activeCard.value = null;
        }
        return;
    }

    // Jika sel kosong dan tidak ada kartu yang dipilih, buka modal tambah slot
    if (!isHariLibur(hari) && !isOutOfOperationalHours(hari, jam) && !isCoveredByPreviousSchedule(hari, jam)) {
        emit('add-slot', hari, jam);
    }
}
</script>

<template>
    <div class="relative w-full overflow-x-auto rounded-lg border bg-background shadow-xs">
        <!-- Banner petunjuk pemindahan saat kartu aktif/terpilih (Aksesibilitas / Tablet) -->
        <div
            v-if="activeCard"
            class="sticky top-0 z-30 flex items-center justify-between border-b bg-primary/10 px-4 py-2 text-xs font-medium text-primary backdrop-blur-md"
        >
            <div class="flex items-center gap-2">
                <span class="inline-block h-2 w-2 rounded-full bg-primary animate-pulse" />
                <span>
                    Memindahkan slot: <strong>{{ activeCard.mata_pelajaran?.nama }}</strong> ({{ activeCard.jam_selesai_ke - activeCard.jam_mulai_ke }} JP).
                    Pilih atau jatuhkan ke slot hijau yang tersedia.
                </span>
            </div>
            <button
                @click="activeCard = null"
                class="rounded px-2 py-0.5 text-[11px] font-semibold hover:bg-primary/20 transition-colors"
            >
                Batal
            </button>
        </div>

        <div class="min-w-[900px]">
            <!-- Header Grid: Kolom Jam + 6 Hari (Senin..Sabtu) -->
            <div class="grid grid-cols-[80px_repeat(6,1fr)] border-b bg-muted/40 text-center font-medium text-xs">
                <div class="p-3 text-muted-foreground font-semibold flex items-center justify-center">
                    Jam Ke
                </div>
                <div
                    v-for="hari in days"
                    :key="`header-${hari}`"
                    class="p-3 border-l flex flex-col items-center justify-center gap-0.5"
                    :class="[isHariLibur(hari) ? 'bg-muted/60 text-muted-foreground' : 'text-foreground']"
                >
                    <span class="font-bold text-sm">{{ HARI_NAMES[hari] }}</span>
                    <span v-if="isHariLibur(hari)" class="text-[10px] text-muted-foreground font-normal">
                        (Libur)
                    </span>
                    <span v-else-if="getJamKerjaHari(hari)" class="text-[10px] text-muted-foreground font-mono">
                        {{ getJamKerjaHari(hari)?.jumlah_jam_pelajaran }} JP Operasional
                    </span>
                </div>
            </div>

            <!-- Baris Grid: Jam 1 s.d. maxJamOperasional -->
            <div
                v-for="jam in maxJamOperasional"
                :key="`row-${jam}`"
                class="grid grid-cols-[80px_repeat(6,1fr)] border-b border-border/50 min-h-[72px]"
            >
                <!-- Kolom Label Jam Ke-N -->
                <div class="flex flex-col items-center justify-center border-r bg-muted/20 p-2 font-mono text-xs text-muted-foreground">
                    <span class="font-bold text-foreground">Ke-{{ jam }}</span>
                    <span class="text-[10px] opacity-70">Slot {{ jam }}</span>
                </div>

                <!-- Sel Tiap Hari -->
                <div
                    v-for="hari in days"
                    :key="`cell-${hari}-${jam}`"
                    class="relative border-r border-border/40 p-1.5 transition-colors"
                    :class="[
                        isHariLibur(hari)
                            ? 'bg-muted/40 cursor-not-allowed'
                            : isOutOfOperationalHours(hari, jam)
                              ? 'bg-muted/20 cursor-not-allowed'
                              : isCoveredByPreviousSchedule(hari, jam)
                                ? 'bg-card/40'
                                : canManage
                                  ? 'hover:bg-accent/40 cursor-pointer'
                                  : 'bg-card',
                        dragOverKey === `${hari}-${jam}` && activeCard ? 'ring-2 ring-primary ring-inset' : ''
                    ]"
                    @dragover="handleDragOver($event, hari, jam)"
                    @dragleave="handleDragLeave(hari, jam)"
                    @drop="handleDrop($event, hari, jam)"
                    @click="handleCellClick(hari, jam)"
                >
                    <!-- ConflictOverlay saat kartu sedang aktif / di-drag -->
                    <ConflictOverlay
                        v-if="activeCard && !isCoveredByPreviousSchedule(hari, jam)"
                        :evaluation="getEvaluation(hari, jam)!"
                    />

                    <!-- Status Hari Libur -->
                    <div
                        v-if="isHariLibur(hari) && jam === 1"
                        class="absolute inset-0 flex flex-col items-center justify-center p-2 text-center text-muted-foreground/60 select-none"
                    >
                        <CalendarOff class="h-5 w-5 mb-1 opacity-50" />
                        <span class="text-xs font-semibold">Hari Libur</span>
                    </div>

                    <!-- Status Di Luar Jam Operasional -->
                    <div
                        v-else-if="!isHariLibur(hari) && isOutOfOperationalHours(hari, jam)"
                        class="flex items-center justify-center h-full text-muted-foreground/40 text-[10px] select-none"
                    >
                        <span class="inline-flex items-center gap-1 font-mono">
                            <Ban class="h-3 w-3" /> Non-aktif
                        </span>
                    </div>

                    <!-- Kartu Jadwal Pelajaran yang Dimulai pada Slot Ini -->
                    <template v-else-if="getScheduleAt(hari, jam)">
                        <DraggableCard
                            :jadwal="getScheduleAt(hari, jam)!"
                            :view-mode="viewMode"
                            :can-manage="canManage"
                            :is-selected="activeCard?.id === getScheduleAt(hari, jam)!.id"
                            :is-dragging="isDragging && activeCard?.id === getScheduleAt(hari, jam)!.id"
                            @dragstart="handleCardDragStart"
                            @dragend="handleCardDragEnd"
                            @select="handleCardSelect"
                            @edit="emit('edit-slot', $event)"
                            @delete="emit('delete-slot', $event)"
                        />
                    </template>

                    <!-- Slot Kosong Tersedia (Tombol Tambah saat hover jika belum ada kartu aktif) -->
                    <div
                        v-else-if="!isCoveredByPreviousSchedule(hari, jam) && !activeCard && canManage"
                        class="group/slot flex h-full min-h-[56px] w-full items-center justify-center rounded border border-dashed border-transparent transition-all group-hover/slot:border-border hover:bg-muted/30"
                    >
                        <Plus class="h-4 w-4 text-muted-foreground/20 group-hover/slot:text-muted-foreground/80 transition-colors" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
