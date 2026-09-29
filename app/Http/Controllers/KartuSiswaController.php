<?php

namespace App\Http\Controllers;

use App\Models\Rombel;
use App\Models\Siswa;
use App\Services\Kartu\KartuDigitalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KartuSiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected KartuDigitalService $kartuService
    ) {}

    /**
     * Download or view digital student card for a single student.
     */
    public function show(Request $request, Siswa $siswa): Response
    {
        $user = $request->user();
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;

        // Tenant isolation: 404 if student does not belong to active school
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            abort(404);
        }

        $this->authorize('view', $siswa);

        $sekolah = $siswa->sekolah;
        $pdfBinary = $this->kartuService->generateKartuSiswaPdf($siswa, $sekolah);

        $filename = 'kartu-pelajar-'.($siswa->nisn ?? $siswa->id).'.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Download or view bulk digital student cards for an entire rombel (8 cards/A4).
     */
    public function rombel(Request $request, Rombel $rombel): Response
    {
        $user = $request->user();
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;

        // Tenant isolation: 404 if rombel does not belong to active school
        if ($activeSekolahId && $rombel->sekolah_id !== $activeSekolahId) {
            abort(404);
        }

        // Authorization: operator, kepsek, waka_kurikulum, or assigned wali_kelas
        $canAccess = $user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum'])
            || ($user->hasRole('wali_kelas') && $rombel->wali_kelas_id && $rombel->wali_kelas_id === $user->pegawai?->id);

        if (! $canAccess) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencetak kartu rombel ini.');
        }

        // Dynamically elevate memory limit for bulk PDF generation (benchmarked up to ~273 MB for 36 cards)
        @ini_set('memory_limit', '512M');

        $sekolah = $rombel->sekolah;
        $siswaList = Siswa::whereHas('anggotaRombel', function ($q) use ($rombel) {
            $q->where('rombel_id', $rombel->id);
        })->orderBy('nama')->get();

        if ($siswaList->isEmpty()) {
            abort(404, 'Rombel ini tidak memiliki siswa untuk dicetak kartunya.');
        }

        $pdfBinary = $this->kartuService->generateKartuSiswaPdf($siswaList, $sekolah, $rombel->semester);

        $safeRombelName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rombel->nama) ?? 'rombel';
        $filename = "kartu-pelajar-rombel-{$safeRombelName}.pdf";

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
