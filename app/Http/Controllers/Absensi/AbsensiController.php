<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Pegawai;
use App\Services\AbsensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AbsensiController — scan QR dan absen manual (T-08.05, T-08.08).
 */
class AbsensiController extends Controller
{
    public function __construct(private readonly AbsensiService $absensiService) {}

    /**
     * Halaman scan QR untuk guru/pegawai (T-08.05).
     * Semua role kecuali Orang Tua bisa mengakses (PRD 4.2).
     */
    public function scan(): Response
    {
        abort_unless(
            ! auth()->user()?->hasRole('orang_tua'),
            403
        );

        $pegawai = Pegawai::where('user_id', auth()->id())->first();

        return Inertia::render('absensi/Scan', [
            'pegawai' => $pegawai ? [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama,
            ] : null,
        ]);
    }

    /**
     * POST: Proses absensi dari QR scan (T-08.05).
     * Minggu 8: fetch biasa (bukan antrean offline — itu Minggu 10).
     */
    public function simpanQr(Request $request): JsonResponse
    {
        abort_unless(
            ! auth()->user()?->hasRole('orang_tua'),
            403
        );

        $data = $request->validate([
            'payload_qr' => ['required', 'string'],
            'jenis' => ['required', 'in:masuk,pulang'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'waktu_perangkat' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        $pegawai = Pegawai::where('user_id', auth()->id())
            ->where('sekolah_id', session('sekolah_id'))
            ->first();

        if (! $pegawai) {
            return response()->json(['message' => 'Data pegawai tidak ditemukan.'], 422);
        }

        $absensi = $this->absensiService->prosesAbsenQr($pegawai, $data);

        return response()->json([
            'message' => 'Absensi berhasil dicatat.',
            'status' => $absensi->status?->value,
            'menit_terlambat' => $absensi->menit_terlambat,
            'lokasi_mencurigakan' => $absensi->lokasi_mencurigakan,
        ]);
    }

    /**
     * Halaman absen manual untuk Operator (T-08.08).
     */
    public function indexManual(): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $pegawaiList = Pegawai::orderBy('nama')->get(['id', 'nama', 'jenis']);

        return Inertia::render('absensi/Manual', [
            'pegawaiList' => $pegawaiList,
            'batasHari' => AbsensiService::BATAS_MANUAL_HARI,
        ]);
    }

    /**
     * POST: Simpan absensi manual oleh Operator (T-08.08).
     * Wajib: alasan terisi. Tercatat di audit log via LogsSimpulActivity.
     */
    public function simpanManual(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $data = $request->validate([
            'pegawai_id' => ['required', 'uuid', 'exists:pegawai,id'],
            'tanggal' => ['required', 'date'],
            'jenis' => ['required', 'in:masuk,pulang'],
            'alasan_manual' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $pegawai = Pegawai::findOrFail($data['pegawai_id']);

        // Pastikan pegawai dalam sekolah yang sama (tenant isolation)
        abort_unless(
            $pegawai->sekolah_id === session('sekolah_id'),
            404
        );

        $absensi = $this->absensiService->prosesAbsenManual(
            $pegawai,
            $data,
            (int) auth()->id()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Absensi manual berhasil dicatat.',
                'id' => $absensi->id,
            ]);
        }

        return back()->with('success', 'Absensi manual berhasil dicatat untuk '.$pegawai->nama.'.');
    }
}
