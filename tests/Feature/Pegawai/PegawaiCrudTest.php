<?php

namespace Tests\Feature\Pegawai;

use App\Models\AlokasiJamMapel;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected User $guruUser;

    protected Pegawai $guruPegawai;

    protected Semester $semesterAktif;

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

        $this->guruUser = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->guruUser->assignRole('guru');
        $this->guruPegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->guruUser->id,
            'nama' => 'Guru Pengajar',
            'jenis' => 'guru',
            'email' => $this->guruUser->email,
        ]);
    }

    public function test_operator_can_view_pegawai_index(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pegawai.index'));

        $response->assertOk();
    }

    public function test_operator_can_create_pegawai_and_auto_create_user_with_must_change_password(): void
    {
        $payload = [
            'nama' => 'Dra. Siti Aminah, M.Pd.',
            'email' => 'siti.aminah@sekolah.sch.id',
            'nip' => '198001012005012001',
            'nuptk' => '1234567890123456',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1980-01-01',
            'agama' => 'Islam',
            'alamat' => 'Jl. Merdeka No. 10',
            'no_hp' => '081234567890',
            'jam_maks_per_minggu' => 30,
            'hari_tidak_mengajar' => ['senin', 'jumat'],
        ];

        $response = $this->actingAs($this->operator)
            ->post(route('pegawai.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('initial_account');

        $flash = session('initial_account');
        $this->assertIsArray($flash);
        $this->assertEquals('Dra. Siti Aminah, M.Pd.', $flash['nama']);
        $this->assertEquals('siti.aminah@sekolah.sch.id', $flash['email']);
        $this->assertNotEmpty($flash['password']);

        // Check Pegawai record
        $this->assertDatabaseHas('pegawai', [
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Dra. Siti Aminah, M.Pd.',
            'nip' => '198001012005012001',
            'nuptk' => '1234567890123456',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
            'jenis_kelamin' => 'P',
            'jam_maks_per_minggu' => 30,
        ]);

        // Check User record and roles
        $user = User::where('email', 'siti.aminah@sekolah.sch.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('guru'));
    }

    public function test_json_request_returns_initial_password(): void
    {
        $payload = [
            'nama' => 'Ahmad Jaelani, S.Kom.',
            'email' => 'ahmad.jaelani@sekolah.sch.id',
            'jenis' => 'tu',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
        ];

        $response = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $payload);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'pegawai' => ['id', 'nama', 'jenis', 'status_kepegawaian'],
            'initial_password',
        ]);

        $createdUser = User::where('email', 'ahmad.jaelani@sekolah.sch.id')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->must_change_password);
        $this->assertTrue($createdUser->hasRole('operator'));
    }

    public function test_auto_role_assignment_for_kepsek(): void
    {
        $payload = [
            'nama' => 'Dr. H. Mulyadi, M.Pd.',
            'email' => 'kepsek@sekolah.sch.id',
            'jenis' => 'kepsek',
            'status_kepegawaian' => 'pns',
            'jam_maks_per_minggu' => 24,
        ];

        $response = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $payload);

        $response->assertCreated();

        $user = User::where('email', 'kepsek@sekolah.sch.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('kepsek'));
    }

    public function test_teaching_load_and_days_off_persisted_and_calculated(): void
    {
        $pegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Guru Matematika',
            'jenis' => 'guru',
            'jam_maks_per_minggu' => 28,
            'hari_tidak_mengajar' => ['rabu', 'kamis'],
        ]);

        $rombel = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
        ]);

        $mapel = MataPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
        ]);

        AlokasiJamMapel::create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $rombel->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $pegawai->id,
            'jam_per_minggu' => 6,
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson(route('pegawai.index', ['search' => 'Guru Matematika']));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $item = $data[0];

        $this->assertEquals(28, $item['jam_maks_per_minggu']);
        $this->assertEquals(['rabu', 'kamis'], $item['hari_tidak_mengajar']);
        $this->assertEquals(6, $item['beban_mengajar_aktual']);
    }

    public function test_operator_can_update_pegawai(): void
    {
        $payload = [
            'nama' => 'Guru Pengajar Diperbarui',
            'email' => $this->guruUser->email,
            'nip' => '198505052010011002',
            'nuptk' => '9876543210987654',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pppk',
            'jam_maks_per_minggu' => 32,
            'hari_tidak_mengajar' => ['selasa'],
        ];

        $response = $this->actingAs($this->operator)
            ->put(route('pegawai.update', $this->guruPegawai->id), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('pegawai', [
            'id' => $this->guruPegawai->id,
            'nama' => 'Guru Pengajar Diperbarui',
            'nip' => '198505052010011002',
            'status_kepegawaian' => 'pppk',
            'jam_maks_per_minggu' => 32,
        ]);

        // Linked User name is also kept in sync
        $this->assertDatabaseHas('users', [
            'id' => $this->guruUser->id,
            'name' => 'Guru Pengajar Diperbarui',
        ]);
    }

    public function test_operator_can_delete_pegawai(): void
    {
        $response = $this->actingAs($this->operator)
            ->delete(route('pegawai.destroy', $this->guruPegawai->id));

        $response->assertRedirect();

        $this->assertSoftDeleted('pegawai', [
            'id' => $this->guruPegawai->id,
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $this->guruUser->id,
        ]);
    }

    public function test_duplicate_nip_or_nuptk_in_same_school_rejected_but_allowed_across_schools(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        Pegawai::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'nip' => '199001012015011001',
            'nuptk' => '5555666677778888',
        ]);

        // Creating with same NIP/NUPTK in different school should succeed
        $payload = [
            'nama' => 'Pegawai Sekolah Kami',
            'email' => 'pegawai.baru@sekolah.sch.id',
            'nip' => '199001012015011001',
            'nuptk' => '5555666677778888',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
        ];

        $response = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $payload);

        $response->assertCreated();

        // But attempting duplicate within the same school should fail validation (422)
        $duplicatePayload = [
            'nama' => 'Pegawai Duplikat',
            'email' => 'pegawai.duplikat@sekolah.sch.id',
            'nip' => '199001012015011001',
            'nuptk' => '5555666677778888',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
        ];

        $duplicateResponse = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $duplicatePayload);

        $duplicateResponse->assertStatus(422);
        $duplicateResponse->assertJsonValidationErrors(['nip', 'nuptk']);
    }

    public function test_rbac_guru_cannot_manage_pegawai_and_can_only_view_self(): void
    {
        $otherPegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Guru Lain',
        ]);

        // Guru cannot list all pegawai
        $this->actingAs($this->guruUser)
            ->get(route('pegawai.index'))
            ->assertForbidden();

        // Guru cannot create pegawai
        $this->actingAs($this->guruUser)
            ->post(route('pegawai.store'), [
                'nama' => 'Baru',
                'email' => 'baru@sekolah.sch.id',
                'jenis' => 'guru',
                'status_kepegawaian' => 'gty',
                'jam_maks_per_minggu' => 24,
            ])
            ->assertForbidden();

        // Guru cannot update pegawai
        $this->actingAs($this->guruUser)
            ->put(route('pegawai.update', $this->guruPegawai->id), [
                'nama' => 'Ganti Nama',
                'email' => $this->guruUser->email,
                'jenis' => 'guru',
                'status_kepegawaian' => 'gty',
                'jam_maks_per_minggu' => 24,
            ])
            ->assertForbidden();

        // Guru cannot delete pegawai
        $this->actingAs($this->guruUser)
            ->delete(route('pegawai.destroy', $this->guruPegawai->id))
            ->assertForbidden();

        // Guru can view self
        $this->actingAs($this->guruUser)
            ->get(route('pegawai.show', $this->guruPegawai->id))
            ->assertOk();

        // Guru cannot view other teacher
        $this->actingAs($this->guruUser)
            ->get(route('pegawai.show', $otherPegawai->id))
            ->assertForbidden();
    }

    public function test_tenant_isolation_pegawai_from_different_school_returns_404(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $pegawaiLain = Pegawai::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'nama' => 'Guru Sekolah Lain',
        ]);

        $this->actingAs($this->operator)
            ->get(route('pegawai.show', $pegawaiLain->id))
            ->assertNotFound();

        $this->actingAs($this->operator)
            ->put(route('pegawai.update', $pegawaiLain->id), [
                'nama' => 'Guru Hack',
                'email' => 'hack@sekolah.sch.id',
                'jenis' => 'guru',
                'status_kepegawaian' => 'gty',
                'jam_maks_per_minggu' => 24,
            ])
            ->assertNotFound();

        $this->actingAs($this->operator)
            ->delete(route('pegawai.destroy', $pegawaiLain->id))
            ->assertNotFound();
    }
}
