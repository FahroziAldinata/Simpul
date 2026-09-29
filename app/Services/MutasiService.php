<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Models\AnggotaRombel;
use App\Models\MutasiSiswa;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MutasiService
{
    /**
     * Execute a student mutation and manage class memberships and status accordingly.
     *
     * @throws ValidationException
     */
    public function executeMutasi(
        Siswa $siswa,
        JenisMutasi $tipe,
        Carbon|string $tanggal,
        ?string $alasan = null,
        ?string $asalSekolah = null,
        ?string $sekolahTujuan = null,
        ?string $keRombelId = null,
        ?string $semesterId = null
    ): MutasiSiswa {
        return DB::transaction(function () use (
            $siswa,
            $tipe,
            $tanggal,
            $alasan,
            $asalSekolah,
            $sekolahTujuan,
            $keRombelId,
            $semesterId
        ) {
            $parsedDate = $tanggal instanceof Carbon ? $tanggal : Carbon::parse($tanggal);

            /** @var Semester|null $semester */
            $semester = null;

            if ($keRombelId !== null) {
                /** @var Rombel $keRombel */
                $keRombel = Rombel::with('semester')
                    ->where('sekolah_id', $siswa->sekolah_id)
                    ->findOrFail($keRombelId);

                $semester = $keRombel->semester;

                if (! $semester || ! $semester->is_aktif) {
                    throw ValidationException::withMessages([
                        'ke_rombel_id' => 'Mutasi tidak dapat dilakukan pada semester yang sudah diarsipkan atau non-aktif.',
                    ]);
                }
            } else {
                if ($semesterId !== null) {
                    $semester = Semester::where('sekolah_id', $siswa->sekolah_id)->findOrFail($semesterId);
                } else {
                    $semester = Semester::where('sekolah_id', $siswa->sekolah_id)
                        ->where('is_aktif', true)
                        ->first();
                }

                if (! $semester || ! $semester->is_aktif) {
                    throw ValidationException::withMessages([
                        'semester_id' => 'Mutasi tidak dapat dilakukan pada semester yang sudah diarsipkan atau non-aktif.',
                    ]);
                }
            }

            // Snapshot data sebelum mutasi
            $statusSebelum = $siswa->status->value;

            /** @var AnggotaRombel|null $anggotaRombel */
            $anggotaRombel = AnggotaRombel::where('semester_id', $semester->id)
                ->where('siswa_id', $siswa->id)
                ->first();

            $rombelIdSebelum = $anggotaRombel?->rombel_id;
            $anggotaRombelDibuatBaru = false;

            if ($tipe === JenisMutasi::PindahRombel && $rombelIdSebelum === $keRombelId) {
                throw ValidationException::withMessages([
                    'ke_rombel_id' => 'Rombel tujuan tidak boleh sama dengan rombel saat ini.',
                ]);
            }

            // Mutasi logic according to type
            switch ($tipe) {
                case JenisMutasi::Masuk:
                case JenisMutasi::PindahRombel:
                case JenisMutasi::NaikKelas:
                case JenisMutasi::TinggalKelas:
                    if (! $keRombelId) {
                        throw ValidationException::withMessages([
                            'ke_rombel_id' => 'Rombel tujuan wajib dipilih untuk jenis mutasi ini.',
                        ]);
                    }

                    $siswa->status = StatusSiswa::Aktif;

                    if ($anggotaRombel) {
                        $anggotaRombel->update([
                            'rombel_id' => $keRombelId,
                        ]);
                    } else {
                        AnggotaRombel::create([
                            'sekolah_id' => $siswa->sekolah_id,
                            'rombel_id' => $keRombelId,
                            'siswa_id' => $siswa->id,
                            'semester_id' => $semester->id,
                        ]);
                        $anggotaRombelDibuatBaru = true;
                    }
                    break;

                case JenisMutasi::Keluar:
                    $siswa->status = StatusSiswa::Pindah;
                    // US-13 AC1: baris anggota_rombel TIDAK dihapus, tetap ada sebagai riwayat historis
                    break;

                case JenisMutasi::Lulus:
                    $siswa->status = StatusSiswa::Lulus;
                    // US-13 AC1: baris anggota_rombel TIDAK dihapus, tetap ada sebagai riwayat historis
                    break;

                case JenisMutasi::DropOut:
                    $siswa->status = StatusSiswa::DropOut;
                    // US-13 AC1: baris anggota_rombel TIDAK dihapus, tetap ada sebagai riwayat historis
                    break;
            }

            $siswa->save();

            return MutasiSiswa::create([
                'sekolah_id' => $siswa->sekolah_id,
                'siswa_id' => $siswa->id,
                'semester_id' => $semester->id,
                'tipe' => $tipe,
                'tanggal' => $parsedDate,
                'alasan' => $alasan,
                'asal_sekolah' => $asalSekolah,
                'sekolah_tujuan' => $sekolahTujuan,
                'dari_rombel_id' => $rombelIdSebelum,
                'ke_rombel_id' => $keRombelId,
                'status_sebelum' => $statusSebelum,
                'rombel_id_sebelum' => $rombelIdSebelum,
                'anggota_rombel_dibuat_baru' => $anggotaRombelDibuatBaru,
            ]);
        });
    }

    /**
     * Cancel / revert an executed mutation using LIFO policy and dual-path restoration.
     *
     * @throws ValidationException
     */
    public function batalkanMutasi(MutasiSiswa $mutasi, User $user, string $alasanBatal): MutasiSiswa
    {
        return DB::transaction(function () use ($mutasi, $user, $alasanBatal) {
            if ($mutasi->is_batal) {
                throw ValidationException::withMessages([
                    'mutasi' => 'Mutasi ini sudah dibatalkan sebelumnya.',
                ]);
            }

            // LIFO Rule: Only the latest uncancelled mutation for this student can be cancelled
            $hasNewer = MutasiSiswa::where('siswa_id', $mutasi->siswa_id)
                ->where('is_batal', false)
                ->where('id', '!=', $mutasi->id)
                ->where(function ($query) use ($mutasi) {
                    $query->where('tanggal', '>', $mutasi->tanggal)
                        ->orWhere(function ($q2) use ($mutasi) {
                            $q2->where('tanggal', $mutasi->tanggal)
                                ->where('created_at', '>', $mutasi->created_at);
                        });
                })
                ->exists();

            if ($hasNewer) {
                throw ValidationException::withMessages([
                    'mutasi' => 'Hanya mutasi terbaru yang dapat dibatalkan. Terdapat mutasi yang lebih baru yang belum dibatalkan untuk siswa ini.',
                ]);
            }

            /** @var Siswa $siswa */
            $siswa = $mutasi->siswa;

            // Kembalikan status siswa ke status_sebelum
            if ($mutasi->status_sebelum !== null) {
                $statusEnum = StatusSiswa::tryFrom($mutasi->status_sebelum);
                if ($statusEnum) {
                    $siswa->status = $statusEnum;
                    $siswa->save();
                }
            }

            // Jalur Dual-Path Pembatalan:
            if ($mutasi->anggota_rombel_dibuat_baru) {
                // Jalur A: Hapus baris anggota_rombel yang dibuat oleh mutasi tersebut
                AnggotaRombel::where('semester_id', $mutasi->semester_id)
                    ->where('siswa_id', $mutasi->siswa_id)
                    ->delete();
            } else {
                // Jalur B: Kembalikan rombel_id ke rombel_id_sebelum
                if ($mutasi->rombel_id_sebelum !== null) {
                    /** @var AnggotaRombel|null $anggota */
                    $anggota = AnggotaRombel::where('semester_id', $mutasi->semester_id)
                        ->where('siswa_id', $mutasi->siswa_id)
                        ->first();

                    if ($anggota) {
                        $anggota->update([
                            'rombel_id' => $mutasi->rombel_id_sebelum,
                        ]);
                    }
                }
            }

            $mutasi->update([
                'is_batal' => true,
                'alasan_batal' => $alasanBatal,
                'dibatalkan_oleh' => $user->id,
                'dibatalkan_at' => Carbon::now(),
            ]);

            return $mutasi->fresh();
        });
    }
}
