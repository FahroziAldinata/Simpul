<?php

namespace App\Enums;

/**
 * Status kehadiran absensi pegawai.
 *
 * Nilai hadir/terlambat/pulang_cepat/alfa dihitung oleh StatusKehadiranCalculator
 * berdasarkan jam_kerja + toleransi_menit (Minggu 8).
 *
 * Nilai izin/sakit/cuti/dinas diisi otomatis oleh IzinApprovalService
 * saat pengajuan izin disetujui penuh (T-09.05, Minggu 9).
 * Baris absensi untuk hari izin: jenis='masuk', sumber='izin', waktu_server=null.
 */
enum StatusAbsensi: string
{
    // --- Dari QR / manual scan ---
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case PulangCepat = 'pulang_cepat';
    case Alfa = 'alfa';

    // --- Dari izin yang disetujui (T-09.05) ---
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case Dinas = 'dinas';

    /**
     * Apakah status ini berasal dari izin (bukan absensi fisik).
     */
    public function dariIzin(): bool
    {
        return in_array($this, [self::Izin, self::Sakit, self::Cuti, self::Dinas]);
    }

    /**
     * Label pendek untuk sel rekap matriks (T-09.07).
     * Aturan desain C1: warna + huruf, bukan warna saja.
     */
    public function labelRekap(): string
    {
        return match ($this) {
            self::Hadir => 'H',
            self::Terlambat => 'T',
            self::PulangCepat => 'P',
            self::Alfa => 'A',
            self::Izin => 'I',
            self::Sakit => 'S',
            self::Cuti => 'C',
            self::Dinas => 'D',
        };
    }

    /**
     * Warna CSS kelas Tailwind untuk sel rekap matriks.
     */
    public function warnaTailwind(): string
    {
        return match ($this) {
            self::Hadir => 'bg-green-100 text-green-800',
            self::Terlambat => 'bg-yellow-100 text-yellow-800',
            self::PulangCepat => 'bg-orange-100 text-orange-800',
            self::Alfa => 'bg-red-100 text-red-800',
            self::Izin => 'bg-blue-100 text-blue-800',
            self::Sakit => 'bg-purple-100 text-purple-800',
            self::Cuti => 'bg-cyan-100 text-cyan-800',
            self::Dinas => 'bg-indigo-100 text-indigo-800',
        };
    }
}
