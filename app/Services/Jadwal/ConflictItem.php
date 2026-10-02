<?php

namespace App\Services\Jadwal;

use App\Models\JadwalPelajaran;

class ConflictItem
{
    public function __construct(
        public string $type, // 'guru' | 'ruang' | 'rombel'
        public string $message,
        public ?JadwalPelajaran $conflictingSchedule = null,
    ) {}
}
