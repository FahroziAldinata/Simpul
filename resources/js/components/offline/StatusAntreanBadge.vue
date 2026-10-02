<script setup lang="ts">
/**
 * T-10.04 — StatusAntreanBadge.vue
 *
 * Badge status item antrean offline individual.
 * US-21 AC4: umpan balik dengan IKON + warna, tidak hanya warna saja.
 * PRD aturan desain C1 (konsisten sejak Minggu 3): status selalu berikan ikon.
 *
 * Status visual:
 *   synced         → hijau  + ikon CheckCircle2
 *   pending/syncing → kuning + ikon Clock (animasi pulse jika syncing)
 *   failed         → merah  + ikon AlertCircle + tombol retry manual
 *   conflict_final → oranye + ikon XCircle (dibatalkan aturan bisnis, tidak retry)
 */
import { AlertCircle, CheckCircle2, Clock, Loader2, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import type { AntreanAbsensi } from '@/offline/db';
import { useOfflineQueue } from '@/offline/useOfflineQueue';

const props = defineProps<{
    item: AntreanAbsensi;
}>();

const { retry } = useOfflineQueue();

const statusConfig = {
    synced: {
        label: 'Tersinkron',
        warna: 'text-emerald-600',
        bg: 'bg-emerald-50 border-emerald-200',
        ikon: CheckCircle2,
        animate: false,
    },
    pending: {
        label: 'Menunggu sinkron',
        warna: 'text-amber-600',
        bg: 'bg-amber-50 border-amber-200',
        ikon: Clock,
        animate: false,
    },
    syncing: {
        label: 'Menyinkron...',
        warna: 'text-amber-600',
        bg: 'bg-amber-50 border-amber-200',
        ikon: Loader2,
        animate: true,
    },
    failed: {
        label: 'Gagal sinkron',
        warna: 'text-red-600',
        bg: 'bg-red-50 border-red-200',
        ikon: AlertCircle,
        animate: false,
    },
    conflict_final: {
        label: 'Dibatalkan',
        warna: 'text-orange-600',
        bg: 'bg-orange-50 border-orange-200',
        ikon: XCircle,
        animate: false,
    },
} as const;

const config = computed(() => statusConfig[props.item.status]);
const waktuScan = computed(() =>
    new Date(props.item.captured_at).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);
</script>

<template>
    <div
        :class="[
            'flex items-start gap-2 rounded-lg border px-3 py-2 text-sm transition-all',
            config.bg,
        ]"
        :aria-label="`Status absensi ${item.jenis}: ${config.label}`"
    >
        <!-- Ikon status (selalu ada — US-21 AC4) -->
        <component
            :is="config.ikon"
            :class="[
                'mt-0.5 h-4 w-4 shrink-0',
                config.warna,
                config.animate && 'animate-spin',
            ]"
            aria-hidden="true"
        />

        <div class="min-w-0 flex-1">
            <!-- Label status + jenis -->
            <div :class="['font-medium', config.warna]">
                {{ config.label }}
            </div>
            <div class="text-xs text-slate-500">
                Absen {{ item.jenis }} · {{ waktuScan }}
            </div>

            <!-- Pesan error detail (jika ada) -->
            <div
                v-if="item.pesan_error"
                class="mt-1 text-xs text-slate-600"
            >
                {{ item.pesan_error }}
            </div>

            <!-- Tombol retry manual (hanya untuk status failed, bukan conflict_final) -->
            <button
                v-if="item.status === 'failed'"
                class="mt-1.5 rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                :id="`retry-${item.client_uuid}`"
                @click="retry(item.client_uuid)"
            >
                Coba lagi
            </button>
        </div>
    </div>
</template>
