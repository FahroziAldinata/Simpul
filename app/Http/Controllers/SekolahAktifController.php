<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SekolahAktifController extends Controller
{
    /**
     * Update active school for Super Admin.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || $user->sekolah_id !== null) {
            abort(403, 'Hanya Super Admin yang dapat mengganti sekolah aktif.');
        }

        $validated = $request->validate([
            'sekolah_id' => ['required', 'uuid', 'exists:sekolah,id'],
        ]);

        session(['sekolah_id' => $validated['sekolah_id']]);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($validated['sekolah_id']);
        }

        return back();
    }
}
