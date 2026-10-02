/**
 * T-10.02 — Skema Dexie.js untuk penyimpanan offline (IndexedDB)
 *
 * Store utama: `antrean_absensi`
 * Payload sesuai PRD 6.2: { client_uuid, token_qr, jenis, captured_at, lat, lng }
 *
 * client_uuid: UUIDv4 yang dibuat SATU KALI per item saat item dimasukkan ke
 * antrean (bukan identitas perangkat permanen). Berfungsi sebagai idempotency
 * key sisi klien — satu operasi absensi = satu uuid unik.
 *
 * Status item antrean:
 *   pending        — tersimpan lokal, belum di-sync
 *   syncing        — sedang dikirim ke server
 *   synced         — berhasil tersimpan di server
 *   failed         — gagal setelah max percobaan backoff
 *   conflict_final — ditolak karena aturan bisnis final (mis. ada izin disetujui)
 *                    tidak akan di-retry otomatis
 */

import Dexie, { type Table } from 'dexie';

export type StatusAntrean =
    | 'pending'
    | 'syncing'
    | 'synced'
    | 'failed'
    | 'conflict_final';

export type JenisAbsensi = 'masuk' | 'pulang';

export interface AntreanAbsensi {
    /** UUIDv4 unik per item — juga dipakai sebagai Idempotency-Key ke server */
    client_uuid: string;
    /** Token QR yang dipindai (base64 encoded, dari server) */
    token_qr: string;
    /** Jenis absensi: masuk atau pulang */
    jenis: JenisAbsensi;
    /**
     * Waktu scan di perangkat (unix ms dari Date.now()).
     * Dikirim apa adanya ke server; server mengoreksi dengan clockOffset.
     * PRD B4: waktu yang dicatat adalah waktu server, bukan waktu perangkat —
     * diimplementasikan via server-side correction, bukan dengan mengubah nilai ini.
     */
    captured_at: number;
    /** Offset clock terukur saat captured_at diambil (server_time - client_time, ms) */
    clock_offset: number;
    /** Koordinat GPS saat scan — null jika tidak tersedia atau dinonaktifkan */
    lat: number | null;
    lng: number | null;
    /** Status sinkronisasi saat ini */
    status: StatusAntrean;
    /** Jumlah percobaan sync yang sudah dilakukan */
    percobaan: number;
    /** Timestamp item dibuat di antrean (unix ms) */
    dibuat_pada: number;
    /** Timestamp percobaan sync terakhir (unix ms), null jika belum pernah dicoba */
    dicoba_pada: number | null;
    /** Pesan error terakhir (untuk status failed/conflict_final) */
    pesan_error: string | null;
    /** Kode error spesifik dari server (mis. 'izin_disetujui') */
    kode_error: string | null;
}

export class SimpulDB extends Dexie {
    antrean_absensi!: Table<AntreanAbsensi, string>;

    constructor() {
        super('simpul_offline');

        this.version(1).stores({
            /**
             * client_uuid = primary key (string, bukan auto-increment)
             * Index tambahan: status (untuk query pending items saat flush)
             * Index: dibuat_pada (untuk urutan FIFO saat sync)
             */
            antrean_absensi: 'client_uuid, status, dibuat_pada',
        });
    }
}

export const db = new SimpulDB();

/**
 * Utilitas: buat UUIDv4 di sisi klien.
 * Pakai crypto.randomUUID() jika tersedia (semua browser modern + SW context).
 * Fallback ke Math.random() pattern untuk lingkungan yang sangat lama.
 */
export function buatClientUuid(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    // Fallback (tidak akan tercapai di browser target NFR-12)
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}
