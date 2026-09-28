<?php

namespace App\Enums;

enum StatusSiswa: string
{
    case Aktif = 'aktif';
    case Lulus = 'lulus';
    case Pindah = 'pindah';
    case DropOut = 'do';
}
