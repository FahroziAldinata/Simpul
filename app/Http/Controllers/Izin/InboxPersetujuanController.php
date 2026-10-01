<?php

namespace App\Http\Controllers\Izin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanIzin;
use App\Models\PersetujuanIzin;
use App\Services\IzinApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * InboxPersetujuanController — inbox approver dan aksi setuju/tolak (T-09.04).
 *
 * Hanya Super Admin dan Kepsek yang bisa approve (PRD 4.2).
 * Approver hanya melihat langkah yang menjadi giliran mereka saat ini.
 */
class InboxPersetujuanController extends Controller
{
    public function __construct(private readonly IzinApprovalService $izinService) {}

    /**
     * Daftar pengajuan yang menunggu keputusan approver yang sedang login.
     *
     * Aturan visibilitas:
     * - Cari langkah PersetujuanIzin dengan status=menunggu DAN approver_role cocok dengan role user
     * - Pastikan tidak ada langkah urutan lebih rendah yang masih menunggu (langkah sebelumnya harus selesai)
     */
    public function index(): Response
    {
        abort_unless(auth()->user()?->hasRole(['super_admin', 'kepsek']), 403);

        $user = auth()->user();
        $userRoles = $user->getRoleNames()->toArray();

        // Subquery: langkah aktif yang benar-benar giliran user ini
        $langkahIds = PersetujuanIzin::query()
            ->whereIn('approver_role', $userRoles)
            ->where('status', 'menunggu')
            // Pastikan tidak ada langkah sebelumnya (urutan lebih kecil) yang masih menunggu
            ->whereNotExists(function ($sub) {
                $sub->from('persetujuan_izin as p2')
                    ->whereColumn('p2.pengajuan_izin_id', 'persetujuan_izin.pengajuan_izin_id')
                    ->whereColumn('p2.urutan', '<', 'persetujuan_izin.urutan')
                    ->where('p2.status', 'menunggu');
            })
            ->pluck('id');

        $langkah = PersetujuanIzin::whereIn('id', $langkahIds)
            ->with([
                'pengajuan.pegawai:id,nama,jenis',
                'pengajuan.jenisIzin:id,nama,kode',
                'pengajuan.persetujuan',
            ])
            ->when(
                ! $user->hasRole('super_admin'),
                fn ($q) => $q->whereHas('pengajuan', fn ($pq) => $pq->where('sekolah_id', session('sekolah_id')))
            )
            ->get();

        return Inertia::render('izin/InboxPersetujuan', [
            'langkah' => $langkah,
        ]);
    }

    /**
     * Approver memutuskan satu langkah: setuju atau tolak.
     */
    public function putuskan(Request $request, PersetujuanIzin $persetujuanIzin): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole(['super_admin', 'kepsek']), 403);

        $data = $request->validate([
            'keputusan' => ['required', 'in:disetujui,ditolak'],
            'catatan'   => ['nullable', 'string', 'max:500'],
        ]);

        $setuju = $data['keputusan'] === 'disetujui';

        $this->izinService->putuskan(
            $persetujuanIzin,
            auth()->user(),
            $setuju,
            $data['catatan'] ?? null,
        );

        $pesan = $setuju ? 'Pengajuan berhasil disetujui.' : 'Pengajuan berhasil ditolak.';

        return back()->with('success', $pesan);
    }
}
