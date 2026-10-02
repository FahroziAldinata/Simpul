/**
 * T-10.03 — Composable useOfflineQueue
 *
 * Mengelola antrean absensi offline: enqueue, flush, retry, status reaktif.
 *
 * Alur (sesuai PRD 6.2):
 *  1. enqueue() → simpan ke IndexedDB, tampilkan UI optimistis
 *  2. flush() → kirim array pending ke POST /api/absensi/sync (kredensial cookie)
 *  3. Sukses → tandai synced, hapus dari antrean setelah 5 menit (bersih)
 *  4. Gagal jaringan → exponential backoff (T-10.08)
 *  5. Gagal bisnis final (409 kode izin_disetujui) → status conflict_final, tidak retry
 *
 * Keputusan #2: kegagalan bisnis final ≠ kegagalan jaringan, tidak masuk backoff loop.
 * Keputusan #3: clock_offset diambil dari localStorage (diperbarui via initClockSync).
 */

import { computed, readonly, ref } from 'vue';
import { ambilClockOffset } from './clockSync';
import {
    buatClientUuid,
    db,
    type AntreanAbsensi,
    type JenisAbsensi,
    type StatusAntrean,
} from './db';

// Jeda backoff: 1s, 2s, 4s, 8s, 16s (max 5 percobaan, lalu status failed)
const BACKOFF_DELAYS_MS = [1000, 2000, 4000, 8000, 16000];
const MAX_PERCOBAAN = 5;

// Kode error bisnis yang TIDAK boleh di-retry otomatis (keputusan #2)
const KODE_ERROR_FINAL = new Set(['izin_disetujui']);

// State reaktif global (satu instance per PWA)
const _antrean = ref<AntreanAbsensi[]>([]);
const _sedangFlush = ref(false);

/** Muat ulang semua item dari IndexedDB ke state reaktif */
async function muatAntrean(): Promise<void> {
    const semua = await db.antrean_absensi
        .orderBy('dibuat_pada')
        .toArray();
    _antrean.value = semua;
}

if (typeof window !== 'undefined' && typeof indexedDB !== 'undefined') {
    void muatAntrean();
}

