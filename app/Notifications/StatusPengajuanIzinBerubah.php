<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app: status pengajuan izin berubah (T-09.03 AC3).
 *
 * Dikirim ke pegawai pengaju saat pengajuannya disetujui, ditolak, atau dibatalkan sistem.
 */
class StatusPengajuanIzinBerubah extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PengajuanIzin $pengajuan,
        public readonly string $kejadian, // 'disetujui' | 'ditolak' | 'dibatalkan_konflik'
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $pesan = match ($this->kejadian) {
            'disetujui'          => 'Pengajuan '.$this->pengajuan->jenisIzin?->nama.' Anda telah disetujui.',
            'ditolak'            => 'Pengajuan '.$this->pengajuan->jenisIzin?->nama.' Anda ditolak.',
            'dibatalkan_konflik' => 'Pengajuan '.$this->pengajuan->jenisIzin?->nama.' Anda dibatalkan karena Anda sudah tercatat hadir pada tanggal tersebut.',
            default              => 'Status pengajuan izin Anda telah berubah.',
        };

        return [
            'type'              => 'status_pengajuan_izin_berubah',
            'pengajuan_izin_id' => $this->pengajuan->id,
            'kejadian'          => $this->kejadian,
            'status_baru'       => $this->pengajuan->status->value,
            'pesan'             => $pesan,
        ];
    }
}
