<?php

namespace App\Services;

use App\Enums\JenisAbsensi;
use App\Enums\StatusAbsensi;
use App\Models\JamKerja;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * StatusKehadiranCalculator — Menghitung status kehadiran pegawai.
 *
 * Logika:
 *  - Masuk ≤ jam_masuk + toleransi_menit → Hadir
 *  - Masuk > jam_masuk + toleransi_menit → Terlambat (+ hitung menit_terlambat)
 *  - Pulang < jam_pulang               → Pulang Cepat
 *  - Pulang ≥ jam_pulang               → Hadir (untuk jenis pulang)
 *  - Tidak ada catatan sama sekali     → Alfa (dihitung di laporan, bukan di sini)
 */
class StatusKehadiranCalculator
{
    /**
     * Hitung status dan menit keterlambatan untuk satu catatan absensi.
     *
     * @param  CarbonInterface  $waktuAbsen  Waktu server saat absen terjadi
     * @param  JenisAbsensi  $jenis  Masuk atau Pulang
     * @param  JamKerja  $jamKerja  Konfigurasi jam kerja hari tersebut
     * @return array{status: StatusAbsensi, menit_terlambat: int}
     */
    public function hitung(CarbonInterface $waktuAbsen, JenisAbsensi $jenis, JamKerja $jamKerja): array
    {
        // Hari libur — jika tercatat absen di hari libur, anggap hadir (tidak ada keterlambatan)
        if ($jamKerja->is_libur) {
            return [
                'status' => StatusAbsensi::Hadir,
                'menit_terlambat' => 0,
            ];
        }

        if ($jenis === JenisAbsensi::Masuk) {
            return $this->hitungMasuk($waktuAbsen, $jamKerja);
        }

        return $this->hitungPulang($waktuAbsen, $jamKerja);
    }

    /**
     * Cari JamKerja yang berlaku untuk pegawai/kelompok pada hari tertentu.
     *
     * Urutan prioritas: kelompok spesifik pegawai → 'umum'
     *
     * @param  string  $kelompok  Kelompok pegawai (mis. 'guru', 'tu', 'umum')
     * @param  int  $hari  1 = Senin, ..., 7 = Minggu
     */
    public function cariJamKerja(string $sekolahId, string $kelompok, int $hari): ?JamKerja
    {
        // Coba cari jadwal khusus untuk kelompok tersebut
        $jamKerja = JamKerja::where('sekolah_id', $sekolahId)
            ->where('kelompok', $kelompok)
            ->where('hari', $hari)
            ->first();

        // Fallback ke 'umum' jika tidak ada jadwal spesifik
        if (! $jamKerja && $kelompok !== 'umum') {
            $jamKerja = JamKerja::where('sekolah_id', $sekolahId)
                ->where('kelompok', 'umum')
                ->where('hari', $hari)
                ->first();
        }

        return $jamKerja;
    }

    /**
     * @return array{status: StatusAbsensi, menit_terlambat: int}
     */
    private function hitungMasuk(CarbonInterface $waktuAbsen, JamKerja $jamKerja): array
    {
        if (! $jamKerja->jam_masuk) {
            return ['status' => StatusAbsensi::Hadir, 'menit_terlambat' => 0];
        }

        // Batas toleransi = jam_masuk + toleransi_menit
        $batasToleransi = Carbon::parse($jamKerja->jam_masuk)
            ->setDate($waktuAbsen->year, $waktuAbsen->month, $waktuAbsen->day)
            ->addMinutes($jamKerja->toleransi_menit);

        if ($waktuAbsen->lte($batasToleransi)) {
            return ['status' => StatusAbsensi::Hadir, 'menit_terlambat' => 0];
        }

        // Terlambat — hitung dari jam_masuk (bukan dari batas toleransi)
        $jamMasukMurni = Carbon::parse($jamKerja->jam_masuk)
            ->setDate($waktuAbsen->year, $waktuAbsen->month, $waktuAbsen->day);

        $menitTerlambat = (int) abs($waktuAbsen->diffInMinutes($jamMasukMurni));

        return [
            'status' => StatusAbsensi::Terlambat,
            'menit_terlambat' => $menitTerlambat,
        ];
    }

    /**
     * @return array{status: StatusAbsensi, menit_terlambat: int}
     */
    private function hitungPulang(CarbonInterface $waktuAbsen, JamKerja $jamKerja): array
    {
        if (! $jamKerja->jam_pulang) {
            return ['status' => StatusAbsensi::Hadir, 'menit_terlambat' => 0];
        }

        $jamPulangResmi = Carbon::parse($jamKerja->jam_pulang)
            ->setDate($waktuAbsen->year, $waktuAbsen->month, $waktuAbsen->day);

        if ($waktuAbsen->lt($jamPulangResmi)) {
            return ['status' => StatusAbsensi::PulangCepat, 'menit_terlambat' => 0];
        }

        return ['status' => StatusAbsensi::Hadir, 'menit_terlambat' => 0];
    }
}
