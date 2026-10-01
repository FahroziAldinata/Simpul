<?php

namespace App\Enums;

/**
 * Status kehadiran absensi pegawai.
 * Dihitung oleh StatusKehadiranCalculator berdasarkan jam_kerja + toleransi_menit.
 */
enum StatusAbsensi: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case PulangCepat = 'pulang_cepat';
    case Alfa = 'alfa';
}
