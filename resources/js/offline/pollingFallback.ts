/**
 * T-10.07 — Polling fallback untuk Background Sync
 *
 * Background Sync API tidak didukung Safari iOS (NFR-12 minta Safari iOS 16+).
 * Fallback: polling interval saat online.
 *
 * Logika:
 *  1. Saat device online (event 'online') → flush antrean segera
 *  2. Poll setiap INTERVAL_MS saat online (memastikan sync terjadi meski SW tidak aktif)
 *  3. Background Sync (di sw.ts) adalah mekanisme primer — polling adalah fallback
 *
 * Diinisialisasi satu kali dari app.ts.
 */

import { useOfflineQueue } from './useOfflineQueue';

const INTERVAL_MS = 30_000; // 30 detik polling interval

let _intervalId: ReturnType<typeof setInterval> | null = null;
let _sudahInit = false;

export function initPollingFallback(): void {
    if (_sudahInit) return;
    _sudahInit = true;

    const { flush } = useOfflineQueue();

    // Flush segera saat online kembali (event 'online')
    window.addEventListener('online', () => {
        flush();
    });

    // Polling periodik — hanya kirim jika online
    _intervalId = setInterval(() => {
        if (navigator.onLine) {
            flush();
        }
    }, INTERVAL_MS);
}

export function destroyPollingFallback(): void {
    if (_intervalId !== null) {
        clearInterval(_intervalId);
        _intervalId = null;
    }
    _sudahInit = false;
}
