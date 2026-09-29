<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\User;

class SiswaPolicy
{
    /**
     * Determine whether the user can view any students.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum', 'wali_kelas']);
    }

    /**
     * Determine whether the user can view the student.
     */
    public function view(User $user, Siswa $siswa): bool
    {
        // Tenant isolation: student must belong to user active school
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum'])) {
            return true;
        }

        if ($user->hasRole('wali_kelas')) {
            $pegawaiId = $user->pegawai?->id;
            if (! $pegawaiId) {
                return false;
            }

            return $siswa->anggotaRombel()
                ->whereHas('rombel', function ($q) use ($pegawaiId) {
                    $q->where('wali_kelas_id', $pegawaiId);
                })
                ->whereHas('semester', function ($q) {
                    $q->where('is_aktif', true);
                })
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create students.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'operator']) || $user->can('siswa.create');
    }

    /**
     * Determine whether the user can update the student.
     */
    public function update(User $user, Siswa $siswa): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'operator'])) {
            return true;
        }

        if ($user->hasRole('wali_kelas')) {
            $pegawaiId = $user->pegawai?->id;
            if (! $pegawaiId) {
                return false;
            }

            return $siswa->anggotaRombel()
                ->whereHas('rombel', function ($q) use ($pegawaiId) {
                    $q->where('wali_kelas_id', $pegawaiId);
                })
                ->whereHas('semester', function ($q) {
                    $q->where('is_aktif', true);
                })
                ->exists();
        }

        return $user->can('siswa.update');
    }

    /**
     * Determine whether the user can delete the student.
     */
    public function delete(User $user, Siswa $siswa): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            return false;
        }

        return $user->hasRole(['super_admin', 'operator']) || $user->can('siswa.delete');
    }

    /**
     * Determine whether the user can view/download student documents.
     */
    public function viewBerkas(User $user, Siswa $siswa): bool
    {
        return $this->view($user, $siswa);
    }

    /**
     * Determine whether the user can upload/replace student documents.
     */
    public function uploadBerkas(User $user, Siswa $siswa): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            return false;
        }

        // Wali Kelas is strictly read-only on berkas
        if ($user->hasRole('wali_kelas') && ! $user->hasRole(['super_admin', 'operator'])) {
            return false;
        }

        return $user->hasRole(['super_admin', 'operator']) || $user->can('siswa.update');
    }

    /**
     * Determine whether the user can delete student documents.
     */
    public function deleteBerkas(User $user, Siswa $siswa): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            return false;
        }

        // Wali Kelas is strictly read-only on berkas
        if ($user->hasRole('wali_kelas') && ! $user->hasRole(['super_admin', 'operator'])) {
            return false;
        }

        return $user->hasRole(['super_admin', 'operator']) || $user->can('siswa.delete');
    }

    /**
     * Determine whether the user can view student mutation history.
     */
    public function viewMutasi(User $user, Siswa $siswa): bool
    {
        return $this->view($user, $siswa);
    }

    /**
     * Determine whether the user can manage (execute or cancel) student mutations.
     */
    public function manageMutasi(User $user, Siswa $siswa): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $siswa->sekolah_id !== $activeSekolahId) {
            return false;
        }

        // Wali Kelas is strictly barred from student mutations
        if ($user->hasRole('wali_kelas') && ! $user->hasRole(['super_admin', 'operator'])) {
            return false;
        }

        return $user->hasRole(['super_admin', 'operator']) || $user->can('siswa.update');
    }
}
