<?php

namespace App\Services;

use App\Enums\StatusAbsensi;
use App\Enums\StatusPengajuanIzin;
use App\Enums\StatusPersetujuanIzin;
use App\Models\Absensi;
use App\Models\JenisIzin;
use App\Models\KuotaCuti;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PersetujuanIzin;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * IzinApprovalService — orkestrasi alur pengajuan & persetujuan izin/cuti (T-09.03–T-09.06).
 *
 * Bertanggung jawab atas:
 *  1. Validasi pengajuan baru (AC6: cek hadir, cek rentang, cek kuota)
 *  2. Membuat langkah-langkah persetujuan sesuai urutan_approval di JenisIzin
 *  3. Eksekusi keputusan approver (setuju/tolak)
 *  4. Auto-isi absensi saat semua langkah disetujui (T-09.05)
 *  5. Update kuota_cuti saat pengajuan cuti disetujui (T-09.06)
 *  6. Guard: tolak scan QR pada hari izin yang sudah disetujui
 */
class IzinApprovalService
{
    /**
     * Buat pengajuan izin baru dan langkah-langkah persetujuannya.
     *
     * @param  array{
     *     jenis_izin_id: string,
     *     tanggal_mulai: string,
     *     tanggal_selesai: string,
     *     alasan: string,
     *     lampiran_path: string|null,
     *     lampiran_mime: string|null,
     * } $data
     *
     * @throws ValidationException
     */
    public function ajukan(Pegawai $pegawai, array $data): PengajuanIzin
    {
        $mulai   = Carbon::parse($data['tanggal_mulai'])->startOfDay();
        $selesai = Carbon::parse($data['tanggal_selesai'])->startOfDay();

        // Validasi: tanggal_mulai <= tanggal_selesai
        if ($mulai->gt($selesai)) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ]);
        }

        $jenisIzin = JenisIzin::findOrFail($data['jenis_izin_id']);

        // AC6: Tolak pengajuan untuk tanggal yang sudah tercatat hadir
        $this->pastikanTidakAdaAbsensiHadir($pegawai, $mulai, $selesai);

        // Cek kuota cuti (jika jenis mengurangi_kuota_cuti)
        if ($jenisIzin->mengurangi_kuota_cuti) {
            $this->pastikanKuotaCukup($pegawai, $mulai, $selesai);
        }

        $status = $jenisIzin->butuh_persetujuan
            ? StatusPengajuanIzin::Menunggu
            : StatusPengajuanIzin::Disetujui;

        $pengajuan = PengajuanIzin::create([
            'sekolah_id'      => $pegawai->sekolah_id,
            'pegawai_id'      => $pegawai->id,
            'jenis_izin_id'   => $jenisIzin->id,
            'tanggal_mulai'   => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            'alasan'          => $data['alasan'],
            'lampiran_path'   => $data['lampiran_path'] ?? null,
            'lampiran_mime'   => $data['lampiran_mime'] ?? null,
            'status'          => $status,
        ]);

        if ($jenisIzin->butuh_persetujuan) {
            $this->buatLangkahPersetujuan($pengajuan, $jenisIzin);
        } else {
            // Langsung auto-fill absensi tanpa persetujuan (misal Sakit dengan surat)
            $this->autoIsiAbsensi($pengajuan);
            if ($jenisIzin->mengurangi_kuota_cuti) {
                $this->kurangiKuota($pengajuan);
            }
        }

        // Kirim notifikasi in-app ke approver langkah 1 (T-09.04)
        if ($jenisIzin->butuh_persetujuan) {
            $this->notifikasiApproverAktif($pengajuan);
        }

        return $pengajuan->fresh(['persetujuan', 'jenisIzin']);
    }

    /**
     * Approver menyetujui atau menolak langkah yang sedang aktif.
     *
     * @throws ValidationException
     */
    public function putuskan(PersetujuanIzin $langkah, User $approver, bool $setuju, ?string $catatan): void
    {
        if ($langkah->status !== StatusPersetujuanIzin::Menunggu) {
            throw ValidationException::withMessages([
                'status' => 'Langkah ini sudah diputuskan sebelumnya.',
            ]);
        }

        // Pastikan approver punya role yang tepat
        if (! $approver->hasRole($langkah->approver_role)) {
            abort(403, 'Anda tidak berhak memutuskan langkah ini.');
        }

        // Pastikan approver berada di sekolah yang sama dengan pengaju
        // (Super Admin tidak punya sekolah_id — diizinkan lintas sekolah)
        $pengajuan = $langkah->pengajuan;
        if ($approver->sekolah_id !== null && $approver->sekolah_id !== $pengajuan->sekolah_id) {
            abort(404);
        }

        if (! $setuju && empty($catatan)) {
            throw ValidationException::withMessages([
                'catatan' => 'Catatan wajib diisi saat menolak.',
            ]);
        }

        $langkah->update([
            'approver_id'     => $approver->id,
            'status'          => $setuju ? StatusPersetujuanIzin::Disetujui : StatusPersetujuanIzin::Ditolak,
            'catatan'         => $catatan,
            'diputuskan_pada' => now(),
        ]);

        if (! $setuju) {
            $pengajuan->update(['status' => StatusPengajuanIzin::Ditolak]);
            $this->notifikasiPengaju($pengajuan, 'ditolak');

            return;
        }

        // Setuju: cek apakah masih ada langkah berikutnya
        $langkahBerikutnya = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)
            ->where('urutan', '>', $langkah->urutan)
            ->orderBy('urutan')
            ->first();

        if ($langkahBerikutnya) {
            // Ada langkah berikutnya — notifikasi approver berikutnya
            $this->notifikasiApproverAktif($pengajuan);
        } else {
            // Semua langkah selesai → pengajuan disetujui penuh
            $this->selesaikanPersetujuan($pengajuan);
        }
    }

    /**
     * Semua langkah disetujui — finalisasi pengajuan.
     *
     * AC6 (validasi ulang): cek kembali apakah ada absensi hadir yang muncul
     * selama jeda waktu antara pengajuan dan persetujuan akhir.
     */
    private function selesaikanPersetujuan(PengajuanIzin $pengajuan): void
    {
        $mulai   = $pengajuan->tanggal_mulai;
        $selesai = $pengajuan->tanggal_selesai;
        $pegawai = $pengajuan->pegawai;

        // Validasi ulang AC6 saat persetujuan final
        try {
            $this->pastikanTidakAdaAbsensiHadir($pegawai, $mulai, $selesai);
        } catch (ValidationException $e) {
            // Ada konflik: batalkan pengajuan dengan catatan otomatis
            $pengajuan->update(['status' => StatusPengajuanIzin::Dibatalkan]);
            $this->notifikasiPengaju($pengajuan, 'dibatalkan_konflik');

            return;
        }

        $pengajuan->update(['status' => StatusPengajuanIzin::Disetujui]);

        $this->autoIsiAbsensi($pengajuan);

        $jenisIzin = $pengajuan->jenisIzin;
        if ($jenisIzin->mengurangi_kuota_cuti) {
            $this->kurangiKuota($pengajuan);
        }

        $this->notifikasiPengaju($pengajuan, 'disetujui');
    }

    /**
     * Buat baris PersetujuanIzin untuk setiap langkah di urutan_approval.
     */
    private function buatLangkahPersetujuan(PengajuanIzin $pengajuan, JenisIzin $jenisIzin): void
    {
        $urutanApproval = $jenisIzin->urutan_approval;

        foreach ($urutanApproval as $urutan => $roleSlug) {
            PersetujuanIzin::create([
                'pengajuan_izin_id' => $pengajuan->id,
                'urutan'            => $urutan + 1,
                'approver_role'     => $roleSlug,
                'status'            => StatusPersetujuanIzin::Menunggu,
            ]);
        }
    }

    /**
     * Auto-isi baris absensi untuk setiap tanggal dalam rentang izin (T-09.05).
     *
     * Satu baris per tanggal: jenis='masuk', sumber='izin', waktu_server=null.
     * Status ditentukan dari kode jenis izin.
     * Tanggal yang sudah punya baris jenis='masuk' dilewati (idempoten).
     */
    public function autoIsiAbsensi(PengajuanIzin $pengajuan): void
    {
        $status = $this->statusDariJenisIzin($pengajuan->jenisIzin);
        $tanggal = $pengajuan->tanggal_mulai->copy();

        while ($tanggal->lte($pengajuan->tanggal_selesai)) {
            $tanggalStr = $tanggal->toDateString();

            // Idempoten: skip kalau sudah ada baris absensi masuk hari ini
            $sudahAda = Absensi::where('pegawai_id', $pengajuan->pegawai_id)
                ->where('tanggal', $tanggalStr)
                ->where('jenis', 'masuk')
                ->exists();

            if (! $sudahAda) {
                Absensi::create([
                    'sekolah_id'        => $pengajuan->sekolah_id,
                    'pegawai_id'        => $pengajuan->pegawai_id,
                    'tanggal'           => $tanggalStr,
                    'jenis'             => 'masuk',
                    'waktu_server'      => null,
                    'status'            => $status->value,
                    'menit_terlambat'   => 0,
                    'sumber'            => 'izin',
                    'pengajuan_izin_id' => $pengajuan->id,
                ]);
            }

            $tanggal = $tanggal->addDay();
        }
    }

    /**
     * Kurangi terpakai di kuota_cuti.
     * Baris kuota dibuat otomatis jika belum ada (menggunakan tahun ajaran aktif).
     */
    private function kurangiKuota(PengajuanIzin $pengajuan): void
    {
        $tahunAjaran = TahunAjaran::where('sekolah_id', $pengajuan->sekolah_id)
            ->where('is_aktif', true)
            ->first();

        if (! $tahunAjaran) {
            return; // Tidak ada tahun ajaran aktif — tidak bisa lacak
        }

        $kuota = KuotaCuti::firstOrCreate(
            [
                'pegawai_id'      => $pengajuan->pegawai_id,
                'tahun_ajaran_id' => $tahunAjaran->id,
            ],
            [
                'sekolah_id' => $pengajuan->sekolah_id,
                'kuota_hari' => 12,
                'terpakai'   => 0,
            ]
        );

        $jumlahHari = $pengajuan->jumlahHari();
        $kuota->increment('terpakai', $jumlahHari);
    }

    /**
     * Validasi AC6: pastikan tidak ada absensi hadir dalam rentang tanggal.
     *
     * @throws ValidationException
     */
    public function pastikanTidakAdaAbsensiHadir(Pegawai $pegawai, Carbon|\DateTimeInterface $mulai, Carbon|\DateTimeInterface $selesai): void
    {
        $konflik = Absensi::where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->where('jenis', 'masuk')
            ->whereIn('status', ['hadir', 'terlambat']) // pulang_cepat termasuk hadir secara fisik
            ->exists();

        if ($konflik) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Tidak dapat mengajukan izin untuk tanggal yang sudah tercatat hadir.',
            ]);
        }
    }

    /**
     * Guard untuk scan QR: cek apakah pegawai punya izin disetujui pada tanggal ini.
     *
     * Dipanggil dari AbsensiService::prosesAbsenQr sebelum simpan.
     * Jika ya → tolak dengan pesan jelas (tidak diam-diam menimpa izin).
     *
     * @throws ValidationException
     */
    public function pastikanTidakAdaIzinDisetujui(Pegawai $pegawai, string $tanggal): void
    {
        $adaIzin = Absensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', $tanggal)
            ->where('jenis', 'masuk')
            ->where('sumber', 'izin')
            ->whereIn('status', ['izin', 'sakit', 'cuti', 'dinas'])
            ->exists();

        if ($adaIzin) {
            throw ValidationException::withMessages([
                'payload_qr' => 'Anda sudah memiliki izin yang disetujui untuk hari ini. Scan QR tidak diperlukan.',
            ]);
        }
    }

    /**
     * Cek apakah pegawai masih punya sisa kuota cuti yang cukup.
     *
     * @throws ValidationException
     */
    private function pastikanKuotaCukup(Pegawai $pegawai, Carbon|\DateTimeInterface $mulai, Carbon|\DateTimeInterface $selesai): void
    {
        $tahunAjaran = TahunAjaran::where('sekolah_id', $pegawai->sekolah_id)
            ->where('is_aktif', true)
            ->first();

        if (! $tahunAjaran) {
            return; // Tidak bisa validasi tanpa tahun ajaran aktif
        }

        $kuota = KuotaCuti::where('pegawai_id', $pegawai->id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->first();

        $kuotaHari = $kuota ? $kuota->kuota_hari : 12;
        $terpakai  = $kuota ? $kuota->terpakai : 0;

        $jumlahDiajukan = (int) $mulai->diffInDays($selesai) + 1;

        if (($terpakai + $jumlahDiajukan) > $kuotaHari) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => 'Sisa kuota cuti tidak mencukupi. Sisa: '.($kuotaHari - $terpakai).' hari.',
            ]);
        }
    }

    /**
     * Tentukan StatusAbsensi dari jenis izin berdasarkan kode.
     */
    private function statusDariJenisIzin(JenisIzin $jenisIzin): StatusAbsensi
    {
        return match ($jenisIzin->kode) {
            'sakit' => StatusAbsensi::Sakit,
            'cuti'  => StatusAbsensi::Cuti,
            'dinas' => StatusAbsensi::Dinas,
            default => StatusAbsensi::Izin,
        };
    }

    /**
     * Notifikasi in-app ke approver aktif (langkah terendah yang masih menunggu).
     * Menggunakan channel database bawaan Laravel.
     */
    private function notifikasiApproverAktif(PengajuanIzin $pengajuan): void
    {
        $langkahAktif = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)
            ->where('status', StatusPersetujuanIzin::Menunggu)
            ->orderBy('urutan')
            ->first();

        if (! $langkahAktif) {
            return;
        }

        // Cari semua user dengan role tersebut di sekolah pengaju
        $approvers = User::role($langkahAktif->approver_role)
            ->where(function ($q) use ($pengajuan) {
                $q->where('sekolah_id', $pengajuan->sekolah_id)
                    ->orWhereNull('sekolah_id'); // Super Admin
            })
            ->get();

        foreach ($approvers as $approver) {
            $approver->notify(new \App\Notifications\PengajuanIzinMenungguPersetujuan($pengajuan, $langkahAktif->urutan));
        }
    }

    /**
     * Notifikasi in-app ke pengaju tentang perubahan status.
     */
    private function notifikasiPengaju(PengajuanIzin $pengajuan, string $kejadian): void
    {
        $user = $pengajuan->pegawai?->user;
        if (! $user) {
            return;
        }

        $user->notify(new \App\Notifications\StatusPengajuanIzinBerubah($pengajuan, $kejadian));
    }
}
