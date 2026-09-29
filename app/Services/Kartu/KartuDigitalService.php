<?php

namespace App\Services\Kartu;

use App\Enums\JenisBerkasSiswa;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class KartuDigitalService
{
    public function __construct(
        protected GotenbergPdfService $gotenberg,
        protected QrCodeService $qrCodeService
    ) {}

    /**
     * Generate Kartu Siswa PDF (single or bulk up to entire rombel, 8 cards/A4).
     *
     * @param  Siswa|Collection<int, Siswa>|array<int, Siswa>  $siswaInput
     */
    public function generateKartuSiswaPdf(Siswa|Collection|array $siswaInput, Sekolah $sekolah, ?Semester $semester = null): string
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Siswa> $siswaList */
        $siswaList = $siswaInput instanceof Siswa
            ? new \Illuminate\Database\Eloquent\Collection([$siswaInput])
            : ($siswaInput instanceof \Illuminate\Database\Eloquent\Collection
                ? $siswaInput
                : new \Illuminate\Database\Eloquent\Collection(collect($siswaInput)->all()));

        // Eager-load relations needed for card rendering
        $siswaList->loadMissing([
            'berkas' => fn ($q) => $q->where('jenis', JenisBerkasSiswa::Foto),
            'anggotaRombel.rombel',
            'anggotaRombel.semester',
        ]);

        $activeSemester = $semester ?? Semester::where('sekolah_id', $sekolah->id)->where('is_aktif', true)->first();
        $semesterLabel = $activeSemester ? "{$activeSemester->nama} {$activeSemester->tahunAjaran?->nama}" : 'Tahun Ajaran Aktif';

        $cards = $siswaList->map(function (Siswa $siswa) use ($sekolah) {
            $currentRombel = $siswa->anggotaRombel
                ->sortByDesc('created_at')
                ->first()?->rombel->nama ?? '-';

            $ttl = $siswa->tempat_lahir
                ? "{$siswa->tempat_lahir}, {$siswa->tanggal_lahir->format('d/m/Y')}"
                : $siswa->tanggal_lahir->format('d/m/Y');

            // Photo processing
            $fotoBase64 = null;
            $fotoBerkas = $siswa->berkas->firstWhere('jenis', JenisBerkasSiswa::Foto);
            if ($fotoBerkas && Storage::disk('s3')->exists($fotoBerkas->file_path)) {
                $fotoContent = Storage::disk('s3')->get($fotoBerkas->file_path);
                if ($fotoContent) {
                    $fotoBase64 = "data:{$fotoBerkas->mime_type};base64,".base64_encode($fotoContent);
                }
            }

            // QR Code generation
            $encryptedPayload = $this->qrCodeService->generateEncryptedPayload(
                'siswa',
                $siswa->id,
                $sekolah->id
            );
            $qrSvg = $this->qrCodeService->generateQrSvg($encryptedPayload, 120);

            return [
                'id' => $siswa->id,
                'nama' => $siswa->nama,
                'nisn' => $siswa->nisn,
                'nik' => $siswa->nik,
                'rombel' => $currentRombel,
                'ttl' => $ttl,
                'foto_base64' => $fotoBase64,
                'qr_svg' => $qrSvg,
            ];
        })->all();

        return $this->renderPdf($cards, $sekolah, $semesterLabel, isSiswa: true);
    }

    /**
     * Generate Kartu Pegawai PDF (single or bulk, 8 cards/A4).
     *
     * @param  Pegawai|Collection<int, Pegawai>|array<int, Pegawai>  $pegawaiInput
     */
    public function generateKartuPegawaiPdf(Pegawai|Collection|array $pegawaiInput, Sekolah $sekolah, ?Semester $semester = null): string
    {
        /** @var Collection<int, Pegawai> $pegawaiList */
        $pegawaiList = $pegawaiInput instanceof Pegawai ? collect([$pegawaiInput]) : collect($pegawaiInput);

        $activeSemester = $semester ?? Semester::where('sekolah_id', $sekolah->id)->where('is_aktif', true)->first();
        $semesterLabel = $activeSemester ? "{$activeSemester->nama} {$activeSemester->tahunAjaran?->nama}" : 'Tahun Ajaran Aktif';

        $cards = $pegawaiList->map(function (Pegawai $pegawai) use ($sekolah) {
            $encryptedPayload = $this->qrCodeService->generateEncryptedPayload(
                'pegawai',
                $pegawai->id,
                $sekolah->id
            );
            $qrSvg = $this->qrCodeService->generateQrSvg($encryptedPayload, 120);

            $jenisLabel = match ($pegawai->jenis) {
                'guru' => 'Guru Pengajar',
                'kepsek' => 'Kepala Sekolah',
                'tenaga_kependidikan' => 'Staf Tata Usaha',
                default => ucfirst(str_replace('_', ' ', $pegawai->jenis)),
            };

            return [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'nuptk' => $pegawai->nuptk,
                'jenis' => $jenisLabel,
                'status_kepegawaian' => strtoupper($pegawai->status_kepegawaian),
                'foto_base64' => null,
                'qr_svg' => $qrSvg,
            ];
        })->all();

        return $this->renderPdf($cards, $sekolah, $semesterLabel, isSiswa: false);
    }

    /**
     * Render HTML and send to Gotenberg to produce PDF.
     *
     * @param  array<int, array<string, mixed>>  $cards
     */
    protected function renderPdf(array $cards, Sekolah $sekolah, string $semesterLabel, bool $isSiswa): string
    {
        // 8 cards per page
        $sheets = array_chunk($cards, 8);

        // Logo processing
        $logoBase64 = null;
        if ($sekolah->logo_path && Storage::disk('s3')->exists($sekolah->logo_path)) {
            $logoContent = Storage::disk('s3')->get($sekolah->logo_path);
            if ($logoContent) {
                $mime = str_ends_with(strtolower($sekolah->logo_path), '.png') ? 'image/png' : 'image/jpeg';
                $logoBase64 = "data:{$mime};base64,".base64_encode($logoContent);
            }
        }

        $sekolahData = [
            'nama' => $sekolah->nama,
            'npsn' => $sekolah->npsn,
            'jenjang' => strtoupper($sekolah->jenjang),
            'alamat' => $sekolah->alamat ?? '-',
            'logo_base64' => $logoBase64,
            'semester_aktif' => $semesterLabel,
        ];

        // Base64 fonts
        $fontRegularBase64 = $this->getFontBase64('plus-jakarta-sans-400.woff2');
        $fontBoldBase64 = $this->getFontBase64('plus-jakarta-sans-700.woff2');

        $html = View::make('kartu.print', [
            'title' => $isSiswa ? "Kartu Pelajar - {$sekolah->nama}" : "Kartu Pegawai - {$sekolah->nama}",
            'sheets' => $sheets,
            'sekolah' => $sekolahData,
            'isSiswa' => $isSiswa,
            'fontRegularBase64' => $fontRegularBase64,
            'fontBoldBase64' => $fontBoldBase64,
            'fallbackLogoSvg' => $this->getFallbackLogoSvg(),
            'fallbackPhotoSvg' => $this->getFallbackPhotoSvg(),
            'accentColor' => $isSiswa ? '#1e40af' : '#047857',
        ])->render();

        return $this->gotenberg->convertHtmlToPdf($html);
    }

    /**
     * Retrieve Base64 representation of embedded font file.
     */
    protected function getFontBase64(string $filename): string
    {
        $path = resource_path("fonts/{$filename}");
        if (file_exists($path)) {
            return base64_encode((string) file_get_contents($path));
        }

        return '';
    }

    /**
     * SVG Fallback for school logo (Tut Wuri Handayani / official emblem inspired).
     */
    public function getFallbackLogoSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" fill="none">
    <rect width="36" height="36" rx="6" fill="#1e3a8a"/>
    <path d="M18 7L8 13.5V25.5L18 30L28 25.5V13.5L18 7Z" stroke="#facc15" stroke-width="1.5" fill="#1e40af"/>
    <path d="M18 12L12 16V23L18 26L24 23V16L18 12Z" fill="#3b82f6"/>
    <circle cx="18" cy="18" r="2.5" fill="#facc15"/>
    <path d="M14 24C16 22 18 22 18 20C18 22 20 22 22 24H14Z" fill="#facc15"/>
</svg>
SVG;
    }

    /**
     * SVG Fallback for profile photo (crisp silhouette avatar).
     */
    public function getFallbackPhotoSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 105" fill="none">
    <rect width="80" height="105" fill="#f8fafc"/>
    <circle cx="40" cy="40" r="16" fill="#94a3b8"/>
    <path d="M14 92C14 74 24 66 40 66C56 66 66 74 66 92V100H14V92Z" fill="#94a3b8"/>
</svg>
SVG;
    }
}
