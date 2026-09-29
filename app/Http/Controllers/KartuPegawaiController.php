<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Services\Kartu\KartuDigitalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KartuPegawaiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected KartuDigitalService $kartuService
    ) {}

    /**
     * Download or view digital card for a single employee.
     */
    public function show(Request $request, Pegawai $pegawai): Response
    {
        $user = $request->user();
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;

        // Tenant isolation: 404 if pegawai does not belong to active school
        if ($activeSekolahId && $pegawai->sekolah_id !== $activeSekolahId) {
            abort(404);
        }

        $this->authorize('view', $pegawai);

        $sekolah = $pegawai->sekolah;
        $pdfBinary = $this->kartuService->generateKartuPegawaiPdf($pegawai, $sekolah);

        $filename = 'kartu-pegawai-'.($pegawai->nip ?? $pegawai->nuptk ?? $pegawai->id).'.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Download or view bulk digital cards for all employees in the school (8 cards/A4).
     */
    public function cetakMassal(Request $request): Response
    {
        $user = $request->user();
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;

        if (! $activeSekolahId) {
            abort(404, 'Sekolah aktif tidak ditemukan.');
        }

        if (! $user->hasRole(['super_admin', 'operator'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencetak kartu seluruh pegawai.');
        }

        // Dynamically elevate memory limit for bulk PDF generation (benchmarked up to ~273 MB)
        @ini_set('memory_limit', '512M');

        /** @var Sekolah $sekolah */
        $sekolah = Sekolah::findOrFail($activeSekolahId);

        $pegawaiList = Pegawai::where('sekolah_id', $activeSekolahId)
            ->orderBy('nama')
            ->get();

        if ($pegawaiList->isEmpty()) {
            abort(404, 'Tidak ada data pegawai untuk dicetak kartunya.');
        }

        $pdfBinary = $this->kartuService->generateKartuPegawaiPdf($pegawaiList, $sekolah);

        $safeSchoolName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $sekolah->nama) ?? 'sekolah';
        $filename = "kartu-pegawai-{$safeSchoolName}.pdf";

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
