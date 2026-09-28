<?php

namespace Tests\Feature\Siswa;

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
use App\Services\BerkasService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BerkasSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected User $userWaliKelas;

    protected Pegawai $pegawaiWaliKelas;

    protected Semester $semesterAktif;

    protected Rombel $rombelA;

    protected Rombel $rombelB;

    protected Siswa $siswaA;

    protected Siswa $siswaB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('s3');

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'is_aktif' => true]);
        $this->semesterAktif = Semester::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'is_aktif' => true,
        ]);

        $this->userWaliKelas = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userWaliKelas->assignRole('wali_kelas');

        $this->pegawaiWaliKelas = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->userWaliKelas->id,
            'nama' => 'Guru Wali Kelas Rombel A',
        ]);

        $this->rombelA = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'wali_kelas_id' => $this->pegawaiWaliKelas->id,
            'nama' => 'X RPL 1',
        ]);

        $this->rombelB = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X RPL 2',
        ]);

        $this->siswaA = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id]);
        AnggotaRombel::create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombelA->id,
            'siswa_id' => $this->siswaA->id,
            'semester_id' => $this->semesterAktif->id,
        ]);

        $this->siswaB = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id]);
        AnggotaRombel::create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombelB->id,
            'siswa_id' => $this->siswaB->id,
            'semester_id' => $this->semesterAktif->id,
        ]);
    }

    public function test_operator_can_upload_valid_berkas_and_photo(): void
    {
        $file = UploadedFile::fake()->image('pas_foto_siswa.jpg', 300, 400);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $this->siswaA->id), [
                'jenis' => 'foto',
                'berkas' => $file,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('berkas_siswa', [
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $this->siswaA->id,
            'jenis' => 'foto',
            'mime_type' => 'image/jpeg',
            'nama_file_asli' => 'pas_foto_siswa.jpg',
        ]);

        $berkas = BerkasSiswa::where('siswa_id', $this->siswaA->id)->where('jenis', 'foto')->firstOrFail();
        Storage::disk('s3')->assertExists($berkas->file_path);
        $this->assertStringStartsWith("{$this->sekolah->id}/", $berkas->file_path);
    }

    public function test_rejects_fake_mime_type_spoofing(): void
    {
        // File with .png extension but text content (magic bytes mismatch)
        $fakeFile = UploadedFile::fake()->createWithContent('malicious.png', '<?php echo "evil script"; ?>');

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $this->siswaA->id), [
                'jenis' => 'foto',
                'berkas' => $fakeFile,
            ]);

        $response->assertSessionHasErrors('berkas');
        $this->assertDatabaseMissing('berkas_siswa', [
            'siswa_id' => $this->siswaA->id,
            'jenis' => 'foto',
        ]);
    }

    public function test_rejects_file_larger_than_2_mb(): void
    {
        // 2.5 MB fake pdf (2560 KB)
        $largeFile = UploadedFile::fake()->create('dokumen_besar.pdf', 2560, 'application/pdf');

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $this->siswaA->id), [
                'jenis' => 'akta',
                'berkas' => $largeFile,
            ]);

        $response->assertSessionHasErrors('berkas');
        $this->assertDatabaseMissing('berkas_siswa', [
            'siswa_id' => $this->siswaA->id,
            'jenis' => 'akta',
        ]);
    }

    public function test_photo_only_accepts_jpg_and_png_rejects_pdf(): void
    {
        $pdfFile = UploadedFile::fake()->create('foto_ijazah.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $this->siswaA->id), [
                'jenis' => 'foto',
                'berkas' => $pdfFile,
            ]);

        $response->assertSessionHasErrors('berkas');
        $this->assertDatabaseMissing('berkas_siswa', [
            'siswa_id' => $this->siswaA->id,
            'jenis' => 'foto',
        ]);
    }

    public function test_signed_url_lifetime_is_300_seconds(): void
    {
        $file = UploadedFile::fake()->image('foto.png', 100, 100);

        /** @var BerkasService $service */
        $service = app(BerkasService::class);
        $berkas = $service->storeBerkas($this->siswaA, JenisBerkasSiswa::Foto, $file);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.berkas.url', [$this->siswaA->id, 'foto']).'?json=1');

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(300, $data['expires_in']);
        $this->assertArrayHasKey('url', $data);

        // Verify URL contains expiration parameter
        $url = $data['url'];
        $this->assertNotEmpty($url);

        // When fake disk is used, url contains ?expiration=<timestamp>
        // Timestamp should be ~ now() + 300 seconds (within 5 seconds delta)
        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        if (isset($queryParams['expiration'])) {
            $diff = abs((int) $queryParams['expiration'] - (now()->timestamp + 300));
            $this->assertLessThanOrEqual(5, $diff);
        } elseif (isset($queryParams['X-Amz-Expires'])) {
            $this->assertSame('300', $queryParams['X-Amz-Expires']);
        }
    }

    public function test_atomic_replacement_deletes_old_file_after_db_commit(): void
    {
        $file1 = UploadedFile::fake()->image('foto_awal.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('foto_revisi.png', 100, 100);

        /** @var BerkasService $service */
        $service = app(BerkasService::class);
        $berkas1 = $service->storeBerkas($this->siswaA, JenisBerkasSiswa::Foto, $file1);
        $oldPath = $berkas1->file_path;

        Storage::disk('s3')->assertExists($oldPath);

        // Upload revision
        $berkas2 = $service->storeBerkas($this->siswaA, JenisBerkasSiswa::Foto, $file2);
        $newPath = $berkas2->file_path;

        // Old file must be deleted from storage after commit
        Storage::disk('s3')->assertMissing($oldPath);
        Storage::disk('s3')->assertExists($newPath);

        // Database only has 1 record for this student and jenis
        $this->assertSame(1, BerkasSiswa::where('siswa_id', $this->siswaA->id)->where('jenis', 'foto')->count());
        $this->assertSame($newPath, BerkasSiswa::where('siswa_id', $this->siswaA->id)->where('jenis', 'foto')->first()->file_path);
    }

    public function test_failed_db_transaction_cleans_up_newly_uploaded_file(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 100, 100);

        /** @var BerkasService $service */
        $service = app(BerkasService::class);

        // Force DB error by breaking connection or throwing inside query
        $mockSiswa = Siswa::factory()->make(['id' => '00000000-0000-0000-0000-000000000000', 'sekolah_id' => $this->sekolah->id]);

        try {
            // Inserting with non-existent student violates foreign key constraint in Postgres
            $service->storeBerkas($mockSiswa, JenisBerkasSiswa::Foto, $file);
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            // Expected
        }

        // Assert no orphaned files left in S3
        $allFiles = Storage::disk('s3')->allFiles($this->sekolah->id);
        $this->assertEmpty($allFiles);
    }

    public function test_wali_kelas_cannot_upload_or_delete_berkas_403(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 100, 100);

        // Upload -> 403 Forbidden
        $responseUpload = $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $this->siswaA->id), [
                'jenis' => 'foto',
                'berkas' => $file,
            ]);

        $responseUpload->assertForbidden();

        // Create berkas as operator
        /** @var BerkasService $service */
        $service = app(BerkasService::class);
        $service->storeBerkas($this->siswaA, JenisBerkasSiswa::Foto, $file);

        // Delete -> 403 Forbidden
        $responseDelete = $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.berkas.destroy', [$this->siswaA->id, 'foto']));

        $responseDelete->assertForbidden();
    }

    public function test_wali_kelas_cannot_download_berkas_of_other_rombel_403(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 100, 100);
        /** @var BerkasService $service */
        $service = app(BerkasService::class);
        $service->storeBerkas($this->siswaB, JenisBerkasSiswa::Foto, $file);

        // Siswa B is in rombel B, while Wali Kelas is for rombel A -> 403 Forbidden
        $response = $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.berkas.url', [$this->siswaB->id, 'foto']).'?json=1');

        $response->assertForbidden();
    }

    public function test_wali_kelas_can_download_berkas_of_own_rombel(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 100, 100);
        /** @var BerkasService $service */
        $service = app(BerkasService::class);
        $service->storeBerkas($this->siswaA, JenisBerkasSiswa::Foto, $file);

        // Siswa A is in rombel A -> 200 OK
        $response = $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.berkas.url', [$this->siswaA->id, 'foto']).'?json=1');

        $response->assertOk();
        $response->assertJsonStructure(['url', 'expires_in']);
    }

    public function test_tenant_isolation_returns_404_for_other_school_student_berkas(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $siswaSekolahLain = Siswa::factory()->create(['sekolah_id' => $sekolahLain->id]);

        $file = UploadedFile::fake()->image('foto.jpg', 100, 100);

        // Attempting upload to other school's student -> 404 Not Found
        $responseUpload = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $siswaSekolahLain->id), [
                'jenis' => 'foto',
                'berkas' => $file,
            ]);

        $responseUpload->assertNotFound();

        // Attempting get url -> 404 Not Found
        $responseUrl = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.berkas.url', [$siswaSekolahLain->id, 'foto']).'?json=1');

        $responseUrl->assertNotFound();

        // Attempting delete -> 404 Not Found
        $responseDelete = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.berkas.destroy', [$siswaSekolahLain->id, 'foto']));

        $responseDelete->assertNotFound();
    }

    public function test_berkas_changes_are_recorded_in_activity_log_with_metadata_only(): void
    {
        $file = UploadedFile::fake()->image('foto_profil.jpg', 200, 200);

        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.berkas.store', $this->siswaA->id), [
                'jenis' => 'foto',
                'berkas' => $file,
            ]);

        $activity = Activity::where('subject_type', BerkasSiswa::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $attributes = $activity->properties['attributes'] ?? [];

        // Check metadata logged
        $this->assertSame('foto', $attributes['jenis']);
        $this->assertSame('foto_profil.jpg', $attributes['nama_file_asli']);
        $this->assertSame('image/jpeg', $attributes['mime_type']);
        $this->assertNotEmpty($attributes['file_path']);
        $this->assertArrayNotHasKey('file_content', $attributes);
    }
}
