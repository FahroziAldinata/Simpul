<?php

namespace App\Policies;

use App\Models\JadwalPelajaran;
use App\Models\User;

class JadwalPelajaranPolicy
{
    /**
     * Resolve active sekolah_id for the user.
     */
    private function resolveSekolahId(User $user): ?string
    {
        return $user->hasRole('super_admin') ? (session('sekolah_id') ?? $user->sekolah_id) : $user->sekolah_id;
    }

    /**
     * Determine whether the user can view any schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'waka_kurikulum', 'operator', 'kepsek', 'guru', 'wali_kelas', 'orang_tua'])
            || $user->can('jadwal.view');
    }

    /**
     * Determine whether the user can view the schedule.
     */
    public function view(User $user, JadwalPelajaran $jadwal): bool
    {
        $activeSekolahId = $this->resolveSekolahId($user);
        if ($activeSekolahId && $jadwal->sekolah_id !== $activeSekolahId) {
            return false;
        }

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create schedules.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'waka_kurikulum']) || $user->can('jadwal.create');
    }

    /**
     * Determine whether the user can update the schedule.
     */
    public function update(User $user, JadwalPelajaran $jadwal): bool
    {
        $activeSekolahId = $this->resolveSekolahId($user);
        if ($activeSekolahId && $jadwal->sekolah_id !== $activeSekolahId) {
            return false;
        }

        return $user->hasRole(['super_admin', 'waka_kurikulum']) || $user->can('jadwal.update');
    }

    /**
     * Determine whether the user can delete the schedule.
     */
    public function delete(User $user, JadwalPelajaran $jadwal): bool
    {
        $activeSekolahId = $this->resolveSekolahId($user);
        if ($activeSekolahId && $jadwal->sekolah_id !== $activeSekolahId) {
            return false;
        }

        return $user->hasRole(['super_admin', 'waka_kurikulum']) || $user->can('jadwal.delete');
    }
}
