/**
 * T-10.07 — Service Worker custom untuk SIMPUL
 *
 * Workbox precaching di-inject oleh vite-plugin-pwa (VitePWA) pada build time.
 * File ini menambahkan handler Background Sync di atas precaching Workbox.
 *
 * Alur Background Sync (T-10.07):
 *  1. Klien memanggil ServiceWorkerRegistration.sync.register('simpul-absensi-sync')
 *     setelah enqueue item
 *  2. Browser memanggil sync event di SW saat online
 *  3. SW mengirim pesan 'TRIGGER_FLUSH' ke semua client aktif
 *  4. Client (Vue composable useOfflineQueue) menerima pesan dan memanggil flush()
 *
 * Mengapa bukan fetch langsung dari SW?
 *  Fetch dari SW tidak bisa mengakses cookie sesi browser dengan andal di semua browser.
 *  Delegasi ke client memastikan cookie Fortify selalu tersedia.
 *
 * Catatan untuk vite-plugin-pwa: set strategies: 'injectManifest', src: 'sw.ts'
 * di vite.config.ts agar file ini dipakai sebagai SW custom (bukan generateSW).
 */

/// <reference lib="WebWorker" />
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

declare const self: ServiceWorkerGlobalScope;

// Injected oleh VitePWA pada build time
// eslint-disable-next-line @typescript-eslint/no-explicit-any
precacheAndRoute((self as any).__WB_MANIFEST ?? []);
cleanupOutdatedCaches();

self.addEventListener('install', () => {
    // Skip waiting — aktifkan SW baru segera tanpa menunggu tab lama ditutup
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Klaim semua client — memastikan SW baru langsung mengelola halaman terbuka
    event.waitUntil(self.clients.claim());
});

/**
 * Background Sync event handler (T-10.07)
 * Safari iOS tidak mendukung Background Sync — di sana polling fallback (pollingFallback.ts)
 * yang akan menangani.
 */
self.addEventListener('sync', (event) => {
    // @ts-expect-error: SyncEvent type is not available in standard TypeScript lib
    if (event.tag === 'simpul-absensi-sync') {
        // @ts-expect-error: waitUntil method on SyncEvent
        event.waitUntil(triggerFlushKeClient());
    }
});

/**
 * Kirim pesan ke semua window client yang masih terbuka.
 * Client akan menerima pesan dan memanggil useOfflineQueue().flush().
 */
async function triggerFlushKeClient(): Promise<void> {
    const clients = await self.clients.matchAll({ type: 'window' });
    for (const client of clients) {
        client.postMessage({ type: 'TRIGGER_FLUSH' });
    }
}
