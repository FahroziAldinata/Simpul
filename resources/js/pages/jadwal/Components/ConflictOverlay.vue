<script setup lang="ts">
import { AlertCircle, CheckCircle2, Ban } from '@lucide/vue';
import type { DropEvaluation } from '../types';

defineProps<{
    evaluation: DropEvaluation;
}>();
</script>

<template>
    <div
        class="absolute inset-0 z-20 flex flex-col items-center justify-center rounded-lg p-2 text-center transition-all duration-150 backdrop-blur-[2px]"
        :class="[
            evaluation.isValid
                ? 'bg-emerald-500/20 text-emerald-900 border-2 border-emerald-500 border-dashed dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-400'
                : evaluation.type === 'hari_libur' || evaluation.type === 'jam_operasional'
                  ? 'bg-muted/70 text-muted-foreground border-2 border-muted-foreground/30 border-dashed cursor-not-allowed'
                  : 'bg-rose-500/20 text-rose-900 border-2 border-rose-500 border-dashed dark:bg-rose-950/50 dark:text-rose-200 dark:border-rose-400 cursor-not-allowed'
        ]"
    >
        <!-- Indikator Ikon + Teks (Aturan C1 Aksesibilitas: bukan warna saja) -->
        <template v-if="evaluation.isValid">
            <div class="flex items-center gap-1.5 font-semibold text-xs text-emerald-800 dark:text-emerald-300">
                <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <span>Slot Tersedia</span>
            </div>
            <span class="text-[10px] text-emerald-700/80 dark:text-emerald-300/80 mt-0.5">Lepas untuk memindahkan</span>
        </template>

        <template v-else-if="evaluation.type === 'hari_libur' || evaluation.type === 'jam_operasional'">
            <div class="flex items-center gap-1 font-medium text-[11px]">
                <Ban class="h-3.5 w-3.5 shrink-0 opacity-70" />
                <span>Terkunci</span>
            </div>
            <span class="text-[10px] line-clamp-2 mt-0.5 opacity-80">{{ evaluation.message }}</span>
        </template>

        <template v-else>
            <div class="flex items-center gap-1.5 font-bold text-xs text-rose-800 dark:text-rose-300">
                <AlertCircle class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400 animate-pulse" />
                <span>Bentrok!</span>
            </div>
            <span class="text-[10px] text-rose-800/90 dark:text-rose-200/90 line-clamp-2 mt-0.5 font-medium leading-tight">
                {{ evaluation.message }}
            </span>
        </template>
    </div>
</template>
