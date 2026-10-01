<?php

namespace App\Enums;

/**
 * Status satu langkah dalam tabel persetujuan_izin (T-09.03).
 */
enum StatusPersetujuanIzin: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
}
