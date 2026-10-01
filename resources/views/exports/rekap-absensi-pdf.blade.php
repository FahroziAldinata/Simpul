<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi {{ $bulan }}/{{ $tahun }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; padding: 20mm 15mm; }

        /* Kop Sekolah */
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
        .kop p { font-size: 9pt; color: #333; }

        /* Judul Laporan */
        .judul { text-align: center; margin: 10px 0 14px; }
        .judul h2 { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .judul p { font-size: 9pt; }

        /* Tabel Data */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #555; padding: 4px 6px; text-align: center; vertical-align: middle; }
        th { background-color: #d1fae5; font-weight: bold; }
        td.nama { text-align: left; }
        td.nip { font-family: monospace; text-align: left; }

        /* Status warna */
        .H { background: #dcfce7; color: #166534; font-weight: bold; }
        .T { background: #fef9c3; color: #854d0e; font-weight: bold; }
        .S { background: #f3e8ff; color: #581c87; font-weight: bold; }
        .I { background: #dbeafe; color: #1e40af; font-weight: bold; }
        .C { background: #cffafe; color: #155e75; font-weight: bold; }
        .D { background: #e0e7ff; color: #312e81; font-weight: bold; }
        .A { background: #fee2e2; color: #991b1b; font-weight: bold; }

        /* Tanda Tangan */
        .tanda-tangan { margin-top: 30px; display: flex; justify-content: flex-end; }
        .tanda-tangan .blok { text-align: center; width: 220px; }
        .tanda-tangan .blok .kota-tanggal { margin-bottom: 60px; }
        .tanda-tangan .blok .garis { border-top: 1px solid #000; padding-top: 4px; }
        .tanda-tangan .blok p { font-size: 9pt; }

        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>

<!-- Kop Sekolah -->
<div class="kop">
    <h1>{{ $sekolah?->nama ?? 'Nama Sekolah' }}</h1>
    <p>NPSN: {{ $sekolah?->npsn ?? '-' }}</p>
    <p>{{ $sekolah?->alamat ?? '' }}</p>
</div>

<!-- Judul -->
<div class="judul">
    <h2>Rekap Absensi Pegawai</h2>
    <p>Bulan: {{ \Carbon\Carbon::create($tahun, $bulan, 1)->isoFormat('MMMM YYYY') }}</p>
</div>

<!-- Tabel Ringkasan -->
<table>
    <thead>
        <tr>
            <th rowspan="2" style="width:30px">No</th>
            <th rowspan="2">Nama Pegawai</th>
            <th rowspan="2">NIP</th>
            <th rowspan="2">Jenis</th>
            <th colspan="7">Status Kehadiran</th>
            <th rowspan="2">Menit Terlambat</th>
        </tr>
        <tr>
            <th class="H">H</th>
            <th class="T">T</th>
            <th class="S">S</th>
            <th class="I">I</th>
            <th class="C">C</th>
            <th class="D">D</th>
            <th class="A">A</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rekap['baris'] as $i => $baris)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td class="nama">{{ $baris['nama'] }}</td>
            <td class="nip">{{ $baris['nip'] ?? '-' }}</td>
            <td>{{ ucfirst($baris['jenis']) }}</td>
            <td class="H">{{ $baris['ringkasan']['H'] }}</td>
            <td class="T">{{ $baris['ringkasan']['T'] }}</td>
            <td class="S">{{ $baris['ringkasan']['S'] }}</td>
            <td class="I">{{ $baris['ringkasan']['I'] }}</td>
            <td class="C">{{ $baris['ringkasan']['C'] }}</td>
            <td class="D">{{ $baris['ringkasan']['D'] }}</td>
            <td class="A">{{ $baris['ringkasan']['A'] }}</td>
            <td>{{ $baris['ringkasan']['total_menit'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="12" style="text-align:center;color:#888">Tidak ada data pegawai.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Legenda -->
<p style="font-size:8pt;color:#555;margin-bottom:20px">
    <strong>Keterangan:</strong>
    H = Hadir &nbsp;|&nbsp; T = Terlambat &nbsp;|&nbsp; S = Sakit &nbsp;|&nbsp;
    I = Izin &nbsp;|&nbsp; C = Cuti &nbsp;|&nbsp; D = Dinas &nbsp;|&nbsp; A = Alfa/Tidak Hadir
</p>

<!-- Blok Tanda Tangan -->
<div class="tanda-tangan">
    <div class="blok">
        <p class="kota-tanggal">{{ $sekolah?->alamat ? explode(',', $sekolah->alamat)[0] : '' }}, {{ \Carbon\Carbon::now()->isoFormat('D MMMM YYYY') }}</p>
        <p style="margin-bottom:4px">Kepala Sekolah,</p>
        <div style="height:60px"></div>
        <div class="garis">
            <p><strong>( ________________________ )</strong></p>
        </div>
    </div>
</div>

</body>
</html>
