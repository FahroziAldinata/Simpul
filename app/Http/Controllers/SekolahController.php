<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSekolahRequest;
use App\Models\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SekolahController extends Controller
{
    /**
     * Display the school profile form.
     */
    public function edit(Request $request, ?string $id = null): Response
    {
        $user = $request->user();
        $targetId = $id ?? session('sekolah_id');

        $sekolah = Sekolah::find($targetId);

        if (! $sekolah) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        // Isolasi tenant: jika user terikat sekolah dan target berbeda, wajib return 404
        if ($user && $user->sekolah_id !== null && $user->sekolah_id !== $sekolah->id) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        if (! $user) {
            abort(401);
        }

        // RBAC check: Super Admin, Operator, Kepsek, Waka Kurikulum boleh melihat
        $canView = $user->can('sekolah.view')
            || $user->can('data_induk.view')
            || $user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum']);

        if (! $canView) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat profil sekolah.');
        }

        $canUpdate = $user->can('sekolah.update')
            || $user->can('data_induk.update')
            || $user->hasRole(['super_admin', 'operator']);

        return Inertia::render('data-induk/sekolah/Edit', [
            'sekolah' => $sekolah,
            'canUpdate' => (bool) $canUpdate,
        ]);
    }

    /**
     * Update the school profile.
     */
    public function update(UpdateSekolahRequest $request, ?string $id = null): RedirectResponse
    {
        $user = $request->user();
        $targetId = $id ?? session('sekolah_id');

        $sekolah = Sekolah::find($targetId);

        if (! $sekolah) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        // Isolasi tenant: jika user terikat sekolah dan target berbeda, wajib return 404
        if ($user && $user->sekolah_id !== null && $user->sekolah_id !== $sekolah->id) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            // Hapus logo lama jika ada
            if ($sekolah->logo_path && Storage::disk('s3')->exists($sekolah->logo_path)) {
                Storage::disk('s3')->delete($sekolah->logo_path);
            }

            /** @var UploadedFile $logoFile */
            $logoFile = $request->file('logo');
            $path = $logoFile->store("logos/{$sekolah->id}", 's3');
            $validated['logo_path'] = $path;
        }

        unset($validated['logo']);

        $sekolah->update($validated);

        return back()->with('success', 'Profil sekolah berhasil diperbarui.');
    }
}
