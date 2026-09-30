<?php

use App\Services\Impor\RowNormalizer;

test('RowNormalizer menangani NISN notasi ilmiah dan leading zero', function () {
    $normalizer = new RowNormalizer;

    // Scientific notation
    expect($normalizer->normalizeNisn('8.12345678E+09'))->toBe('8123456780');
    expect($normalizer->normalizeNisn(8123456780.0))->toBe('8123456780');

    // Leading zero lost in Excel (9 digits) -> padded to 10
    expect($normalizer->normalizeNisn('812345678'))->toBe('0812345678');
    expect($normalizer->normalizeNisn('12345678'))->toBe('0012345678');
});

test('RowNormalizer membersihkan NIK', function () {
    $normalizer = new RowNormalizer;

    expect($normalizer->normalizeNik('3201-0123-0408-0001'))->toBe('3201012304080001');
    expect($normalizer->normalizeNik('3201 0123 0408 0001'))->toBe('3201012304080001');
});

test('RowNormalizer mengubah nama ke Title Case dan merapikan spasi', function () {
    $normalizer = new RowNormalizer;

    expect($normalizer->normalizeNama('MUHAMMAD  RIZKY PRATAMA'))->toBe('Muhammad Rizky Pratama');
    expect($normalizer->normalizeNama('siti nurhaliza'))->toBe('Siti Nurhaliza');
});

test('RowNormalizer menormalisasi jenis kelamin', function () {
    $normalizer = new RowNormalizer;

    expect($normalizer->normalizeJenisKelamin('Laki-laki'))->toBe('L');
    expect($normalizer->normalizeJenisKelamin('LAKI-LAKI'))->toBe('L');
    expect($normalizer->normalizeJenisKelamin('pria'))->toBe('L');
    expect($normalizer->normalizeJenisKelamin('Perempuan'))->toBe('P');
    expect($normalizer->normalizeJenisKelamin('wanita'))->toBe('P');
});

test('RowNormalizer mem-parsing berbagai format tanggal lahir', function () {
    $normalizer = new RowNormalizer;

    expect($normalizer->normalizeTanggalLahir('2008-05-17'))->toBe('2008-05-17');
    expect($normalizer->normalizeTanggalLahir('17/05/2008'))->toBe('2008-05-17');
    expect($normalizer->normalizeTanggalLahir('17-05-2008'))->toBe('2008-05-17');
    expect($normalizer->normalizeTanggalLahir('17 Mei 2008'))->toBe('2008-05-17');
    expect($normalizer->normalizeTanggalLahir('5 Agustus 2007'))->toBe('2007-08-05');
});

test('RowNormalizer menstandarkan nomor HP', function () {
    $normalizer = new RowNormalizer;

    expect($normalizer->normalizeNoHp('+62 812-3456-7890'))->toBe('081234567890');
    expect($normalizer->normalizeNoHp('6281234567890'))->toBe('081234567890');
    expect($normalizer->normalizeNoHp('0812 3456 7890'))->toBe('081234567890');
});
