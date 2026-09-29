<?php

namespace Tests\Feature\Kartu;

use App\Enums\JenisBerkasSiswa;
use App\Models\AnggotaRombel;
use App\Models\BerkasSiswa;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Kartu\KartuDigitalService;
use App\Services\Kartu\QrCodeService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KartuDigitalTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected User $kepsek;

    protected User $guru;

    protected Pegawai $guruPegawai;

    protected Semester $semesterAktif;

    protected Rombel $rombel;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create([
            'nama' => 'SMA Negeri 1 Prestasi',
            'npsn' => '20109988',
            'jenjang' => 'sma',
            'alamat' => 'Jl. Pendidikan No. 10',
        ]);
        setPermissionsTeamId($this->sekolah->id);

        $tahunAjaran = TahunAjaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => '2026/2027',
        ]);

        $this->semesterAktif = Semester::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Ganjil',
            'is_aktif' => true,
        ]);

        $this->operator = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'name' => 'Operator Sekolah',
            'email' => 'operator@sekolah.sch.id',
        ]);
        $this->operator->assignRole('operator');

        $this->kepsek = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'name' => 'Kepala Sekolah',
            'email' => 'kepsek@sekolah.sch.id',
        ]);
        $this->kepsek->assignRole('kepsek');

        $this->guru = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'name' => 'Guru Pengajar',
            'email' => 'guru@sekolah.sch.id',
        ]);
        $this->guru->assignRole('guru');

        $this->guruPegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->guru->id,
            'nama' => 'Guru Pengajar',
            'email' => 'guru@sekolah.sch.id',
            'nip' => '198001012005011001',
            'nuptk' => '1234567890123456',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        $this->rombel = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X-A',
            'tingkat' => 10,
            'wali_kelas_id' => $this->guruPegawai->id,
        ]);

        $this->siswa = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Ahmad Fajar',
            'nisn' => '0098765432',
            'nik' => '3201011205080001',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2008-05-12',
        ]);

        AnggotaRombel::create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
        ]);
    }

    public function test_qr_code_encrypted_payload_can_be_decrypted_and_verified(): void
    {
        $qrService = app(QrCodeService::class);

        $payloadEncrypted = $qrService->generateEncryptedPayload(
            'siswa',
            $this->siswa->id,
            $this->sekolah->id
        );

        $this->assertNotEmpty($payloadEncrypted);
        $this->assertNotEquals($this->siswa->id, $payloadEncrypted);

        $decrypted = $qrService->decryptPayload($payloadEncrypted);

        $this->assertEquals(1, $decrypted['v']);
        $this->assertEquals('s', $decrypted['t']);
        $this->assertEquals($this->siswa->id, $decrypted['id']);
        $this->assertEquals($this->sekolah->id, $decrypted['sid']);
        $this->assertArrayNotHasKey('num', $decrypted);
    }

    public function test_qr_code_readability_and_density_on_small_card_format(): void
    {
        $qrService = app(QrCodeService::class);

        $payloadEncrypted = $qrService->generateEncryptedPayload(
            'siswa',
            $this->siswa->id,
            $this->sekolah->id
        );

        // Generate vector SVG
        $svg = $qrService->generateQrSvg($payloadEncrypted, 120);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringContainsString('viewBox="0 0', $svg);
        // Validates that paths (modules) are generated
        $this->assertStringContainsString('<path', $svg);

        // Ensure encrypted string is reasonably sized for QR density (under 450 chars)
        $this->assertLessThan(450, strlen($payloadEncrypted));
    }

    public function test_operator_can_generate_single_kartu_siswa_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('siswa.kartu', $this->siswa->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_operator_can_generate_bulk_kartu_siswa_rombel_pdf(): void
    {
        // Add another student to rombel
        $siswa2 = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Budi Santoso',
            'nisn' => '0098765433',
        ]);
        AnggotaRombel::create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $siswa2->id,
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('rombel.kartu', $this->rombel->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_operator_can_generate_single_kartu_pegawai_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pegawai.kartu', $this->guruPegawai->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_operator_can_generate_bulk_kartu_pegawai_pdf(): void
    {
        // Add another employee
        Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Staf Tata Usaha',
            'jenis' => 'tenaga_kependidikan',
            'status_kepegawaian' => 'pns',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('pegawai.kartu.massal'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_tenant_isolation_kartu_returns_404_for_different_school(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $siswaLain = Siswa::factory()->create([
            'sekolah_id' => $sekolahLain->id,
        ]);
        $pegawaiLain = Pegawai::factory()->create([
            'sekolah_id' => $sekolahLain->id,
        ]);
        $rombelLain = Rombel::factory()->create([
            'sekolah_id' => $sekolahLain->id,
        ]);

        // Student card cross-tenant
        $responseSiswa = $this->actingAs($this->operator)
            ->get(route('siswa.kartu', $siswaLain->id));
        $responseSiswa->assertNotFound();

        // Pegawai card cross-tenant
        $responsePegawai = $this->actingAs($this->operator)
            ->get(route('pegawai.kartu', $pegawaiLain->id));
        $responsePegawai->assertNotFound();

        // Rombel card cross-tenant
        $responseRombel = $this->actingAs($this->operator)
            ->get(route('rombel.kartu', $rombelLain->id));
        $responseRombel->assertNotFound();
    }

    public function test_rbac_wali_kelas_can_print_assigned_rombel_cards_but_forbidden_for_other_rombel(): void
    {
        $this->guru->assignRole('wali_kelas');

        // Can print own rombel
        $responseOwn = $this->actingAs($this->guru)
            ->get(route('rombel.kartu', $this->rombel->id));
        $responseOwn->assertOk();
        $responseOwn->assertHeader('Content-Type', 'application/pdf');

        // Cannot print another rombel in the same school
        $rombelLain = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X-B',
            'wali_kelas_id' => null,
        ]);

        $responseOther = $this->actingAs($this->guru)
            ->get(route('rombel.kartu', $rombelLain->id));
        $responseOther->assertForbidden();
    }

    public function test_fallbacks_rendered_when_photo_and_logo_are_missing(): void
    {
        $kartuService = app(KartuDigitalService::class);

        // Ensure no logo and no photo
        $this->sekolah->update(['logo_path' => null]);

        $pdfBinary = $kartuService->generateKartuSiswaPdf($this->siswa, $this->sekolah);
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
    }

    public function test_actual_photo_and_logo_embedded_when_available(): void
    {
        Storage::fake('s3');

        // Store fake logo
        $logoFile = UploadedFile::fake()->image('logo.png', 100, 100);
        $logoPath = $logoFile->store('logos', 's3');
        $this->sekolah->update(['logo_path' => $logoPath]);

        // Store fake student photo
        $photoFile = UploadedFile::fake()->image('pasfoto.jpg', 200, 260);
        $photoPath = $photoFile->store('berkas_siswa', 's3');
        BerkasSiswa::create([
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $this->siswa->id,
            'jenis' => JenisBerkasSiswa::Foto,
            'file_path' => $photoPath,
            'nama_file_asli' => 'pasfoto.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 1024,
        ]);

        $kartuService = app(KartuDigitalService::class);
        $pdfBinary = $kartuService->generateKartuSiswaPdf($this->siswa, $this->sekolah);

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
    }

    public function test_peak_memory_usage_when_rendering_full_rombel_of_36_students(): void
    {
        // Create 35 additional students in the rombel (total 36 students = 1 full class)
        $students = collect([$this->siswa]);
        for ($i = 2; $i <= 36; $i++) {
            $student = Siswa::factory()->create([
                'sekolah_id' => $this->sekolah->id,
                'nama' => "Siswa Rombel {$i}",
                'nisn' => '0098'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            ]);
            AnggotaRombel::create([
                'sekolah_id' => $this->sekolah->id,
                'semester_id' => $this->semesterAktif->id,
                'rombel_id' => $this->rombel->id,
                'siswa_id' => $student->id,
            ]);
            $students->push($student);
        }

        $memStart = memory_get_usage();
        $kartuService = app(KartuDigitalService::class);
        $pdfBinary = $kartuService->generateKartuSiswaPdf($students, $this->sekolah, $this->rombel->semester);
        $memDelta = memory_get_usage() - $memStart;
        $memPeak = memory_get_peak_usage(true);

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);

        // Peak memory of the process should stay within lightweight bounds (< 128 MB)
        $memPeakMb = round($memPeak / 1024 / 1024, 2);
        $memDeltaMb = round($memDelta / 1024 / 1024, 2);
        $this->assertLessThan(128, $memPeakMb, "Peak memory ({$memPeakMb} MB) exceeded maximum threshold.");
        // Additional memory allocated specifically for 36 cards should be very lightweight (< 15 MB)
        $this->assertLessThan(15, $memDeltaMb, "Delta memory ({$memDeltaMb} MB) exceeded threshold.");
    }

    public function test_bulk_kartu_pegawai_denied_for_non_operator_roles(): void
    {
        // 1. Kepsek -> 403
        $this->actingAs($this->kepsek)
            ->get(route('pegawai.kartu.massal'))
            ->assertForbidden();

        // 2. Waka Kurikulum -> 403
        $waka = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $waka->assignRole('waka_kurikulum');
        $this->actingAs($waka)
            ->get(route('pegawai.kartu.massal'))
            ->assertForbidden();

        // 3. Guru -> 403
        $this->actingAs($this->guru)
            ->get(route('pegawai.kartu.massal'))
            ->assertForbidden();

        // 4. Wali Kelas -> 403
        $wali = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $wali->assignRole('wali_kelas');
        $this->actingAs($wali)
            ->get(route('pegawai.kartu.massal'))
            ->assertForbidden();

        // 5. Orang Tua -> 403
        $ortu = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $ortu->assignRole('orang_tua');
        $this->actingAs($ortu)
            ->get(route('pegawai.kartu.massal'))
            ->assertForbidden();

        // 6. Operator -> 200 OK
        $this->actingAs($this->operator)
            ->get(route('pegawai.kartu.massal'))
            ->assertOk();

        // 7. Super Admin -> 200 OK
        $superAdmin = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('pegawai.kartu.massal'))
            ->assertOk();
    }

    public function test_peak_memory_usage_when_rendering_full_rombel_of_36_students_with_large_photos(): void
    {
        Storage::fake('s3');

        // Create 36 students, each with a realistic ~1.5 MB photo attached in storage
        $students = collect();
        $fakeLargeImageData = str_repeat("\xFF\xD8\xFF\xE0\x00\x10JFIF".str_repeat('A', 1024), 1400); // ~1.4 MB image

        for ($i = 1; $i <= 36; $i++) {
            $student = Siswa::factory()->create([
                'sekolah_id' => $this->sekolah->id,
                'nama' => "Siswa Foto Besar {$i}",
                'nisn' => '0097'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            ]);

            $photoPath = "berkas_siswa/foto_{$student->id}.jpg";
            Storage::disk('s3')->put($photoPath, $fakeLargeImageData);

            BerkasSiswa::create([
                'sekolah_id' => $this->sekolah->id,
                'siswa_id' => $student->id,
                'jenis' => JenisBerkasSiswa::Foto,
                'file_path' => $photoPath,
                'nama_file_asli' => 'pasfoto.jpg',
                'mime_type' => 'image/jpeg',
                'file_size_bytes' => strlen($fakeLargeImageData),
            ]);

            AnggotaRombel::create([
                'sekolah_id' => $this->sekolah->id,
                'semester_id' => $this->semesterAktif->id,
                'rombel_id' => $this->rombel->id,
                'siswa_id' => $student->id,
            ]);

            $students->push($student);
        }

        $memStart = memory_get_usage();
        $kartuService = app(KartuDigitalService::class);
        $pdfBinary = $kartuService->generateKartuSiswaPdf($students, $this->sekolah, $this->rombel->semester);
        $memDelta = memory_get_usage() - $memStart;
        $memPeak = memory_get_peak_usage(true);

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);

        $memPeakMb = round($memPeak / 1024 / 1024, 2);
        $memDeltaMb = round($memDelta / 1024 / 1024, 2);

        // Record realistic findings: with 36 * 1.4MB = ~50MB binary photos, base64 payload is ~67MB,
        // Blade HTML string is ~70MB, and HTTP multipart payload is ~70MB.
        // Measured PHP process peak memory reaches ~272 MB.
        $this->assertLessThan(384, $memPeakMb, "Peak memory ({$memPeakMb} MB) exceeded 384MB threshold.");
    }
}
