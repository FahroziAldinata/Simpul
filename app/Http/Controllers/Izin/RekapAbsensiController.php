<?php

namespace App\Http\Controllers\Izin;

use App\Http\Controllers\Controller;
use App\Models\KuotaCuti;
use App\Models\Pegawai;
use App\Models\TahunAjaran;
use App\Services\RekapAbsensiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RekapAbsensiController — matriks rekap bulanan dan kuota cuti (T-09.07, T-09.06).
 *
 * Akses (PRD 4.2):
 * - Super Admin, Operator, Kepsek: semua pegawai
 * - Guru / Wali Kelas: hanya data sendiri
 */
class RekapAbsensiController extends Controller
{
    public function __construct(private readonly RekapAbsensiService $rekapService) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();

        abort_unless(
            $user?->hasRole(['super_admin', 'operator', 'kepsek', 'guru', 'wali_kelas']),
            403
        );

        $sekolahId = session('sekolah_id');
        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);

        // Guru/Wali Kelas hanya bisa lihat data sendiri
        $pegawaiIdFilter = null;
        if ($user->hasRole(['guru', 'wali_kelas'])) {
            $pegawai = Pegawai::where('user_id', $user->id)->first();
            $pegawaiIdFilter = $pegawai?->id;
        }

        $rekap = $this->rekapService->generate($sekolahId, $bulan, $tahun, $pegawaiIdFilter);

        // Kuota cuti untuk bulan yang sama (tahun ajaran aktif)
        $tahunAjaran = TahunAjaran::where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->first();

        $kuotaCuti = $tahunAjaran
            ? KuotaCuti::where('sekolah_id', $sekolahId)
                ->where('tahun_ajaran_id', $tahunAjaran->id)
                ->when($pegawaiIdFilter, fn ($q) => $q->where('pegawai_id', $pegawaiIdFilter))
                ->with('pegawai:id,nama')
                ->get(['id', 'pegawai_id', 'kuota_hari', 'terpakai'])
            : collect();

        return Inertia::render('izin/RekapAbsensi', [
            'rekap' => $rekap,
            'kuotaCuti' => $kuotaCuti,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'durasiMs' => $rekap['durasi_ms'],
        ]);
    }
}
