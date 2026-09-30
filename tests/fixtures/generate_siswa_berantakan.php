<?php

require_once __DIR__.'/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet;
$sheet = $spreadsheet->getActiveSheet();

// Row 1: Merged Title Header (Testing Merged Header detection in PRD 6.3)
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', 'DATA CALON SISWA BARU TP 2026/2027 (KACAU & BERANTAKAN)');

// Row 2: Column Headers
$headers = [
    'No. NISN Siswa',
    'Nomor NIK',
    'Nama Siswa',
    'Jenis Kelamin (L/P)',
    'Kota Lahir',
    'Tgl Lahir',
    'Agama',
    'Alamat Rumah',
    'Nomor HP / WhatsApp',
    'Rombongan Belajar',
];

foreach ($headers as $colIdx => $header) {
    $col = Coordinate::stringFromColumnIndex($colIdx + 1);
    $sheet->setCellValue("{$col}2", $header);
}

$rows = [];
$firstNames = ['Ahmad', 'Budi', 'Candra', 'Deni', 'Eko', 'Fajar', 'Gilang', 'Hadi', 'Indra', 'Joko', 'Kurniawan', 'Lukman', 'Maulana', 'Naufal', 'Oki', 'Panji', 'Rian', 'Siti', 'Dewi', 'Nur', 'Putri', 'Rina', 'Mega', 'Tari', 'Wulan', 'Yulia'];
$lastNames = ['Pratama', 'Santoso', 'Wijaya', 'Saputra', 'Setiawan', 'Hidayat', 'Kusuma', 'Nugroho', 'Wibowo', 'Firmansyah', 'Siregar', 'Lubis', 'Nasution', 'Batubara', 'Harahap', 'Gultom'];
$religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
$cities = ['Jakarta', 'Bandung', 'Surabaya', 'Semarang', 'Medan', 'Makassar', 'Palembang', 'Yogyakarta', 'Surakarta', 'Malang'];

$dateFormats = [
    'iso' => fn ($y, $m, $d) => sprintf('%04d-%02d-%02d', $y, $m, $d),
    'slash' => fn ($y, $m, $d) => sprintf('%02d/%02d/%04d', $d, $m, $y),
    'dash' => fn ($y, $m, $d) => sprintf('%02d-%02d-%04d', $d, $m, $y),
    'indo_text' => function ($y, $m, $d) {
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return sprintf('%d %s %04d', $d, $months[(int) $m], $y);
    },
];

$usedNisns = [];

// 1. Generate 742 VALID rows
for ($i = 1; $i <= 742; $i++) {
    $gender = ($i % 2 === 0) ? 'L' : 'P';
    $firstName = $firstNames[($i * 3) % count($firstNames)];
    $lastName = $lastNames[($i * 7) % count($lastNames)];

    // Vary casing in name to test title-casing
    $fullName = "$firstName $lastName";
    if ($i % 5 === 0) {
        $fullName = strtoupper($fullName);
    } elseif ($i % 7 === 0) {
        $fullName = strtolower($fullName);
    }

    $y = 2007 + ($i % 3); // 2007, 2008, 2009
    $m = 1 + ($i % 12);
    $d = 1 + ($i % 28);

    // NISN: 10 digits starting with 007, 008, or 009
    $nisnNum = sprintf('%02d%08d', $y % 100, $i);
    $nisnVal = $nisnNum;

    // Test scientific notation on some NISN rows
    if ($i % 20 === 0) {
        $nisnVal = sprintf('%.8e', (float) $nisnNum);
    } elseif ($i % 15 === 0 && str_starts_with($nisnNum, '0')) {
        // Test Excel leading-zero drop (9 digits)
        $nisnVal = substr($nisnNum, 1);
    }

    // Matching NIK:
    $nikDD = ($gender === 'P') ? $d + 40 : $d;
    $nik = sprintf('320101%02d%02d%02d%04d', $nikDD, $m, $y % 100, ($i % 9000) + 1000);

    // Vary date format
    $fmtKeys = array_keys($dateFormats);
    $fmtKey = $fmtKeys[$i % count($fmtKeys)];
    $birthDateVal = $dateFormats[$fmtKey]($y, $m, $d);

    // Vary gender representations
    $genderVal = match ($i % 4) {
        0 => ($gender === 'L' ? 'L' : 'P'),
        1 => ($gender === 'L' ? 'Laki-laki' : 'Perempuan'),
        2 => ($gender === 'L' ? 'pria' : 'wanita'),
        3 => ($gender === 'L' ? 'L' : 'P'),
    };

    $city = $cities[$i % count($cities)];
    $religion = $religions[$i % count($religions)];
    $phone = ($i % 2 === 0) ? '+62 812-'.sprintf('%04d-%04d', $i, ($i * 2) % 9999) : '08'.sprintf('%010d', $i);
    $rombel = 'Kelas X-'.(($i % 4) + 1);

    $rows[] = [
        $nisnVal,
        $nik,
        $fullName,
        $genderVal,
        $city,
        $birthDateVal,
        $religion,
        "Jl. Merdeka No. $i",
        $phone,
        $rombel,
    ];
}

