<?php

use App\Services\Impor\ColumnMapper;

test('ColumnMapper memprediksi header umum dengan akurasi tinggi', function () {
    $mapper = new ColumnMapper;

    $headers = [
        'No',
        'NISN',
        'No. NIK Siswa',
        'Nama Lengkap',
        'Jenis Kelamin (L/P)',
        'Kota Kelahiran',
        'Tgl Lahir',
        'Agama',
        'Alamat Domisili',
        'Nomor WhatsApp',
        'Rombongan Belajar',
    ];

    $prediction = $mapper->predict($headers);

    expect($prediction['NISN'])->toBe('nisn');
    expect($prediction['No. NIK Siswa'])->toBe('nik');
    expect($prediction['Nama Lengkap'])->toBe('nama');
    expect($prediction['Jenis Kelamin (L/P)'])->toBe('jenis_kelamin');
    expect($prediction['Tgl Lahir'])->toBe('tanggal_lahir');
    expect($prediction['Agama'])->toBe('agama');
    expect($prediction['Alamat Domisili'])->toBe('alamat');
    expect($prediction['Nomor WhatsApp'])->toBe('no_hp');
    expect($prediction['Rombongan Belajar'])->toBe('rombel');
});

test('ColumnMapper menangani typo menggunakan Levenshtein', function () {
    $mapper = new ColumnMapper;

    // Typos
    expect($mapper->matchSingleHeader('Namaz Lengkap'))->toBe('nama');
    expect($mapper->matchSingleHeader('Tempat Lahir'))->toBe('tempat_lahir');
    expect($mapper->matchSingleHeader('Tangal Lahir'))->toBe('tanggal_lahir');
});
