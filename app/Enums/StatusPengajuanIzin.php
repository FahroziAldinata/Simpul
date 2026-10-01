<?php

namespace App\Enums;

/**
 * Status pengajuan izin dalam alur persetujuan berjenjang (T-09.03).
 */
enum StatusPengajuanIzin: string
{
    case Draft = 'draft';          // Tersimpan tapi belum disubmit (future use)
    case Menunggu = 'menunggu';    // Sedang dalam proses persetujuan
    case Disetujui = 'disetujui'; // Semua langkah disetujui
    case Ditolak = 'ditolak';     // Salah satu langkah ditolak
    case Dibatalkan = 'dibatalkan'; // Dibatalkan oleh pengaju
}
