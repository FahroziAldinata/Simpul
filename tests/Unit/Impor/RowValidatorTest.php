<?php

use App\Services\Impor\RowValidator;

test('RowValidator meloloskan data valid sebagai valid', function () {
    $validator = new RowValidator;

    $row = [
        'nisn' => '0081234567',
        'nik' => '3201011705080001', // male, born 17-05-08
        'nama' => 'Ahmad Dahlan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Yogyakarta',
        'tanggal_lahir' => '2008-05-17',
        'agama' => 'Islam',
    ];

    $result = $validator->validateRow($row);

    expect($result['status'])->toBe('valid');
    expect($result['errors'])->toBeEmpty();
    expect($result['warnings'])->toBeEmpty();
});

test('RowValidator memunculkan peringatan ketika NIK tidak cocok dengan tanggal lahir', function () {
    $validator = new RowValidator;

    $row = [
        'nisn' => '0081234567',
        'nik' => '3201011705080001', // NIK indicates 17-05-2008
        'nama' => 'Ahmad Dahlan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Yogyakarta',
        'tanggal_lahir' => '2008-08-20', // Date does NOT match NIK
        'agama' => 'Islam',
    ];

    $result = $validator->validateRow($row);

    expect($result['status'])->toBe('peringatan');
    expect($result['errors'])->toBeEmpty();
    expect($result['warnings'])->toHaveKey('nik');
});

test('RowValidator menandai gagal bila NISN atau NIK salah format', function () {
    $validator = new RowValidator;

    $row = [
        'nisn' => '123', // not 10 digits
        'nik' => '3201', // not 16 digits
        'nama' => '',
        'jenis_kelamin' => 'X',
        'tempat_lahir' => '',
        'tanggal_lahir' => 'invalid-date',
        'agama' => 'Atheis',
    ];

    $result = $validator->validateRow($row);

    expect($result['status'])->toBe('gagal');
    expect($result['errors'])->toHaveKeys(['nisn', 'nik', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama']);
});

test('RowValidator memberikan pesan netral bila duplikat lintas tenant', function () {
    $validator = new RowValidator;

    $row = [
        'nisn' => '0081234567',
        'nik' => '3201011705080001',
        'nama' => 'Ahmad Dahlan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Yogyakarta',
        'tanggal_lahir' => '2008-05-17',
        'agama' => 'Islam',
    ];

    $result = $validator->validateRow(
        data: $row,
        dbDuplicateOtherSchool: true
    );

    expect($result['status'])->toBe('gagal');
    expect($result['errors']['nisn'])->toBe('NISN sudah terdaftar di sistem. Hubungi Super Admin untuk verifikasi lebih lanjut.');
});
