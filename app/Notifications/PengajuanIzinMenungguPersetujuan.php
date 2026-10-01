<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use App\Models\PersetujuanIzin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app: pengajuan izin menunggu keputusan approver (T-09.03 AC3).
 *
 * Dikirim ke setiap user yang memiliki role approver_role di langkah aktif.
 * Ditampilkan di topbar badge + dropdown (Minggu 9 scope: database channel saja).
 */
class PengajuanIzinMenungguPersetujuan extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PengajuanIzin $pengajuan,
        public readonly int $langkahUrutan,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $pegawai = $this->pengajuan->pegawai;

        return [
            'type'              => 'pengajuan_izin_menunggu',
            'pengajuan_izin_id' => $this->pengajuan->id,
            'langkah_urutan'    => $this->langkahUrutan,
            'pegawai_nama'      => $pegawai?->nama,
            'jenis_izin'        => $this->pengajuan->jenisIzin?->nama,
            'tanggal_mulai'     => $this->pengajuan->tanggal_mulai->toDateString(),
            'tanggal_selesai'   => $this->pengajuan->tanggal_selesai->toDateString(),
            'pesan'             => ($pegawai?->nama ?? 'Seseorang').' mengajukan '
                .($this->pengajuan->jenisIzin?->nama ?? 'izin')
                .' dan menunggu persetujuan Anda.',
        ];
    }
}
