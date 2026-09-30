<?php

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Sekolah;
use App\Models\User;

test('import batch dan import rows dapat dibuat dan berelasi dengan benar', function () {
    $sekolah = Sekolah::factory()->create();
    $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

    $batch = ImportBatch::factory()->create([
        'sekolah_id' => $sekolah->id,
        'user_id' => $user->id,
        'tipe' => 'siswa',
        'nama_file' => 'siswa_2026.xlsx',
        'status' => 'uploaded',
    ]);

    $row1 = ImportRow::factory()->create([
        'import_batch_id' => $batch->id,
        'nomor_baris' => 2,
        'status' => 'valid',
    ]);

    $row2 = ImportRow::factory()->create([
        'import_batch_id' => $batch->id,
        'nomor_baris' => 3,
        'status' => 'gagal',
        'errors' => ['nisn' => 'NISN harus 10 digit'],
    ]);

    expect($batch->user->id)->toBe($user->id);
    expect($batch->sekolah->id)->toBe($sekolah->id);
    expect($batch->rows)->toHaveCount(2);
    expect($row1->batch->id)->toBe($batch->id);
    expect($row2->errors)->toBe(['nisn' => 'NISN harus 10 digit']);
});
