<?php

namespace App\Policies;

use App\Models\Pegawai;
use App\Models\User;

class PegawaiPolicy
{
    /**
     * Determine whether the user can view any employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum']);
    }

    /**
     * Determine whether the user can view the employee.
     */
    public function view(User $user, Pegawai $pegawai): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $pegawai->sekolah_id !== $activeSekolahId) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum'])) {
            return true;
        }

        // Guru / Wali Kelas can view self (R³ in PRD 4.2)
        return $pegawai->user_id === $user->id;
    }

    /**
     * Determine whether the user can create employees.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'operator']) || $user->can('pegawai.create');
    }

    /**
     * Determine whether the user can update the employee.
     */
    public function update(User $user, Pegawai $pegawai): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $pegawai->sekolah_id !== $activeSekolahId) {
            return false;
        }

        return $user->hasRole(['super_admin', 'operator']) || $user->can('pegawai.update');
    }

    /**
     * Determine whether the user can delete the employee.
     */
    public function delete(User $user, Pegawai $pegawai): bool
    {
        // Tenant isolation
        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if ($activeSekolahId && $pegawai->sekolah_id !== $activeSekolahId) {
            return false;
        }

        return $user->hasRole(['super_admin', 'operator']) || $user->can('pegawai.delete');
    }
}
