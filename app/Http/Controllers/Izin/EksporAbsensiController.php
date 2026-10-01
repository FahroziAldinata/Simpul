<?php

namespace App\Http\Controllers\Izin;

use App\Exports\RekapAbsensiExport;
use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Services\RekapAbsensiService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * EksporAbsensiController — ekspor Excel dan PDF rekap bulanan (T-09.08).
 *
 * Excel: maatwebsite/excel, NIP sebagai teks (NumberFormat::FORMAT_TEXT).
 * PDF: via Gotenberg container (sudah ada sejak Minggu 2).
 */
class EksporAbsensiController extends Controller
{
    public function __construct(private readonly RekapAbsensiService $rekapService) {}

    /**
     * Ekspor rekap bulanan ke Excel.
     *
     * Format yang disintesis (konsekuensi ADR-0000: riset lapangan Minggu 1 dilewati):
     * - Baris header: kop sekolah (nama, NPSN, alamat)
     * - Baris sub-header: "REKAP ABSENSI PEGAWAI — Bulan X Tahun Y"
     * - Baris kolom: No | Nama | NIP (teks) | Jenis | H | T | S | I | C | D | A | Menit Telat
     * - Baris data: satu pegawai per baris + ringkasan
     * - Footer: blok tanda tangan kepala sekolah
     */
    public function excel(Request $request): BinaryFileResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['super_admin', 'operator', 'kepsek']),
            403
        );

        $sekolahId = session('sekolah_id');
        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);

        $rekap = $this->rekapService->generate($sekolahId, $bulan, $tahun);
        $sekolah = Sekolah::withoutGlobalScopes()->find($sekolahId);

        $namaFile = 'rekap_absensi_'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'_'.$tahun.'.xlsx';

        return Excel::download(
            new RekapAbsensiExport($rekap, $sekolah, $bulan, $tahun),
            $namaFile
        );
    }

    /**
     * Ekspor rekap bulanan ke PDF via Gotenberg.
     *
     * Gotenberg menerima HTML dan mengembalikan PDF siap cetak.
     */
    public function pdf(Request $request): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['super_admin', 'operator', 'kepsek']),
            403
        );

        $sekolahId = session('sekolah_id');
        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);

        $rekap = $this->rekapService->generate($sekolahId, $bulan, $tahun);
        $sekolah = Sekolah::withoutGlobalScopes()->find($sekolahId);

        // Render HTML template untuk Gotenberg
        $html = view('exports.rekap-absensi-pdf', [
            'rekap' => $rekap,
            'sekolah' => $sekolah,
            'bulan' => $bulan,
            'tahun' => $tahun,
        ])->render();

        // Kirim ke Gotenberg container
        $gotenbergUrl = config('services.gotenberg.url', 'http://gotenberg:3000');

        $multipart = [
            [
                'name' => 'files',
                'contents' => $html,
                'filename' => 'index.html',
            ],
        ];

        $client = new Client;
        $response = $client->post($gotenbergUrl.'/forms/chromium/convert/html', [
            'multipart' => $multipart,
            'headers' => [
                'Gotenberg-Output-Filename' => 'rekap_absensi_'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'_'.$tahun,
            ],
        ]);

        return response($response->getBody()->getContents(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rekap_absensi_'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'_'.$tahun.'.pdf"',
        ]);
    }
}
