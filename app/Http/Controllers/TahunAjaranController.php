<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TahunAjaranController extends Controller
{
    private function resolveSekolahId(): string
    {
        $sekolahId = session('sekolah_id') ?? auth()->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        return $sekolahId;
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('data_induk.view'), 403);

        $sekolahId = $this->resolveSekolahId();

        $tahunAjaran = TahunAjaran::where('sekolah_id', $sekolahId)
            ->with(['semester' => fn ($q) => $q->orderBy('nama')])
            ->orderByDesc('tanggal_mulai')
            ->get();

        return Inertia::render('data-induk/tahun-ajaran/Index', [
            'tahunAjaran' => $tahunAjaran,
            'canManage' => (bool) auth()->user()->can('data_induk.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.create'), 403);

        $sekolahId = $this->resolveSekolahId();

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:50'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $isAktif = (bool) ($validated['is_aktif'] ?? false);

        DB::transaction(function () use ($sekolahId, $validated, $isAktif) {
            if ($isAktif) {
                TahunAjaran::where('sekolah_id', $sekolahId)->update(['is_aktif' => false]);
            }

            $tahunAjaran = TahunAjaran::create([
                'sekolah_id' => $sekolahId,
                'nama' => $validated['nama'],
                'tanggal_mulai' => $validated['tanggal_mulai'],
                'tanggal_selesai' => $validated['tanggal_selesai'],
                'is_aktif' => $isAktif,
            ]);

            $start = Carbon::parse($validated['tanggal_mulai']);
            $end = Carbon::parse($validated['tanggal_selesai']);

            // Semester Ganjil: start sampai akhir Desember (atau pertengahan durasi)
            $mid = $start->copy()->month(12)->endOfMonth();
            if ($mid->lte($start) || $mid->gte($end)) {
                $days = $start->diffInDays($end);
                $mid = $start->copy()->addDays(intdiv((int) $days, 2));
            }

            $genapStart = $mid->copy()->addDay();

            $ganjil = Semester::create([
                'sekolah_id' => $sekolahId,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'nama' => 'Ganjil',
                'tanggal_mulai' => $start->toDateString(),
                'tanggal_selesai' => $mid->toDateString(),
                'is_aktif' => $isAktif,
            ]);

            Semester::create([
                'sekolah_id' => $sekolahId,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'nama' => 'Genap',
                'tanggal_mulai' => $genapStart->toDateString(),
                'tanggal_selesai' => $end->toDateString(),
                'is_aktif' => false,
            ]);

            if ($isAktif) {
                Semester::where('sekolah_id', $sekolahId)
                    ->where('id', '!=', $ganjil->id)
                    ->update(['is_aktif' => false]);
            }
        });

        return back()->with('success', 'Tahun ajaran berhasil ditambahkan beserta 2 semester.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolahId = $this->resolveSekolahId();
        $tahunAjaran = TahunAjaran::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:50'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $isAktif = (bool) ($validated['is_aktif'] ?? false);

        DB::transaction(function () use ($sekolahId, $tahunAjaran, $validated, $isAktif) {
            if ($isAktif && ! $tahunAjaran->is_aktif) {
                TahunAjaran::where('sekolah_id', $sekolahId)
                    ->where('id', '!=', $tahunAjaran->id)
                    ->update(['is_aktif' => false]);

                // Aktifkan semester pertama dari tahun ajaran ini jika belum ada yang aktif
                $firstSemester = $tahunAjaran->semester()->orderBy('nama')->first();
                if ($firstSemester) {
                    Semester::where('sekolah_id', $sekolahId)
                        ->where('id', '!=', $firstSemester->id)
                        ->update(['is_aktif' => false]);
                    $firstSemester->update(['is_aktif' => true]);
                }
            }

            $tahunAjaran->update([
                'nama' => $validated['nama'],
                'tanggal_mulai' => $validated['tanggal_mulai'],
                'tanggal_selesai' => $validated['tanggal_selesai'],
                'is_aktif' => $isAktif,
            ]);
        });

        return back()->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    public function activateSemester(string $semesterId): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolahId = $this->resolveSekolahId();
        $semester = Semester::where('sekolah_id', $sekolahId)->where('id', $semesterId)->firstOrFail();

        DB::transaction(function () use ($sekolahId, $semester) {
            // Aktifkan tahun ajaran induknya
            TahunAjaran::where('sekolah_id', $sekolahId)
                ->where('id', '!=', $semester->tahun_ajaran_id)
                ->update(['is_aktif' => false]);

            $semester->tahunAjaran()->update(['is_aktif' => true]);

            // Aktifkan semester ini
            Semester::where('sekolah_id', $sekolahId)
                ->where('id', '!=', $semester->id)
                ->update(['is_aktif' => false]);

            $semester->update(['is_aktif' => true]);
        });

        return back()->with('success', "Semester {$semester->nama} berhasil diaktifkan.");
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.delete'), 403);

        $sekolahId = $this->resolveSekolahId();
        $tahunAjaran = TahunAjaran::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        if ($tahunAjaran->is_aktif) {
            return back()->with('error', 'Tidak dapat menghapus tahun ajaran yang sedang aktif.');
        }

        $tahunAjaran->delete();

        return back()->with('success', 'Tahun ajaran berhasil dihapus.');
    }
}
