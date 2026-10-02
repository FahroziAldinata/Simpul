<script setup lang="ts">
/**
 * T-10.10 — TopbarOfflineIndicator.vue
 * PRD aturan desain C7: indikator offline permanen di topbar saat koneksi hilang.
 *
 * Muncul di atas konten topbar saat navigator.onLine = false.
 * Tidak bisa di-dismiss pengguna — hanya hilang saat koneksi kembali.
 */
import { WifiOff } from '@lucide/vue';
import { useOnlineStatus } from '@/offline/useOnlineStatus';

const { isOnline } = useOnlineStatus();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-300"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div
            v-if="!isOnline"
            class="flex items-center justify-center gap-2 bg-slate-800 px-4 py-1.5 text-sm font-medium text-white"
            role="status"
            aria-live="polite"
            aria-label="Tidak ada koneksi internet"
            id="offline-indicator-topbar"
        >
            <WifiOff class="h-4 w-4 shrink-0" aria-hidden="true" />
            <span>Offline — absensi tersimpan lokal dan akan sinkron otomatis</span>
        </div>
    </Transition>
</template>
