<?php

use App\Services\Impor\DuplicateDetector;

test('DuplicateDetector mendeteksi duplikat di dalam file', function () {
    $detector = new DuplicateDetector;

    $rows = [
        ['nomor_baris' => 2, 'data_bersih' => ['nisn' => '0081234567']],
        ['nomor_baris' => 3, 'data_bersih' => ['nisn' => '0089999999']],
        ['nomor_baris' => 4, 'data_bersih' => ['nisn' => '0081234567']], // duplicate with row 2
    ];

    $inFiles = $detector->detectInFileDuplicates($rows);

    expect($inFiles)->toHaveKey(2);
    expect($inFiles[2])->toBe([4]);
    expect($inFiles)->toHaveKey(4);
    expect($inFiles[4])->toBe([2]);
    expect($inFiles)->not->toHaveKey(3);
});
