<?php

namespace App\Http\Controllers;

use App\Models\HariLibur;
use App\Models\JamKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KalenderController extends Controller
{
    /**
     * Display the academic calendar and operational hours.
     */
    public function index(): Response
    {
        abort_unless(auth()->user()->can('data_induk.view'), 403);

        $hariLibur = HariLibur::orderBy('tanggal_mulai')->get();
        $jamKerja = JamKerja::where('kelompok', 'umum')->orderBy('hari')->get();

        // Default 7 hari jika belum ada di database
        if ($jamKerja->isEmpty()) {
            $jamKerja = collect(range(1, 7))->map(fn ($hari) => [
                'hari' => $hari,
                'kelompok' => 'umum',
                'jam_masuk' => $hari <= 5 ? '07:00' : null,
                'jam_pulang' => $hari <= 5 ? ($hari === 5 ? '11:30' : '15:00') : null,
                'is_libur' => $hari >= 6,
                'jumlah_jam_pelajaran' => $hari <= 4 ? 8 : ($hari === 5 ? 5 : 0),
            ]);
        }

        $user = request()->user();
        $canManage = $user?->can('data_induk.create') || $user?->can('data_induk.update') || $user?->hasRole(['super_admin', 'operator']);

        return Inertia::render('data-induk/kalender/Index', [
            'hariLibur' => $hariLibur,
            'jamKerja' => $jamKerja,
            'canManage' => (bool) $canManage,
            'totalSlotMingguan' => JamKerja::totalSlotMingguan(),
        ]);
    }

    /**
     * Store a new holiday.
     */
    public function storeHariLibur(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'in:nasional,sekolah,ujian,kegiatan'],
        ]);

        HariLibur::create($validated);

        return back()->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /**
     * Delete a holiday.
     */
    public function destroyHariLibur(HariLibur $hariLibur): RedirectResponse
    {
        $hariLibur->delete();

        return back()->with('success', 'Hari libur berhasil dihapus.');
    }

    /**
     * Update operational days and hours.
     */
    public function updateJamKerja(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hari' => ['required', 'array'],
            'hari.*.hari' => ['required', 'integer', 'between:1,7'],
            'hari.*.jam_masuk' => ['nullable', 'string'],
            'hari.*.jam_pulang' => ['nullable', 'string'],
            'hari.*.is_libur' => ['required', 'boolean'],
            'hari.*.jumlah_jam_pelajaran' => ['required', 'integer', 'min:0', 'max:15'],
        ]);

        $sekolahId = session('sekolah_id');

        foreach ($validated['hari'] as $item) {
            JamKerja::updateOrCreate(
                [
                    'sekolah_id' => $sekolahId,
                    'kelompok' => 'umum',
                    'hari' => $item['hari'],
                ],
                [
                    'jam_masuk' => $item['is_libur'] ? null : ($item['jam_masuk'] ?? null),
                    'jam_pulang' => $item['is_libur'] ? null : ($item['jam_pulang'] ?? null),
                    'is_libur' => $item['is_libur'],
                    'jumlah_jam_pelajaran' => $item['is_libur'] ? 0 : $item['jumlah_jam_pelajaran'],
                ]
            );
        }

        return back()->with('success', 'Pengaturan jam kerja sekolah berhasil diperbarui.');
    }
}
