<?php

namespace App\Enums;

enum JenisMutasi: string
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';
    case PindahRombel = 'pindah_rombel';
    case NaikKelas = 'naik_kelas';
    case TinggalKelas = 'tinggal_kelas';
    case Lulus = 'lulus';
    case DropOut = 'drop_out';
}