export function useOfflineQueue() {
    // -------------------------------------------------------------------------
    // Computed / readonly state untuk UI
    // -------------------------------------------------------------------------
    const antrean = readonly(_antrean);

    const jumlahPending = computed(
        () =>
            _antrean.value.filter(
                (i) => i.status === 'pending' || i.status === 'syncing',
            ).length,
    );

    const adaGagal = computed(() =>
        _antrean.value.some((i) => i.status === 'failed'),
    );

    const adaKonflikFinal = computed(() =>
        _antrean.value.some((i) => i.status === 'conflict_final'),
    );

    // -------------------------------------------------------------------------
    // enqueue — T-10.03
    // Membuat item baru dan menyimpannya ke IndexedDB
    // -------------------------------------------------------------------------
    async function enqueue(payload: {
        token_qr: string;
        jenis: JenisAbsensi;
        lat: number | null;
        lng: number | null;
    }): Promise<string> {
        const client_uuid = buatClientUuid();
        const clock_offset = ambilClockOffset();

        const item: AntreanAbsensi = {
            client_uuid,
            token_qr: payload.token_qr,
            jenis: payload.jenis,
            captured_at: Date.now(),
            clock_offset,
            lat: payload.lat,
            lng: payload.lng,
            status: 'pending',
            percobaan: 0,
            dibuat_pada: Date.now(),
            dicoba_pada: null,
            pesan_error: null,
            kode_error: null,
        };

        await db.antrean_absensi.add(item);
        await muatAntrean();

        // Daftarkan Background Sync jika tersedia (T-10.07)
        // Fallback polling dilakukan oleh initPollingFallback()
        try {
            const sw = await navigator.serviceWorker?.ready;
            if (sw && 'sync' in sw) {
                await (sw as ServiceWorkerRegistration & {
                    sync: { register: (tag: string) => Promise<void> };
                }).sync.register('simpul-absensi-sync');
            }
        } catch {
            // Background Sync tidak didukung — polling fallback akan menangani
        }

        return client_uuid;
    }

    // -------------------------------------------------------------------------
    // flush — kirim semua pending ke server (T-10.05 / T-10.06)
    // -------------------------------------------------------------------------
    async function flush(): Promise<void> {
        if (_sedangFlush.value) return;
        _sedangFlush.value = true;

        try {
            const pending = await db.antrean_absensi
                .where('status')
                .anyOf(['pending', 'failed'])
                .and((item) => item.percobaan < MAX_PERCOBAAN)
                .sortBy('dibuat_pada');

            if (pending.length === 0) {
                return;
            }

            // Tandai semua sebagai syncing
            await db.antrean_absensi
                .where('client_uuid')
                .anyOf(pending.map((i) => i.client_uuid))
                .modify({ status: 'syncing', dicoba_pada: Date.now() });
            await muatAntrean();

            // Kirim array ke server (Keputusan #4: credentials include, bukan token)
            const response = await fetch('/api/absensi/sync', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    // Tidak ada X-CSRF-TOKEN — route ini dikecualikan dari CSRF (Keputusan #4)
                    // Idempotency-Key = gabungan uuid untuk batch ini
                    'Idempotency-Key': pending.map((i) => i.client_uuid).join(','),
                },
                body: JSON.stringify({
                    items: pending.map((i) => ({
                        client_uuid: i.client_uuid,
                        token_qr: i.token_qr,
                        jenis: i.jenis,
                        captured_at: i.captured_at,
                        clock_offset: i.clock_offset,
                        lat: i.lat,
                        lng: i.lng,
                    })),
                }),
            });

            if (response.ok) {
                const data = (await response.json()) as {
                    hasil: Array<{
                        client_uuid: string;
                        status: 'synced' | 'conflict_final' | 'failed';
                        kode?: string;
                        pesan?: string;
                    }>;
                };

                // Update status per-item sesuai response
                const itemsGagalServer: AntreanAbsensi[] = [];
                for (const hasil of data.hasil) {
                    const statusBaru: StatusAntrean =
                        hasil.status === 'synced'
                            ? 'synced'
                            : hasil.status === 'conflict_final'
                              ? 'conflict_final'
                              : 'failed';

                    await db.antrean_absensi.update(hasil.client_uuid, {
                        status: statusBaru,
                        kode_error: hasil.kode ?? null,
                        pesan_error: hasil.pesan ?? null,
                    });

                    if (statusBaru === 'failed') {
                        const itemObj = pending.find((p) => p.client_uuid === hasil.client_uuid);
                        if (itemObj) {
                            itemsGagalServer.push(itemObj);
                        }
                    }
                }

                if (itemsGagalServer.length > 0) {
                    await _terapkanBackoff(itemsGagalServer);
                }
            } else if (response.status === 401) {
                // Sesi expired — kembalikan ke pending agar bisa dicoba lagi setelah login
                await db.antrean_absensi
                    .where('client_uuid')
                    .anyOf(pending.map((i) => i.client_uuid))
                    .modify({ status: 'pending' });
            } else {
                // Kegagalan server lain → backoff per item
                await _terapkanBackoff(pending);
            }
        } catch {
            // Network error → backoff
            const pending = await db.antrean_absensi
                .where('status')
                .equals('syncing')
                .toArray();
            await _terapkanBackoff(pending);
        } finally {
            _sedangFlush.value = false;
            await muatAntrean();
        }
    }

    // -------------------------------------------------------------------------
    // retry — coba ulang satu item yang failed (tombol manual)
    // -------------------------------------------------------------------------
    async function retry(client_uuid: string): Promise<void> {
        await db.antrean_absensi.update(client_uuid, {
            status: 'pending',
            percobaan: 0,
            pesan_error: null,
            kode_error: null,
        });
        await muatAntrean();
        await flush();
    }

    // -------------------------------------------------------------------------
    // hapusSynced — bersihkan item yang sudah synced dari antrean
    // -------------------------------------------------------------------------
    async function hapusSynced(): Promise<void> {
        await db.antrean_absensi.where('status').equals('synced').delete();
        await muatAntrean();
    }

    // -------------------------------------------------------------------------
    // Internal: terapkan backoff atau tandai failed setelah max percobaan
    // -------------------------------------------------------------------------
    async function _terapkanBackoff(items: AntreanAbsensi[]): Promise<void> {
        for (const item of items) {
            const percobaanBaru = item.percobaan + 1;
            if (percobaanBaru >= MAX_PERCOBAAN) {
                await db.antrean_absensi.update(item.client_uuid, {
                    status: 'failed',
                    percobaan: percobaanBaru,
                    pesan_error:
                        'Gagal setelah ' + MAX_PERCOBAAN + ' percobaan. Tekan untuk mencoba lagi.',
                });
            } else {
                const delayMs = BACKOFF_DELAYS_MS[percobaanBaru - 1] ?? 16000;
                await db.antrean_absensi.update(item.client_uuid, {
                    status: 'pending',
                    percobaan: percobaanBaru,
                });
                // Jadwalkan flush berikutnya setelah delay (backoff)
                setTimeout(() => flush(), delayMs);
            }
        }
    }

    // Muat antrean saat composable pertama kali dipakai
    muatAntrean();

    return {
        antrean,
        jumlahPending,
        adaGagal,
        adaKonflikFinal,
        sedangFlush: readonly(_sedangFlush),
        enqueue,
        flush,
        retry,
        hapusSynced,
        muatAntrean,
        // Ekspor konstanta untuk dipakai di test / backoff polling
        MAX_PERCOBAAN,
        KODE_ERROR_FINAL,
    };
}
