<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PeriodeAktifController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $sekolahId = session('sekolah_id') ?? auth()->user()->sekolah_id;
        abort_unless($sekolahId, 404);

        $validated = $request->validate([
            'semester_id' => ['required', 'uuid'],
        ]);

        $semester = Semester::where('sekolah_id', $sekolahId)
            ->where('id', $validated['semester_id'])
            ->firstOrFail();

        session(['selected_semester_id' => $semester->id]);

        return back();
    }
}
