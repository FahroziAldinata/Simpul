<script setup lang="ts">
import { computed } from 'vue';
import { GripVertical, Edit2, Trash2, MapPin, User, BookOpen } from '@lucide/vue';
import type { JadwalItem, ViewMode } from '../types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    jadwal: JadwalItem;
    viewMode: ViewMode;
    canManage: boolean;
    isSelected?: boolean;
    isDragging?: boolean;
}>();

const emit = defineEmits<{
    (e: 'dragstart', event: DragEvent, jadwal: JadwalItem): void;
    (e: 'dragend', event: DragEvent): void;
    (e: 'select', jadwal: JadwalItem): void;
    (e: 'edit', jadwal: JadwalItem): void;
    (e: 'delete', jadwal: JadwalItem): void;
}>();

const durasiJp = computed(() => {
    return Math.max(1, props.jadwal.jam_selesai_ke - props.jadwal.jam_mulai_ke);
});

function handleDragStart(event: DragEvent) {
    if (!props.canManage) return;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', props.jadwal.id);
    }
    emit('dragstart', event, props.jadwal);
}

function handleDragEnd(event: DragEvent) {
    emit('dragend', event);
}

function handleClick() {
    emit('select', props.jadwal);
}
</script>

<template>
    <div
        :draggable="canManage"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
        @click="handleClick"
        class="group relative flex flex-col justify-between rounded-md border p-2.5 shadow-xs transition-all select-none"
        :class="[
            canManage ? 'cursor-grab active:cursor-grabbing hover:shadow-md' : 'cursor-default',
            isSelected
                ? 'ring-2 ring-primary border-primary bg-primary/5 dark:bg-primary/10 shadow-md'
                : 'border-border/80 bg-card hover:border-primary/50 dark:bg-card/90',
            isDragging ? 'opacity-40 scale-95' : 'opacity-100',
        ]"
    >
        <!-- Header: Drag handle + Mapel + Durasi -->
        <div class="flex items-start justify-between gap-1.5">
            <div class="flex items-center gap-1 min-w-0">
                <GripVertical
                    v-if="canManage"
                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground/50 group-hover:text-foreground transition-colors"
                />
                <h4 class="font-semibold text-xs text-foreground truncate leading-tight">
                    {{ jadwal.mata_pelajaran?.nama ?? 'Mata Pelajaran' }}
                </h4>
            </div>
            <Badge variant="secondary" class="h-4.5 px-1 text-[10px] shrink-0 font-medium tracking-tight">
                {{ durasiJp }} JP
            </Badge>
        </div>

        <!-- Body: Tergantung Sudut Pandang ViewMode (T-11.09) -->
        <div class="my-1.5 space-y-1 text-[11px] text-muted-foreground">
            <!-- Jika view per rombel, tampilkan guru -->
            <div v-if="viewMode !== 'guru'" class="flex items-center gap-1.5 truncate">
                <User class="h-3 w-3 shrink-0 opacity-70" />
                <span class="truncate">{{ jadwal.guru?.nama ?? 'Belum ditentukan' }}</span>
            </div>

            <!-- Jika view per guru, tampilkan rombel -->
            <div v-if="viewMode === 'guru'" class="flex items-center gap-1.5 font-medium text-foreground truncate">
                <BookOpen class="h-3 w-3 shrink-0 text-primary opacity-80" />
                <span class="truncate">{{ jadwal.rombel?.nama ?? 'Rombel' }}</span>
            </div>

            <!-- Tampilkan ruang jika ada -->
            <div v-if="viewMode !== 'ruang'" class="flex items-center gap-1.5 truncate">
                <MapPin class="h-3 w-3 shrink-0 opacity-70" />
                <span v-if="jadwal.ruang" class="truncate">{{ jadwal.ruang.nama }}</span>
                <span v-else class="italic text-[10px] text-muted-foreground/70">Tanpa Ruang Khusus</span>
            </div>

            <!-- Jika view per ruang, tampilkan rombel + guru -->
            <div v-if="viewMode === 'ruang'" class="flex items-center gap-1.5 truncate">
                <span class="font-medium text-foreground">{{ jadwal.rombel?.nama }}</span>
                <span>•</span>
                <span class="truncate">{{ jadwal.guru?.nama }}</span>
            </div>
        </div>

        <!-- Footer: Jam & Quick Action Buttons -->
        <div class="flex items-center justify-between border-t border-border/40 pt-1 text-[10px] text-muted-foreground">
            <span class="font-mono text-[10px]">
                Jam {{ jadwal.jam_mulai_ke }}–{{ jadwal.jam_selesai_ke - 1 }}
            </span>

            <div v-if="canManage" class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-5 w-5 text-muted-foreground hover:text-foreground"
                    title="Ubah Jadwal"
                    @click.stop="emit('edit', jadwal)"
                >
                    <Edit2 class="h-2.5 w-2.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-5 w-5 text-destructive/80 hover:text-destructive hover:bg-destructive/10"
                    title="Hapus Jadwal"
                    @click.stop="emit('delete', jadwal)"
                >
                    <Trash2 class="h-2.5 w-2.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
