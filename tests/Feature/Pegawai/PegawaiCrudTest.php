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
use Spatie\Activitylog\Models\Activity;
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

        $this->assertSoftDeleted('users', [
            'id' => $this->guruUser->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->guruUser->id,
        ]);
    }

    public function test_pegawai_soft_delete_preserves_user_and_audit_log_and_restore_reactivates_user(): void
    {
        // 1. Create a user and pegawai with known password
        $user = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'name' => 'Guru Audit',
            'email' => 'guru.audit@sekolah.sch.id',
            'password' => bcrypt('Password123!'),
        ]);
        $user->assignRole('guru');

        $pegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $user->id,
            'nama' => 'Guru Audit',
            'email' => 'guru.audit@sekolah.sch.id',
        ]);

        // 2. User creates an activity log entry
        activity()
            ->causedBy($user)
            ->performedOn($pegawai)
            ->log('Melakukan input nilai siswa');

        $activity = Activity::where('causer_id', (string) $user->id)->first();
        $this->assertNotNull($activity);
        $this->assertEquals('Melakukan input nilai siswa', $activity->description);

        // 3. User can login initially
        $loginResponse = $this->post(route('login.store'), [
            'email' => 'guru.audit@sekolah.sch.id',
            'password' => 'Password123!',
        ]);
        $loginResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // Logout before delete
        $this->post(route('logout'));
        $this->assertGuest();

        // 4. Operator soft-deletes the Pegawai
        $deleteResponse = $this->actingAs($this->operator)
            ->delete(route('pegawai.destroy', $pegawai->id));
        $deleteResponse->assertRedirect();

        // Pegawai and User are soft-deleted, but row remains in DB
        $this->assertSoftDeleted('pegawai', ['id' => $pegawai->id]);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Logout operator so session is guest
        $this->post(route('logout'));
        $this->assertGuest();

        // 5. Soft-deleted user CANNOT login
        $this->post(route('login.store'), [
            'email' => 'guru.audit@sekolah.sch.id',
            'password' => 'Password123!',
        ]);
        $this->assertGuest();

        // 6. Audit log remains intact and still points to the user
        $activityAfterDelete = Activity::where('causer_id', (string) $user->id)->first();
        $this->assertNotNull($activityAfterDelete);
        $this->assertEquals((string) $user->id, (string) $activityAfterDelete->causer_id);
        $resolvedUser = User::withTrashed()->find($activityAfterDelete->causer_id);
        $this->assertNotNull($resolvedUser);
        $this->assertEquals('Guru Audit', $resolvedUser->name);

        // 7. Restore pegawai -> automatically restores linked user
        $pegawai->restore();
        $this->assertNotSoftDeleted('pegawai', ['id' => $pegawai->id]);
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);

        // 8. User can login again
        $reloginResponse = $this->post(route('login.store'), [
            'email' => 'guru.audit@sekolah.sch.id',
            'password' => 'Password123!',
        ]);
        $reloginResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);
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

    public function test_operator_can_assign_additional_roles_like_waka_kurikulum_and_wali_kelas(): void
    {
        $payload = [
            'nama' => 'Bambang Triyono, M.Kom.',
            'email' => 'bambang.waka@sekolah.sch.id',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
            'roles' => ['waka_kurikulum', 'wali_kelas'],
        ];

        $response = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $payload);

        $response->assertCreated();

        $user = User::where('email', 'bambang.waka@sekolah.sch.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('guru'));
        $this->assertTrue($user->hasRole('waka_kurikulum'));
        $this->assertTrue($user->hasRole('wali_kelas'));

        $pegawaiId = $response->json('pegawai.id');

        // Test updating additional roles
        $updatePayload = [
            'nama' => 'Bambang Triyono, M.Kom.',
            'email' => 'bambang.waka@sekolah.sch.id',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
            'roles' => ['waka_kurikulum'],
        ];

        $updateResponse = $this->actingAs($this->operator)
            ->putJson(route('pegawai.update', $pegawaiId), $updatePayload);

        $updateResponse->assertOk();

        $user->refresh();
        $this->assertTrue($user->hasRole('guru'));
        $this->assertTrue($user->hasRole('waka_kurikulum'));
        $this->assertFalse($user->hasRole('wali_kelas'));
    }

    public function test_privilege_escalation_guard_super_admin_role_cannot_be_assigned_via_pegawai_endpoint(): void
    {
        // 1. Attempt on store
        $payloadStore = [
            'nama' => 'Attacker Pegawai',
            'email' => 'attacker@sekolah.sch.id',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
            'roles' => ['super_admin'],
        ];

        $responseStore = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $payloadStore);

        $responseStore->assertStatus(422);
        $responseStore->assertJsonValidationErrors(['roles.0']);

        // 2. Attempt on update
        $payloadUpdate = [
            'nama' => 'Guru Pengajar',
            'email' => $this->guruUser->email,
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
            'jam_maks_per_minggu' => 24,
            'roles' => ['guru', 'super_admin'],
        ];

        $responseUpdate = $this->actingAs($this->operator)
            ->putJson(route('pegawai.update', $this->guruPegawai->id), $payloadUpdate);

        $responseUpdate->assertStatus(422);
        $responseUpdate->assertJsonValidationErrors(['roles.1']);

        $this->guruUser->refresh();
        $this->assertFalse($this->guruUser->hasRole('super_admin'));
    }

    public function test_validation_nip_and_nuptk_only_applies_when_filled_allowing_multiple_nulls(): void
    {
        $pegawaiA = [
            'nama' => 'Pegawai Tanpa NIP A',
            'email' => 'tanpa.nip.a@sekolah.sch.id',
            'nip' => null,
            'nuptk' => null,
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
        ];

        $responseA = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $pegawaiA);
        $responseA->assertCreated();

        $pegawaiB = [
            'nama' => 'Pegawai Tanpa NIP B',
            'email' => 'tanpa.nip.b@sekolah.sch.id',
            'nip' => null,
            'nuptk' => null,
            'jenis' => 'guru',
            'status_kepegawaian' => 'gtt',
            'jam_maks_per_minggu' => 24,
        ];

        $responseB = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $pegawaiB);
        $responseB->assertCreated();

        $this->assertDatabaseHas('pegawai', ['email' => 'tanpa.nip.a@sekolah.sch.id', 'nip' => null, 'nuptk' => null]);
        $this->assertDatabaseHas('pegawai', ['email' => 'tanpa.nip.b@sekolah.sch.id', 'nip' => null, 'nuptk' => null]);
    }

    public function test_initial_password_never_retrievable_via_any_endpoint_after_first_response(): void
    {
        $payload = [
            'nama' => 'Pegawai Rahasia',
            'email' => 'rahasia@sekolah.sch.id',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
            'jam_maks_per_minggu' => 24,
        ];

        $storeResponse = $this->actingAs($this->operator)
            ->postJson(route('pegawai.store'), $payload);

        $storeResponse->assertCreated();
        $this->assertNotEmpty($storeResponse->json('initial_password'));
        $pegawaiId = $storeResponse->json('pegawai.id');

        // Subsequent show request must NEVER return password or initial_password
        $showResponse = $this->actingAs($this->operator)
            ->getJson(route('pegawai.show', $pegawaiId));

        $showResponse->assertOk();
        $this->assertArrayNotHasKey('initial_password', $showResponse->json());
        $this->assertArrayNotHasKey('password', $showResponse->json());
        $this->assertArrayNotHasKey('password', $showResponse->json('user') ?? []);

        // Subsequent index request must NEVER return password or initial_password
        $indexResponse = $this->actingAs($this->operator)
            ->getJson(route('pegawai.index', ['search' => 'Pegawai Rahasia']));

        $indexResponse->assertOk();
        $data = $indexResponse->json('data.0');
        $this->assertArrayNotHasKey('initial_password', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('password', $data['user'] ?? []);
    }
}
