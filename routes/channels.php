<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('sekolah.{sekolahId}.impor.{batchId}', function (User $user, string $sekolahId, string $batchId): bool {
    $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : (string) $user->sekolah_id;

    if ((string) $activeSekolahId !== (string) $sekolahId) {
        return false;
    }

    return $user->hasRole('super_admin') || $user->hasRole('operator') || $user->can('siswa.create');
});
