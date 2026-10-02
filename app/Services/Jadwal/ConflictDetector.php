<?php

namespace App\Services\Jadwal;

use App\Models\JadwalPelajaran;

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
     * Cek bentrok jadwal di level aplikasi sebelum eksekusi ke database.
     *
     * @param  array{
     *     sekolah_id: string,
     *     semester_id: string,
     *     hari: int,
     *     jam_mulai_ke: int,
     *     jam_selesai_ke: int,
     *     guru_id: string,
     *     rombel_id: string,
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
        $ruangId = $data['ruang_id'] ?? null;

        $hariNama = self::NAMA_HARI[$hari] ?? "Hari ke-{$hari}";

        // 1. Cek H1: Bentrok Guru
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

        // 2. Cek H2: Bentrok Ruang (hanya jika ruang_id diisi)
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

        // 3. Cek H3: Bentrok Rombel
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
     * Deteksi bentrok dalam memori (misal untuk batch/client-side data checking).
     *
     * @param  array{
     *     hari: int,
     *     jam_mulai_ke: int,
     *     jam_selesai_ke: int,
     *     guru_id: string,
     *     rombel_id: string,
     *     ruang_id?: string|null,
     * }  $candidate
     * @param  iterable<JadwalPelajaran>  $existingSchedules
     */
    public function checkInMemory(array $candidate, iterable $existingSchedules, ?string $ignoreId = null): ConflictResult
    {
        $result = new ConflictResult;

        $hari = (int) $candidate['hari'];
        $jamMulai = (int) $candidate['jam_mulai_ke'];
        $jamSelesai = (int) $candidate['jam_selesai_ke'];
        $guruId = $candidate['guru_id'];
        $rombelId = $candidate['rombel_id'];
        $ruangId = $candidate['ruang_id'] ?? null;

        $hariNama = self::NAMA_HARI[$hari] ?? "Hari ke-{$hari}";

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
