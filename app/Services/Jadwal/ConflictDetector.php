<?php

namespace App\Services\Jadwal;

use App\Models\AlokasiJamMapel;
use App\Models\JadwalPelajaran;
use App\Models\JamKerja;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Ruang;

class ConflictDetector
{
    /**
     * Nama hari dalam bahasa Indonesia.
     *
     * @var array<int, string>
     */
    public const NAMA_HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    /**
     * Cek bentrok jadwal di level aplikasi (H1 s/d H6) sebelum eksekusi ke database.
     *
     * @param  array{
     *     sekolah_id: string,
     *     semester_id: string,
     *     hari: int,
     *     jam_mulai_ke: int,
     *     jam_selesai_ke: int,
     *     guru_id: string,
     *     rombel_id: string,
     *     mata_pelajaran_id: string,
     *     ruang_id?: string|null,
     * }  $data
     */
    public function check(array $data, ?string $ignoreId = null): ConflictResult
    {
        $result = new ConflictResult;

        $sekolahId = $data['sekolah_id'];
        $semesterId = $data['semester_id'];
        $hari = (int) $data['hari'];
        $jamMulai = (int) $data['jam_mulai_ke'];
        $jamSelesai = (int) $data['jam_selesai_ke'];
        $guruId = $data['guru_id'];
        $rombelId = $data['rombel_id'];
        $mapelId = $data['mata_pelajaran_id'];
        $ruangId = $data['ruang_id'] ?? null;

        $hariNama = self::NAMA_HARI[$hari] ?? "Hari ke-{$hari}";
        $durasiBaru = max(1, $jamSelesai - $jamMulai);

        // 1. Cek H5: Hari Libur & Jam Operasional Sekolah (JamKerja)
        /** @var JamKerja|null $jamKerja */
        $jamKerja = JamKerja::where('sekolah_id', $sekolahId)
            ->where('kelompok', 'umum')
            ->where('hari', $hari)
            ->first();

        if ($jamKerja) {
            if ($jamKerja->is_libur) {
                $result->add(new ConflictItem(
                    type: 'hari_libur',
                    message: "Hari {$hariNama} adalah hari libur operasional sekolah.",
                ));
            } elseif ($jamKerja->jumlah_jam_pelajaran > 0) {
                // Jam selesai maksimal adalah jumlah_jam_pelajaran + 1 (karena batas atas eksklusif range)
                $maxSelesai = $jamKerja->jumlah_jam_pelajaran + 1;
                if ($jamMulai < 1 || $jamSelesai > $maxSelesai) {
                    $result->add(new ConflictItem(
                        type: 'jam_operasional',
                        message: "Jam pelajaran berada di luar jam operasional sekolah pada hari {$hariNama} (maksimal jam ke-{$jamKerja->jumlah_jam_pelajaran}).",
                    ));
                }
            }
        }

        // 2. Cek H6: Mapel yang butuh ruang khusus (Lab, Bengkel)
        /** @var MataPelajaran|null $mapel */
        $mapel = MataPelajaran::find($mapelId);
        if ($mapel && ! empty($mapel->butuh_ruang_kategori)) {
            $kategoriDibutuhkan = $mapel->butuh_ruang_kategori;
            if (empty($ruangId)) {
                $result->add(new ConflictItem(
                    type: 'ruang_kategori',
                    message: "Mata pelajaran {$mapel->nama} memerlukan ruang berkategori '{$kategoriDibutuhkan}', tetapi belum ada ruang yang dipilih.",
                ));
            } else {
                /** @var Ruang|null $ruang */
                $ruang = Ruang::find($ruangId);
                if ($ruang && $ruang->kategori !== $kategoriDibutuhkan) {
                    $result->add(new ConflictItem(
                        type: 'ruang_kategori',
                        message: "Mata pelajaran {$mapel->nama} memerlukan ruang berkategori '{$kategoriDibutuhkan}', sedangkan ruang {$ruang->nama} berkategori '{$ruang->kategori}'.",
                    ));
                }
            }
        }

        // 3. Cek H4: Total Jam Mapel per Rombel tidak boleh melebihi Alokasi
        /** @var AlokasiJamMapel|null $alokasi */
        $alokasi = AlokasiJamMapel::where('sekolah_id', $sekolahId)
            ->where('rombel_id', $rombelId)
            ->where('mata_pelajaran_id', $mapelId)
            ->first();

        if ($alokasi && $alokasi->jam_per_minggu > 0) {
            $totalTerjadwal = (int) (JadwalPelajaran::where('sekolah_id', $sekolahId)
                ->where('semester_id', $semesterId)
                ->where('rombel_id', $rombelId)
                ->where('mata_pelajaran_id', $mapelId)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->selectRaw('COALESCE(SUM(jam_selesai_ke - jam_mulai_ke), 0) as total')
                ->value('total') ?? 0);

            if (($totalTerjadwal + $durasiBaru) > $alokasi->jam_per_minggu) {
                $mapelNama = $mapel->nama ?? 'Mata Pelajaran';
                $rombelNama = Rombel::find($rombelId)->nama ?? 'Kelas';
                $result->add(new ConflictItem(
                    type: 'alokasi',
                    message: "Alokasi jam untuk mata pelajaran {$mapelNama} di kelas {$rombelNama} sudah melebihi batas ({$totalTerjadwal}/{$alokasi->jam_per_minggu} jam). Menambah slot ini ({$durasiBaru} jam) akan melampaui kurikulum.",
                ));
            }
        }

        // 4. Cek H1: Bentrok Guru
        /** @var JadwalPelajaran|null $bentrokGuru */
        $bentrokGuru = JadwalPelajaran::with(['rombel', 'guru', 'mataPelajaran'])
            ->where('sekolah_id', $sekolahId)
            ->where('semester_id', $semesterId)
            ->where('guru_id', $guruId)
            ->where('hari', $hari)
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai_ke', '<', $jamSelesai)
                    ->where('jam_selesai_ke', '>', $jamMulai);
            })
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($bentrokGuru) {
            $guruNama = $bentrokGuru->guru->nama;
            $rombelNama = $bentrokGuru->rombel->nama;
            $result->add(new ConflictItem(
                type: 'guru',
                message: "Guru {$guruNama} sudah mengajar di kelas {$rombelNama} pada jam ke-{$bentrokGuru->jam_mulai_ke}–{$bentrokGuru->jam_selesai_ke} ({$hariNama}).",
                conflictingSchedule: $bentrokGuru,
            ));
        }

        // 5. Cek H2: Bentrok Ruang (hanya jika ruang_id diisi)
        if (! empty($ruangId)) {
            /** @var JadwalPelajaran|null $bentrokRuang */
            $bentrokRuang = JadwalPelajaran::with(['rombel', 'ruang', 'mataPelajaran'])
                ->where('sekolah_id', $sekolahId)
                ->where('semester_id', $semesterId)
                ->where('ruang_id', $ruangId)
                ->where('hari', $hari)
                ->where(function ($query) use ($jamMulai, $jamSelesai) {
                    $query->where('jam_mulai_ke', '<', $jamSelesai)
                        ->where('jam_selesai_ke', '>', $jamMulai);
                })
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->first();

            if ($bentrokRuang) {
                $ruangNama = $bentrokRuang->ruang ? $bentrokRuang->ruang->nama : 'Ruang';
                $rombelNama = $bentrokRuang->rombel->nama;
                $mapelNama = $bentrokRuang->mataPelajaran->nama;
                $result->add(new ConflictItem(
                    type: 'ruang',
                    message: "Ruang {$ruangNama} sudah digunakan oleh kelas {$rombelNama} ({$mapelNama}) pada jam ke-{$bentrokRuang->jam_mulai_ke}–{$bentrokRuang->jam_selesai_ke} ({$hariNama}).",
                    conflictingSchedule: $bentrokRuang,
                ));
            }
        }

        // 6. Cek H3: Bentrok Rombel
        /** @var JadwalPelajaran|null $bentrokRombel */
        $bentrokRombel = JadwalPelajaran::with(['rombel', 'guru', 'mataPelajaran'])
            ->where('sekolah_id', $sekolahId)
            ->where('semester_id', $semesterId)
            ->where('rombel_id', $rombelId)
            ->where('hari', $hari)
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai_ke', '<', $jamSelesai)
                    ->where('jam_selesai_ke', '>', $jamMulai);
            })
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($bentrokRombel) {
            $rombelNama = $bentrokRombel->rombel->nama;
            $mapelNama = $bentrokRombel->mataPelajaran->nama;
            $guruNama = $bentrokRombel->guru->nama;
            $result->add(new ConflictItem(
                type: 'rombel',
                message: "Kelas {$rombelNama} sudah memiliki jadwal {$mapelNama} bersama {$guruNama} pada jam ke-{$bentrokRombel->jam_mulai_ke}–{$bentrokRombel->jam_selesai_ke} ({$hariNama}).",
                conflictingSchedule: $bentrokRombel,
            ));
        }

        return $result;
    }

    /**
     * Deteksi bentrok dalam memori (untuk auto-generator, batch checking, dan client-side sync).
     *
     * @param  array{
     *     hari: int,
     *     jam_mulai_ke: int,
     *     jam_selesai_ke: int,
     *     guru_id: string,
     *     rombel_id: string,
     *     mata_pelajaran_id?: string|null,
     *     ruang_id?: string|null,
     * }  $candidate
     * @param  iterable<JadwalPelajaran>  $existingSchedules
     * @param  array{
     *     alokasi_jam?: int|null,
     *     jam_operasional_maks?: int|null,
     *     is_hari_libur?: bool|null,
     *     butuh_ruang_kategori?: string|null,
     *     ruang_kategori?: string|null,
     *     nama_mapel?: string|null,
     *     nama_rombel?: string|null,
     * }|null  $context
     */
    public function checkInMemory(
        array $candidate,
        iterable $existingSchedules,
        ?string $ignoreId = null,
        ?array $context = null
    ): ConflictResult {
        $result = new ConflictResult;

        $hari = (int) $candidate['hari'];
        $jamMulai = (int) $candidate['jam_mulai_ke'];
        $jamSelesai = (int) $candidate['jam_selesai_ke'];
        $guruId = $candidate['guru_id'];
        $rombelId = $candidate['rombel_id'];
        $mapelId = $candidate['mata_pelajaran_id'] ?? null;
        $ruangId = $candidate['ruang_id'] ?? null;

        $hariNama = self::NAMA_HARI[$hari] ?? "Hari ke-{$hari}";
        $durasiBaru = max(1, $jamSelesai - $jamMulai);

        // H5 in-memory: Jam operasional & hari libur
        if ($context) {
            if (! empty($context['is_hari_libur'])) {
                $result->add(new ConflictItem(
                    type: 'hari_libur',
                    message: "Hari {$hariNama} adalah hari libur operasional sekolah.",
                ));
            } elseif (isset($context['jam_operasional_maks']) && $context['jam_operasional_maks'] > 0) {
                $maxSelesai = $context['jam_operasional_maks'] + 1;
                if ($jamMulai < 1 || $jamSelesai > $maxSelesai) {
                    $result->add(new ConflictItem(
                        type: 'jam_operasional',
                        message: "Jam pelajaran berada di luar jam operasional sekolah pada hari {$hariNama} (maksimal jam ke-{$context['jam_operasional_maks']}).",
                    ));
                }
            }

            // H6 in-memory: Kategori ruang
            if (! empty($context['butuh_ruang_kategori'])) {
                $butuh = $context['butuh_ruang_kategori'];
                $ruangKat = $context['ruang_kategori'] ?? null;
                if (empty($ruangId)) {
                    $mapelNama = $context['nama_mapel'] ?? 'Mata Pelajaran';
                    $result->add(new ConflictItem(
                        type: 'ruang_kategori',
                        message: "Mata pelajaran {$mapelNama} memerlukan ruang berkategori '{$butuh}', tetapi belum ada ruang yang dipilih.",
                    ));
                } elseif ($ruangKat && $ruangKat !== $butuh) {
                    $mapelNama = $context['nama_mapel'] ?? 'Mata Pelajaran';
                    $result->add(new ConflictItem(
                        type: 'ruang_kategori',
                        message: "Mata pelajaran {$mapelNama} memerlukan ruang berkategori '{$butuh}', sedangkan ruang berkategori '{$ruangKat}'.",
                    ));
                }
            }
        }

        // H4 in-memory: Total jam mapel per rombel
        if ($context && isset($context['alokasi_jam']) && $context['alokasi_jam'] > 0 && $mapelId) {
            $totalTerjadwal = 0;
            foreach ($existingSchedules as $s) {
                if ($ignoreId && $s->id === $ignoreId) {
                    continue;
                }
                if ($s->rombel_id === $rombelId && $s->mata_pelajaran_id === $mapelId) {
                    $totalTerjadwal += max(0, (int) $s->jam_selesai_ke - (int) $s->jam_mulai_ke);
                }
            }
            if (($totalTerjadwal + $durasiBaru) > $context['alokasi_jam']) {
                $mapelNama = $context['nama_mapel'] ?? 'Mata Pelajaran';
                $rombelNama = $context['nama_rombel'] ?? 'Kelas';
                $result->add(new ConflictItem(
                    type: 'alokasi',
                    message: "Alokasi jam untuk mata pelajaran {$mapelNama} di kelas {$rombelNama} sudah melebihi batas ({$totalTerjadwal}/{$context['alokasi_jam']} jam). Menambah slot ini ({$durasiBaru} jam) akan melampaui kurikulum.",
                ));
            }
        }

        // H1, H2, H3 in-memory
        foreach ($existingSchedules as $schedule) {
            if ($ignoreId && $schedule->id === $ignoreId) {
                continue;
            }

            if ((int) $schedule->hari !== $hari) {
                continue;
            }

            // Cek apakah range overlap: jam_mulai_ke < $jamSelesai AND jam_selesai_ke > $jamMulai
            $overlap = ((int) $schedule->jam_mulai_ke < $jamSelesai) && ((int) $schedule->jam_selesai_ke > $jamMulai);
            if (! $overlap) {
                continue;
            }

            // Cek Guru
            if ($schedule->guru_id === $guruId) {
                $guruNama = $schedule->guru->nama;
                $rombelNama = $schedule->rombel->nama;
                $result->add(new ConflictItem(
                    type: 'guru',
                    message: "Guru {$guruNama} sudah mengajar di kelas {$rombelNama} pada jam ke-{$schedule->jam_mulai_ke}–{$schedule->jam_selesai_ke} ({$hariNama}).",
                    conflictingSchedule: $schedule,
                ));
            }

            // Cek Ruang
            if (! empty($ruangId) && ! empty($schedule->ruang_id) && $schedule->ruang_id === $ruangId) {
                $ruangNama = $schedule->ruang ? $schedule->ruang->nama : 'Ruang';
                $rombelNama = $schedule->rombel->nama;
                $mapelNama = $schedule->mataPelajaran->nama;
                $result->add(new ConflictItem(
                    type: 'ruang',
                    message: "Ruang {$ruangNama} sudah digunakan oleh kelas {$rombelNama} ({$mapelNama}) pada jam ke-{$schedule->jam_mulai_ke}–{$schedule->jam_selesai_ke} ({$hariNama}).",
                    conflictingSchedule: $schedule,
                ));
            }

            // Cek Rombel
            if ($schedule->rombel_id === $rombelId) {
                $rombelNama = $schedule->rombel->nama;
                $mapelNama = $schedule->mataPelajaran->nama;
                $guruNama = $schedule->guru->nama;
                $result->add(new ConflictItem(
                    type: 'rombel',
                    message: "Kelas {$rombelNama} sudah memiliki jadwal {$mapelNama} bersama {$guruNama} pada jam ke-{$schedule->jam_mulai_ke}–{$schedule->jam_selesai_ke} ({$hariNama}).",
                    conflictingSchedule: $schedule,
                ));
            }
        }

        return $result;
    }
}
