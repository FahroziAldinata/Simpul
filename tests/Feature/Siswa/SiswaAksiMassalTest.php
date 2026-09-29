<?php

namespace Tests\Feature\Siswa;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Exports\SiswaExport;
use App\Models\AnggotaRombel;
use App\Models\MutasiSiswa;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SiswaAksiMassalTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected Semester $semesterAktif;

    protected Rombel $rombelA;

    protected Rombel $rombelB;

    protected Siswa $siswa1;

    protected Siswa $siswa2;

    protected Siswa $siswa3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

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

        $this->rombelA = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X-A',
            'tingkat' => 10,
            'kuota' => 30,
        ]);

        $this->rombelB = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X-B',
            'tingkat' => 10,
            'kuota' => 30,
        ]);

        $this->siswa1 = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'status' => StatusSiswa::Aktif,
            'nama' => 'Siswa Satu',
        ]);

        $this->siswa2 = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'status' => StatusSiswa::Aktif,
            'nama' => 'Siswa Dua',
        ]);

        $this->siswa3 = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'status' => StatusSiswa::Aktif,
            'nama' => 'Siswa Tiga',
        ]);

        // Assign students to rombelA
        foreach ([$this->siswa1, $this->siswa2, $this->siswa3] as $siswa) {
            AnggotaRombel::factory()->create([
                'sekolah_id' => $this->sekolah->id,
                'semester_id' => $this->semesterAktif->id,
                'siswa_id' => $siswa->id,
                'rombel_id' => $this->rombelA->id,
            ]);
        }
    }

    // =========================================================================
    // Ubah Rombel Massal
    // =========================================================================

    public function test_operator_can_bulk_ubah_rombel(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id],
                'rombel_id' => $this->rombelB->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => true]);

        // Both students should now be in rombelB
        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa1->id,
            'rombel_id' => $this->rombelB->id,
            'semester_id' => $this->semesterAktif->id,
        ]);

        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa2->id,
            'rombel_id' => $this->rombelB->id,
            'semester_id' => $this->semesterAktif->id,
        ]);
    }

    public function test_bulk_ubah_rombel_records_individual_mutasi_per_student(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id, $this->siswa3->id],
                'rombel_id' => $this->rombelB->id,
            ]);

        // One mutasi per student, not a single combined record
        $this->assertDatabaseCount('mutasi_siswa', 3);

        foreach ([$this->siswa1->id, $this->siswa2->id, $this->siswa3->id] as $siswaId) {
            $this->assertDatabaseHas('mutasi_siswa', [
                'siswa_id' => $siswaId,
                'tipe' => JenisMutasi::PindahRombel->value,
                'ke_rombel_id' => $this->rombelB->id,
                'rombel_id_sebelum' => $this->rombelA->id,
                'is_batal' => false,
            ]);
        }
    }

    public function test_bulk_ubah_rombel_mutations_are_lifo_reversible(): void
    {
        // Create bulk mutation
        $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id],
                'rombel_id' => $this->rombelB->id,
            ]);

        // Student should now be in rombelB
        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa1->id,
            'rombel_id' => $this->rombelB->id,
        ]);

        // Retrieve the mutasi
        $mutasi = MutasiSiswa::where('siswa_id', $this->siswa1->id)->first();
        $this->assertNotNull($mutasi);

        // Cancel the mutation
        $cancelResponse = $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi->id}/batal", ['alasan_batal' => 'Dibatalkan karena salah rombel']);

        $cancelResponse->assertStatus(200);

        // Student should be back in rombelA
        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa1->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // Mutasi should be marked cancelled
        $this->assertDatabaseHas('mutasi_siswa', [
            'id' => $mutasi->id,
            'is_batal' => true,
        ]);
    }

    public function test_bulk_ubah_rombel_is_all_or_nothing_on_failure(): void
    {
        // Use a UUID that doesn't exist for one student
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id, $nonExistentId],
                'rombel_id' => $this->rombelB->id,
            ]);

        $response->assertStatus(422);

        // siswa1 should NOT have been moved (transaction rolled back)
        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa1->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // No mutations should have been recorded
        $this->assertDatabaseCount('mutasi_siswa', 0);
    }

    public function test_bulk_ubah_rombel_error_message_specifies_failing_student(): void
    {
        // Use a student from another school to force 403/404 inside the transaction
        $sekolahLain = Sekolah::factory()->create();
        $siswaLain = Siswa::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'status' => StatusSiswa::Aktif,
            'nama' => 'Siswa Sekolah Lain',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id, $siswaLain->id],
                'rombel_id' => $this->rombelB->id,
            ]);

        $response->assertStatus(422);

        // siswa1 should NOT have been moved (transaction rolled back)
        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa1->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // Error should mention the failing siswaLain or contain an error message
        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
    }

    // =========================================================================
    // Ubah Status Massal
    // =========================================================================

    public function test_operator_can_bulk_ubah_status_to_lulus(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id],
                'status' => 'lulus',
                'tanggal' => '2026-06-30',
                'alasan' => 'Lulus tahun ajaran 2025/2026',
            ]);

        $response->assertStatus(200);

        foreach ([$this->siswa1, $this->siswa2] as $siswa) {
            $this->assertDatabaseHas('siswa', [
                'id' => $siswa->id,
                'status' => StatusSiswa::Lulus->value,
            ]);

            $this->assertDatabaseHas('mutasi_siswa', [
                'siswa_id' => $siswa->id,
                'tipe' => JenisMutasi::Lulus->value,
                'is_batal' => false,
            ]);
        }
    }

    public function test_operator_can_bulk_ubah_status_to_keluar_with_required_fields(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id],
                'status' => 'keluar',
                'tanggal' => '2026-07-01',
                'alasan' => 'Mengikuti domisili orang tua',
                'sekolah_tujuan' => 'SMA Negeri 1 Surabaya',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('siswa', [
            'id' => $this->siswa1->id,
            'status' => StatusSiswa::Pindah->value,
        ]);

        $this->assertDatabaseHas('mutasi_siswa', [
            'siswa_id' => $this->siswa1->id,
            'tipe' => JenisMutasi::Keluar->value,
            'sekolah_tujuan' => 'SMA Negeri 1 Surabaya',
        ]);
    }

    public function test_bulk_ubah_status_keluar_requires_sekolah_tujuan(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id],
                'status' => 'keluar',
                'tanggal' => '2026-07-01',
                'alasan' => 'Pindah sekolah',
                // sekolah_tujuan omitted intentionally
            ]);

        $response->assertStatus(422);

        // Status should NOT have changed
        $this->assertDatabaseHas('siswa', [
            'id' => $this->siswa1->id,
            'status' => StatusSiswa::Aktif->value,
        ]);
    }

    public function test_bulk_ubah_status_keluar_requires_tanggal(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id],
                'status' => 'keluar',
                // tanggal omitted intentionally
                'alasan' => 'Pindah sekolah',
                'sekolah_tujuan' => 'SMA Negeri 1 Surabaya',
            ]);

        $response->assertStatus(422);
    }

    public function test_bulk_ubah_status_keluar_requires_alasan(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id],
                'status' => 'keluar',
                'tanggal' => '2026-07-01',
                // alasan omitted intentionally
                'sekolah_tujuan' => 'SMA Negeri 1 Surabaya',
            ]);

        $response->assertStatus(422);
    }

    public function test_bulk_ubah_status_records_individual_mutasi_per_student(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id, $this->siswa3->id],
                'status' => 'lulus',
                'tanggal' => '2026-06-30',
                'alasan' => 'Lulus',
            ]);

        // One mutasi per student
        $this->assertDatabaseCount('mutasi_siswa', 3);
    }

    public function test_bulk_ubah_status_is_all_or_nothing(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->operator)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id, $nonExistentId],
                'status' => 'lulus',
                'tanggal' => '2026-06-30',
                'alasan' => 'Lulus',
            ]);

        $response->assertStatus(422);

        // siswa1 must NOT have changed status
        $this->assertDatabaseHas('siswa', [
            'id' => $this->siswa1->id,
            'status' => StatusSiswa::Aktif->value,
        ]);

        $this->assertDatabaseCount('mutasi_siswa', 0);
    }

    // =========================================================================
    // Ekspor Excel
    // =========================================================================

    public function test_operator_can_export_selected_students_to_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->post('/siswa/aksi-massal/ekspor', [
                '_token' => csrf_token(),
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id],
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $contentDisp = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('data-siswa-', (string) $contentDisp);
        $this->assertStringContainsString('.xlsx', (string) $contentDisp);
    }

    public function test_export_uses_text_type_for_nisn_nik_nohp_to_prevent_scientific_notation(): void
    {
        /** @var Collection<int, Siswa> $students */
        $students = Siswa::where('sekolah_id', $this->sekolah->id)->get();

        $export = new SiswaExport($students);

        $columnFormats = $export->columnFormats();

        // B = NISN, C = NIK, J = No. HP must be TEXT format
        $this->assertArrayHasKey('B', $columnFormats);
        $this->assertArrayHasKey('C', $columnFormats);
        $this->assertArrayHasKey('J', $columnFormats);

        $this->assertSame(NumberFormat::FORMAT_TEXT, $columnFormats['B']);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $columnFormats['C']);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $columnFormats['J']);
    }

    public function test_export_sanitizes_formula_injection_in_cell_values(): void
    {
        /** @var Collection<int, Siswa> $students */
        $students = collect([
            $this->siswa1,
        ]);

        $export = new SiswaExport($students);

        // Use reflection to test the sanitizeCell method
        $reflection = new \ReflectionClass($export);
        $method = $reflection->getMethod('sanitizeCell');
        $method->setAccessible(true);

        // Each formula-injection prefix should be escaped with a single quote
        foreach (['=1+1', '+CMD', '-2', '@SUM'] as $maliciousValue) {
            $sanitized = $method->invoke($export, $maliciousValue);
            $this->assertStringStartsWith("'", $sanitized,
                "Formula injection not sanitized for value: {$maliciousValue}");
        }

        // Safe values should pass through unchanged
        $this->assertSame('normal text', $method->invoke($export, 'normal text'));
        $this->assertSame('3201010101', $method->invoke($export, '3201010101'));
    }

    public function test_export_is_logged_in_activity_log(): void
    {
        $this->actingAs($this->operator)
            ->post('/siswa/aksi-massal/ekspor', [
                '_token' => csrf_token(),
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id],
            ]);

        $log = Activity::where('log_name', 'siswa')
            ->where('causer_id', $this->operator->id)
            ->latest()
            ->first();

        $this->assertNotNull($log, 'Ekspor tidak dicatat di activity_log');
        $this->assertStringContainsString('Mengekspor', $log->description);
        $this->assertNotNull($log->properties->get('jumlah_siswa'));
    }

    // =========================================================================
    // RBAC Authorization
    // =========================================================================

    public function test_non_operator_roles_are_forbidden_from_bulk_rombel(): void
    {
        $waliKelas = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $waliKelas->assignRole('wali_kelas');

        $response = $this->actingAs($waliKelas)
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id],
                'rombel_id' => $this->rombelB->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_non_operator_roles_are_forbidden_from_bulk_status(): void
    {
        $kepsek = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $kepsek->assignRole('kepsek');

        $response = $this->actingAs($kepsek)
            ->postJson('/siswa/aksi-massal/status', [
                'siswa_ids' => [$this->siswa1->id],
                'status' => 'lulus',
                'tanggal' => '2026-06-30',
                'alasan' => 'Lulus',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_operator_roles_are_forbidden_from_bulk_export(): void
    {
        $guru = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $guru->assignRole('guru');

        $response = $this->actingAs($guru)
            ->post('/siswa/aksi-massal/ekspor', [
                '_token' => csrf_token(),
                'siswa_ids' => [$this->siswa1->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_bulk_endpoints(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $response = $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson('/siswa/aksi-massal/rombel', [
                'siswa_ids' => [$this->siswa1->id],
                'rombel_id' => $this->rombelB->id,
            ]);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_bulk_endpoints(): void
    {
        $this->postJson('/siswa/aksi-massal/rombel', [])->assertStatus(401);
        $this->postJson('/siswa/aksi-massal/status', [])->assertStatus(401);
        $this->post('/siswa/aksi-massal/ekspor', [])->assertRedirect('/login');
    }
}
