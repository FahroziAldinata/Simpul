/**
 * T-10.10 — useOnlineStatus composable
 *
 * Deteksi status koneksi jaringan secara reaktif.
 * Dipakai oleh:
 *   - TopbarOfflineIndicator (T-10.10): tampilkan banner offline permanen
 *   - AntreanOfflinePanel (T-10.04): aktifkan/nonaktifkan tombol sinkron manual
 *   - initPollingFallback (T-10.07): trigger flush saat online kembali
 *
 * Menggunakan navigator.onLine + event online/offline.
 * navigator.onLine tidak 100% akurat di semua kasus (bisa true tapi koneksi slow),
 * tapi cukup untuk kebutuhan UX — falsy positif ditangani oleh error handling flush().
 */

import { onMounted, onUnmounted, ref } from 'vue';

const _isOnline = ref(navigator.onLine);

// State global tunggal — tidak perlu listener duplikat per komponen
let _listenerCount = 0;

function onOnline() {
    _isOnline.value = true;
}
function onOffline() {
    _isOnline.value = false;
}

export function useOnlineStatus() {
    onMounted(() => {
        if (_listenerCount === 0) {
            window.addEventListener('online', onOnline);
            window.addEventListener('offline', onOffline);
        }
        _listenerCount++;
    });

    onUnmounted(() => {
        _listenerCount--;
        if (_listenerCount === 0) {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        }
    });

    return {
        isOnline: _isOnline,
    };
}
