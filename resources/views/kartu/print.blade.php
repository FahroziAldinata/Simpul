<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @font-face {
            font-family: 'Plus Jakarta Sans';
            font-style: normal;
            font-weight: 400;
            src: url('data:font/woff2;base64,{{ $fontRegularBase64 }}') format('woff2');
        }
        @font-face {
            font-family: 'Plus Jakarta Sans';
            font-style: normal;
            font-weight: 700;
            src: url('data:font/woff2;base64,{{ $fontBoldBase64 }}') format('woff2');
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            height: 297mm;
            padding: 10mm 10mm;
            display: grid;
            grid-template-columns: repeat(2, 85.6mm);
            grid-template-rows: repeat(4, 54mm);
            column-gap: 18.8mm;
            row-gap: 6.8mm;
            page-break-after: always;
            break-after: page;
            overflow: hidden;
        }

        .sheet:last-of-type {
            page-break-after: avoid;
            break-after: avoid;
        }

        .card {
            width: 85.6mm;
            height: 54mm;
            border: 1px dashed #cbd5e1;
            border-radius: 3.5mm;
            padding: 2.2mm 2.8mm;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            position: relative;
        }

        /* Top accent border */
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2.2mm;
            background: {{ $accentColor ?? '#1e40af' }};
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.2mm;
            padding-bottom: 1.2mm;
            border-bottom: 0.5px solid #e2e8f0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.8mm;
            max-width: 58mm;
        }

        .school-logo-box {
            width: 7mm;
            height: 7mm;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .school-logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .school-logo-box svg {
            width: 100%;
            height: 100%;
        }

        .header-text {
            overflow: hidden;
        }

        .school-name {
            font-size: 6.5pt;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .school-meta {
            font-size: 4.8pt;
            color: #64748b;
            line-height: 1.1;
            margin-top: 0.2mm;
        }

        .card-badge {
            font-size: 5pt;
            font-weight: 700;
            color: #ffffff;
            background: {{ $accentColor ?? '#1e40af' }};
            padding: 0.8mm 1.6mm;
            border-radius: 1mm;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.2mm 0;
            height: 31mm;
            gap: 2mm;
        }

        .card-photo-box {
            width: 21mm;
            height: 28mm;
            border-radius: 1.8mm;
            overflow: hidden;
            border: 0.5px solid #cbd5e1;
            background: #f8fafc;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-photo-box svg {
            width: 100%;
            height: 100%;
        }

        .card-info {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .student-name {
            font-size: 7.2pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 1.2mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 5.4pt;
            line-height: 1.25;
        }

        .info-table td {
            padding: 0.2mm 0;
            vertical-align: top;
        }

        .info-table .lbl {
            color: #64748b;
            width: 11mm;
            white-space: nowrap;
        }

        .info-table .sep {
            color: #94a3b8;
            width: 2mm;
            text-align: center;
        }

        .info-table .val {
            color: #1e293b;
            word-break: break-word;
        }

        .card-qr-box {
            width: 19mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .qr-svg-wrapper {
            width: 18mm;
            height: 18mm;
        }

        .qr-svg-wrapper svg {
            width: 100%;
            height: 100%;
        }

        .qr-label {
            font-size: 4.2pt;
            color: #64748b;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-top: 0.8mm;
            text-align: center;
            text-transform: uppercase;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 0.5px solid #e2e8f0;
            padding-top: 0.8mm;
            font-size: 4.5pt;
            color: #94a3b8;
            line-height: 1;
        }

        .footer-left {
            font-weight: 600;
            color: #64748b;
        }

        .footer-right {
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
    </style>
</head>
<body>
    @foreach ($sheets as $cards)
        <div class="sheet">
            @foreach ($cards as $item)
                <div class="card">
                    <div class="card-header">
                        <div class="header-left">
                            <div class="school-logo-box">
                                @if (!empty($sekolah['logo_base64']))
                                    <img src="{{ $sekolah['logo_base64'] }}" alt="Logo">
                                @else
                                    {!! $fallbackLogoSvg !!}
                                @endif
                            </div>
                            <div class="header-text">
                                <div class="school-name">{{ $sekolah['nama'] }}</div>
                                <div class="school-meta">NPSN: {{ $sekolah['npsn'] }} &bull; {{ $sekolah['jenjang'] }}</div>
                            </div>
                        </div>
                        <div class="card-badge">{{ $isSiswa ? 'KARTU PELAJAR' : 'KARTU PEGAWAI' }}</div>
                    </div>

                    <div class="card-body">
                        <div class="card-photo-box">
                            @if (!empty($item['foto_base64']))
                                <img src="{{ $item['foto_base64'] }}" alt="Foto">
                            @else
                                {!! $fallbackPhotoSvg !!}
                            @endif
                        </div>

                        <div class="card-info">
                            <div class="student-name">{{ $item['nama'] }}</div>
                            <table class="info-table">
                                @if ($isSiswa)
                                    <tr>
                                        <td class="lbl">NISN</td>
                                        <td class="sep">:</td>
                                        <td class="val"><strong>{{ $item['nisn'] ?? '-' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">NIK</td>
                                        <td class="sep">:</td>
                                        <td class="val">{{ $item['nik'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Kelas</td>
                                        <td class="sep">:</td>
                                        <td class="val">{{ $item['rombel'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">TTL</td>
                                        <td class="sep">:</td>
                                        <td class="val">{{ $item['ttl'] ?? '-' }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="lbl">NIP</td>
                                        <td class="sep">:</td>
                                        <td class="val"><strong>{{ $item['nip'] ?? '-' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">NUPTK</td>
                                        <td class="sep">:</td>
                                        <td class="val">{{ $item['nuptk'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Jabatan</td>
                                        <td class="sep">:</td>
                                        <td class="val">{{ $item['jenis'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Status</td>
                                        <td class="sep">:</td>
                                        <td class="val">{{ $item['status_kepegawaian'] ?? '-' }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>

                        <div class="card-qr-box">
                            <div class="qr-svg-wrapper">
                                {!! $item['qr_svg'] !!}
                            </div>
                            <div class="qr-label">PINDAI VERIFIKASI</div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="footer-left">{{ $sekolah['semester_aktif'] }}</div>
                        <div class="footer-right">SIMPUL ID RESMI</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
