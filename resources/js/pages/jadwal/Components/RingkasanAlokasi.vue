<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown, ChevronRight, CheckCircle2, AlertTriangle, AlertCircle, BookOpen } from '@lucide/vue';
import type { RombelRingkasan } from '../types';
import { Badge } from '@/components/ui/badge';

defineProps<{
    ringkasan: RombelRingkasan[];
    selectedRombelId?: string;
}>();

const emit = defineEmits<{
    (e: 'select-rombel', rombelId: string): void;
}>();

const expandedRombels = ref<Record<string, boolean>>({});

function toggleExpand(rombelId: string) {
    expandedRombels.value[rombelId] = !expandedRombels.value[rombelId];
}
</script>

<template>
    <div class="rounded-lg border bg-card p-4 shadow-xs space-y-3">
        <div class="flex items-center justify-between border-b pb-3">
            <div>
                <h3 class="font-bold text-sm text-foreground flex items-center gap-1.5">
                    <BookOpen class="h-4 w-4 text-primary" />
                    <span>Kelengkapan Alokasi Kurikulum (Kendala H4)</span>
                </h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Memantau pemenuhan jam pelajaran tiap rombel terhadap alokasi mingguan.
                </p>
            </div>
        </div>

        <div class="space-y-2.5">
            <div
                v-for="r in ringkasan"
                :key="r.rombel_id"
                class="rounded-md border p-3 transition-colors"
                :class="[
                    selectedRombelId === r.rombel_id ? 'border-primary/60 bg-primary/5' : 'bg-background hover:border-border/80'
                ]"
            >
                <div class="flex items-center justify-between gap-3 cursor-pointer" @click="toggleExpand(r.rombel_id)">
                    <div class="flex items-center gap-2 min-w-0">
                        <button
                            type="button"
                            class="p-0.5 text-muted-foreground hover:text-foreground transition-colors"
                            @click.stop="toggleExpand(r.rombel_id)"
                        >
                            <ChevronDown v-if="expandedRombels[r.rombel_id]" class="h-4 w-4" />
                            <ChevronRight v-else class="h-4 w-4" />
                        </button>
                        <span
                            class="font-semibold text-xs text-foreground truncate hover:text-primary hover:underline"
                            title="Tampilkan jadwal rombel ini"
                            @click.stop="emit('select-rombel', r.rombel_id)"
                        >
                            {{ r.nama_rombel }}
                        </span>
                        <span class="text-[11px] text-muted-foreground">
                            (Tingkat {{ r.tingkat }})
                        </span>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <!-- Progress & Angka Jam -->
                        <div class="text-right">
                            <span class="font-mono text-xs font-bold text-foreground">
                                {{ r.total_terjadwal }} / {{ r.total_alokasi }} JP
                            </span>
                            <span class="text-[10px] text-muted-foreground block">
                                ({{ r.persentase }}%)
                            </span>
                        </div>

                        <!-- Status Badge -->
                        <Badge
                            v-if="r.is_lengkap"
                            variant="secondary"
                            class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 gap-1 text-[11px]"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600" />
                            <span>Lengkap</span>
                        </Badge>
                        <Badge
                            v-else-if="r.total_terjadwal < r.total_alokasi"
                            variant="secondary"
                            class="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 gap-1 text-[11px]"
                        >
                            <AlertTriangle class="h-3.5 w-3.5 text-amber-600" />
                            <span>Kurang {{ r.total_alokasi - r.total_terjadwal }} JP</span>
                        </Badge>
                        <Badge
                            v-else
                            variant="destructive"
                            class="gap-1 text-[11px]"
                        >
                            <AlertCircle class="h-3.5 w-3.5" />
                            <span>Kelebihan {{ r.total_terjadwal - r.total_alokasi }} JP</span>
                        </Badge>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full bg-muted/60 h-1.5 rounded-full overflow-hidden mt-2">
                    <div
                        class="h-full transition-all duration-300 rounded-full"
                        :class="[
                            r.is_lengkap
                                ? 'bg-emerald-500'
                                : r.total_terjadwal < r.total_alokasi
                                  ? 'bg-amber-500'
                                  : 'bg-rose-500'
                        ]"
                        :style="{ width: `${Math.min(100, r.persentase)}%` }"
                    />
                </div>

                <!-- Tabel Detail Alokasi per Mapel jika Di-expand -->
                <div v-if="expandedRombels[r.rombel_id]" class="mt-3 border-t pt-2 space-y-1 text-xs">
                    <div
                        v-for="m in r.detail_mapel"
                        :key="m.mata_pelajaran_id"
                        class="flex items-center justify-between py-1 px-1.5 rounded hover:bg-muted/40 transition-colors"
                    >
                        <span class="text-foreground/90 truncate">{{ m.nama_mapel }}</span>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-mono text-[11px] text-muted-foreground">
                                {{ m.terjadwal }} / {{ m.alokasi }} JP
                            </span>
                            <span
                                v-if="m.status === 'tepat'"
                                class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                Pas
                            </span>
                            <span
                                v-else-if="m.status === 'kurang'"
                                class="text-[10px] font-semibold text-amber-600 dark:text-amber-400"
                            >
                                -{{ m.alokasi - m.terjadwal }}
                            </span>
                            <span
                                v-else
                                class="text-[10px] font-semibold text-rose-600 dark:text-rose-400"
                            >
                                +{{ m.terjadwal - m.alokasi }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
