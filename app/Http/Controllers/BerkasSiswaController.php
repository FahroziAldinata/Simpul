<?php

namespace App\Http\Controllers;

use App\Enums\JenisBerkasSiswa;
use App\Models\BerkasSiswa;
use App\Models\Siswa;
use App\Services\BerkasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BerkasSiswaController extends Controller
{
    /**
     * Resolve student with strict tenant isolation (returns 404 if not found or cross-school).
     */
    private function resolveSiswa(Request $request, string $siswaId): Siswa
    {
        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        /** @var Siswa|null $siswa */
        $siswa = Siswa::withoutGlobalScopes()->find($siswaId);
        if (! $siswa || $siswa->sekolah_id !== $sekolahId) {
            abort(404, 'Data siswa tidak ditemukan.');
        }

        return $siswa;
    }

    /**
     * Upload or replace student document.
     */
    public function store(Request $request, string $siswaId, BerkasService $berkasService): RedirectResponse|JsonResponse
    {
        $siswa = $this->resolveSiswa($request, $siswaId);
        Gate::authorize('uploadBerkas', $siswa);

        $request->validate([
            'jenis' => ['required', Rule::enum(JenisBerkasSiswa::class)],
            'berkas' => ['required', 'file', 'max:2048'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('berkas');
        $jenis = JenisBerkasSiswa::from((string) $request->input('jenis'));

        $berkas = $berkasService->storeBerkas($siswa, $jenis, $file);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Berkas berhasil diunggah.',
                'berkas' => $berkas,
            ]);
        }

        return back()->with('success', 'Berkas berhasil diunggah.');
    }

    /**
     * Generate on-demand signed URL for downloading/previewing student document.
     */
    public function showUrl(Request $request, string $siswaId, string $jenis, BerkasService $berkasService): JsonResponse|RedirectResponse
    {
        $siswa = $this->resolveSiswa($request, $siswaId);
        Gate::authorize('viewBerkas', $siswa);

        $jenisEnum = JenisBerkasSiswa::tryFrom($jenis);
        if (! $jenisEnum) {
            abort(404, 'Jenis berkas tidak valid.');
        }

        /** @var BerkasSiswa|null $berkas */
        $berkas = BerkasSiswa::where('siswa_id', $siswa->id)
            ->where('jenis', $jenisEnum->value)
            ->first();

        if (! $berkas) {
            abort(404, 'Berkas belum diunggah.');
        }

        $signedUrl = $berkasService->getSignedUrl($berkas, 300);

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'url' => $signedUrl,
                'expires_in' => 300,
                'nama_file_asli' => $berkas->nama_file_asli,
            ]);
        }

        return redirect()->away($signedUrl);
    }

    /**
     * Delete student document.
     */
    public function destroy(Request $request, string $siswaId, string $jenis, BerkasService $berkasService): RedirectResponse|JsonResponse
    {
        $siswa = $this->resolveSiswa($request, $siswaId);
        Gate::authorize('deleteBerkas', $siswa);

        $jenisEnum = JenisBerkasSiswa::tryFrom($jenis);
        if (! $jenisEnum) {
            abort(404, 'Jenis berkas tidak valid.');
        }

        /** @var BerkasSiswa|null $berkas */
        $berkas = BerkasSiswa::where('siswa_id', $siswa->id)
            ->where('jenis', $jenisEnum->value)
            ->first();

        if (! $berkas) {
            abort(404, 'Berkas tidak ditemukan.');
        }

        $berkasService->deleteBerkas($berkas);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Berkas berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'Berkas berhasil dihapus.');
    }
}
