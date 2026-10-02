/**
 * T-10.09 (bagian) — Penyimpanan clock offset di localStorage
 *
 * Setiap request ke server, header `Date` dari response HTTP dibaca dan
 * dipakai untuk menghitung selisih waktu server vs waktu perangkat.
 * Offset ini disimpan di localStorage dan dipakai saat enqueue item offline.
 *
 * Format: clock_offset = server_time_ms - client_time_ms
 * - Positif: jam perangkat lebih lambat dari server
 * - Negatif: jam perangkat lebih cepat dari server
 *
 * Diperbarui setiap kali ada response dengan header Date yang valid.
 * Tidak memerlukan endpoint khusus — memanfaatkan setiap response HTTP.
 */

const STORAGE_KEY = 'simpul_clock_offset';

export function simpanClockOffset(serverDateHeader: string): void {
    const serverTime = new Date(serverDateHeader).getTime();
    if (isNaN(serverTime)) return;
    const clientTime = Date.now();
    const offset = serverTime - clientTime;
    localStorage.setItem(STORAGE_KEY, String(offset));
}

export function ambilClockOffset(): number {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored ? parseInt(stored, 10) : 0;
}

/**
 * Interseptor Inertia: update clock offset dari setiap response.
 * Dipanggil satu kali dari app.ts saat inisialisasi.
 */
export function initClockSync(): void {
    // Gunakan Fetch API global — override responsenya agar kita bisa baca header Date
    // tanpa menginterupsi alur normal Inertia.
    const originalFetch = window.fetch.bind(window);
    window.fetch = async function (...args) {
        const response = await originalFetch(...args);
        // Baca header Date dari semua response ke origin yang sama
        try {
            const dateHeader = response.headers.get('Date');
            if (dateHeader) {
                simpanClockOffset(dateHeader);
            }
        } catch {
            // Tidak kritis jika gagal — offset lama tetap dipakai
        }
        return response;
    };
}
