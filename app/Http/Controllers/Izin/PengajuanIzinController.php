<?php

namespace App\Http\Controllers\Izin;

use App\Http\Controllers\Controller;
use App\Models\JenisIzin;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Services\IzinApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PengajuanIzinController — form pengajuan + daftar pengajuan milik pegawai (T-09.02).
 *
 * Semua role kecuali Orang Tua bisa mengajukan (PRD 4.2).
 */
class PengajuanIzinController extends Controller
{
    public function __construct(private readonly IzinApprovalService $izinService) {}

    public function index(): Response
    {
        abort_unless(! auth()->user()?->hasRole('orang_tua'), 403);

        $pegawai = Pegawai::where('user_id', auth()->id())->first();

        $pengajuan = $pegawai
            ? PengajuanIzin::where('pegawai_id', $pegawai->id)
                ->with(['jenisIzin:id,nama,kode', 'persetujuan'])
                ->latest()
                ->get()
            : collect();

        // Jenis izin yang tersedia (global + kustom sekolah)
        $sekolahId = session('sekolah_id');
        $jenisIzin = JenisIzin::where(function ($q) use ($sekolahId) {
            $q->whereNull('sekolah_id')->orWhere('sekolah_id', $sekolahId);
        })->where('is_aktif', true)->get(['id', 'nama', 'kode', 'butuh_lampiran', 'butuh_persetujuan', 'mengurangi_kuota_cuti']);

        return Inertia::render('izin/PengajuanIndex', [
            'pengajuan' => $pengajuan,
            'jenisIzin' => $jenisIzin,
            'pegawai'   => $pegawai ? ['id' => $pegawai->id, 'nama' => $pegawai->nama] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(! auth()->user()?->hasRole('orang_tua'), 403);

        $pegawai = Pegawai::where('user_id', auth()->id())
            ->where('sekolah_id', session('sekolah_id'))
            ->first();

        if (! $pegawai) {
            return back()->withErrors(['pegawai' => 'Data pegawai tidak ditemukan.']);
        }

        $data = $request->validate([
            'jenis_izin_id'   => ['required', 'uuid', 'exists:jenis_izin,id'],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'gte:tanggal_mulai'],
            'alasan'          => ['required', 'string', 'min:10', 'max:1000'],
            'lampiran'        => ['nullable', 'file', 'max:2048'], // 2MB maks
        ]);

        $jenisIzin = JenisIzin::findOrFail($data['jenis_izin_id']);

        // Validasi MIME asli (bukan ekstensi) — pola sama dengan berkas siswa Minggu 6
        $lampiranPath = null;
        $lampiranMime = null;

        if ($request->hasFile('lampiran')) {
            if (! $jenisIzin->butuh_lampiran && ! $request->hasFile('lampiran')) {
                // Lampiran tidak diwajibkan, tapi boleh diunggah
            }

            $file = $request->file('lampiran');
            $mime = $file->getMimeType(); // MIME asli dari konten, bukan ekstensi

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($mime, $allowedMimes)) {
                return back()->withErrors([
                    'lampiran' => 'Format file tidak valid. Gunakan PDF, JPG, PNG, atau WebP.',
                ]);
            }

            $lampiranPath = $file->store('izin/lampiran', 'private');
            $lampiranMime = $mime;
        } elseif ($jenisIzin->butuh_lampiran) {
            return back()->withErrors(['lampiran' => 'Lampiran wajib diunggah untuk jenis izin ini.']);
        }

        $pengajuan = $this->izinService->ajukan($pegawai, [
            'jenis_izin_id'   => $data['jenis_izin_id'],
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'alasan'          => $data['alasan'],
            'lampiran_path'   => $lampiranPath,
            'lampiran_mime'   => $lampiranMime,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pengajuan izin berhasil dikirim.',
                'id'      => $pengajuan->id,
                'status'  => $pengajuan->status->value,
            ]);
        }

        return back()->with('success', 'Pengajuan izin berhasil dikirim.');
    }

    public function batalkan(PengajuanIzin $pengajuanIzin): RedirectResponse
    {
        abort_unless(! auth()->user()?->hasRole('orang_tua'), 403);

        $pegawai = Pegawai::where('user_id', auth()->id())->first();

        // Hanya pengaju sendiri yang bisa membatalkan
        abort_unless($pengajuanIzin->pegawai_id === $pegawai?->id, 403);
        abort_unless($pengajuanIzin->sekolah_id === session('sekolah_id'), 404);

        if (! in_array($pengajuanIzin->status->value, ['menunggu', 'draft'])) {
            return back()->withErrors(['status' => 'Pengajuan yang sudah diproses tidak dapat dibatalkan.']);
        }

        $pengajuanIzin->update(['status' => 'dibatalkan']);

        return back()->with('success', 'Pengajuan berhasil dibatalkan.');
    }
}