// 2. Generate 18 WARNING rows (NIK birthdate mismatch)
for ($i = 1; $i <= 18; $i++) {
    $idx = 742 + $i;
    $y = 2008;
    $m = 5;
    $d = 17;

    $nisnVal = sprintf('08%08d', $idx);
    // NIK has day 10, but birth date has day 17 -> mismatch triggers warning
    $nik = sprintf('320101%02d%02d%02d%04d', 10, $m, $y % 100, 1000 + $i);
    $birthDateVal = '2008-05-17';

    $rows[] = [
        $nisnVal,
        $nik,
        "Siswa Peringatan $i",
        'L',
        'Jakarta',
        $birthDateVal,
        'Islam',
        'Jl. Melati No. '.$i,
        '08123456789'.($i % 10),
        'Kelas X-1',
    ];
}

// 3. Generate 40 FAILING rows
// 3a. 10 broken NISN (< 8 digits or letters)
for ($i = 1; $i <= 10; $i++) {
    $rows[] = [
        'NISN'.$i, // invalid format
        '3201011705080001',
        "Siswa Rusak Nisn $i",
        'L',
        'Jakarta',
        '2008-05-17',
        'Islam',
        '-',
        '-',
        'Kelas X-1',
    ];
}

// 3b. 10 invalid NIK (< 16 digits)
for ($i = 1; $i <= 10; $i++) {
    $idx = 742 + 18 + 10 + $i;
    $rows[] = [
        sprintf('008%07d', $idx),
        '32010117', // only 8 digits
        "Siswa Rusak Nik $i",
        'L',
        'Jakarta',
        '2008-05-17',
        'Islam',
        '-',
        '-',
        'Kelas X-1',
    ];
}

// 3c. 10 invalid birth date (future or text garbage)
for ($i = 1; $i <= 10; $i++) {
    $idx = 742 + 18 + 20 + $i;
    $rows[] = [
        sprintf('008%07d', $idx),
        '3201011705080001',
        "Siswa Rusak Tanggal $i",
        'L',
        'Jakarta',
        '2035-12-31', // future date
        'Islam',
        '-',
        '-',
        'Kelas X-1',
    ];
}

// 3d. 5 duplicate NISN within file (share NISN with earlier rows)
for ($i = 1; $i <= 5; $i++) {
    $rows[] = [
        '0070000001', // duplicates row 1 NISN!
        '3201011705080001',
        "Siswa Duplikat File $i",
        'L',
        'Jakarta',
        '2008-05-17',
        'Islam',
        '-',
        '-',
        'Kelas X-1',
    ];
}

// 3e. 5 invalid mandatory fields (missing name or invalid religion)
for ($i = 1; $i <= 5; $i++) {
    $idx = 742 + 18 + 35 + $i;
    $rows[] = [
        sprintf('008%07d', $idx),
        '3201011705080001',
        '', // empty name!
        'L',
        'Jakarta',
        '2008-05-17',
        'Atheis', // invalid religion!
        '-',
        '-',
        'Kelas X-1',
    ];
}

// Write data rows into spreadsheet starting at row 3
$currRow = 3;
foreach ($rows as $rowData) {
    foreach ($rowData as $cIdx => $val) {
        $col = Coordinate::stringFromColumnIndex($cIdx + 1);
        $sheet->setCellValueExplicit("{$col}{$currRow}", (string) $val, DataType::TYPE_STRING);
    }
    $currRow++;
}

$outputFile = __DIR__.'/siswa_berantakan.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($outputFile);

echo "Successfully generated $outputFile with ".count($rows).' rows ('.($currRow - 3)." data rows).\n";
