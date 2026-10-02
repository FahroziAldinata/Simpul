<script setup lang="ts">
/**
 * T-10.04 — AntreanOfflinePanel.vue
 *
 * Panel/dropdown yang menampilkan semua item dalam antrean offline
 * dengan status masing-masing (ikon + warna sesuai US-21 AC4).
 * Ditampilkan via badge counter di topbar.
 */
import { Wifi } from '@lucide/vue';
import { computed } from 'vue';
import StatusAntreanBadge from './StatusAntreanBadge.vue';
import { useOfflineQueue } from '@/offline/useOfflineQueue';
import { useOnlineStatus } from '@/offline/useOnlineStatus';

const { antrean, jumlahPending, flush, hapusSynced } = useOfflineQueue();
const { isOnline } = useOnlineStatus();

const adaAntrean = computed(() => antrean.value.length > 0);
const antreanAktif = computed(() =>
    antrean.value.filter((i) => i.status !== 'synced'),
);
const antreanSelesai = computed(() =>
    antrean.value.filter((i) => i.status === 'synced'),
);
</script>

<template>
    <div
        class="flex w-72 flex-col gap-3 p-4"
        role="region"
        aria-label="Antrean absensi offline"
    >
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800">
                Antrean Absensi
            </h3>
            <span
                v-if="jumlahPending > 0"
                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                aria-label="`${jumlahPending} item menunggu sinkron`"
            >
                {{ jumlahPending }} pending
            </span>
        </div>

        <!-- Kosong -->
        <div
            v-if="!adaAntrean"
            class="py-4 text-center text-sm text-slate-400"
        >
            Tidak ada absensi tertunda
        </div>

        <!-- Item aktif (pending/syncing/failed/conflict_final) -->
        <div v-if="antreanAktif.length > 0" class="flex flex-col gap-2">
            <StatusAntreanBadge
                v-for="item in antreanAktif"
                :key="item.client_uuid"
                :item="item"
            />
        </div>

        <!-- Tombol sinkron manual (jika online) -->
        <button
            v-if="isOnline && jumlahPending > 0"
            class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
            :disabled="!isOnline"
            id="btn-sinkron-manual"
            @click="flush()"
        >
            <Wifi class="mr-1.5 inline h-4 w-4" aria-hidden="true" />
            Sinkron sekarang
        </button>

        <!-- Item sudah synced (collapsible) -->
        <details v-if="antreanSelesai.length > 0" class="group">
            <summary
                class="cursor-pointer text-xs text-slate-400 hover:text-slate-600"
            >
                {{ antreanSelesai.length }} tersinkron
            </summary>
            <div class="mt-2 flex flex-col gap-1">
                <StatusAntreanBadge
                    v-for="item in antreanSelesai"
                    :key="item.client_uuid"
                    :item="item"
                />
            </div>
            <button
                class="mt-2 text-xs text-slate-400 underline hover:text-slate-600"
                id="btn-hapus-synced"
                @click="hapusSynced()"
            >
                Hapus riwayat
            </button>
        </details>
    </div>
</template>
