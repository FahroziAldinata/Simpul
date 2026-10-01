<?php

namespace App\Enums;

/**
 * Jenis absensi: masuk atau pulang.
 */
enum JenisAbsensi: string
{
    case Masuk = 'masuk';
    case Pulang = 'pulang';
}
