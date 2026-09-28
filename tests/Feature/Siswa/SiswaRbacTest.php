<?php

namespace Tests\Feature\Siswa;

use App\Models\AnggotaRombel;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WaliSiswa;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected Semester $semesterAktif;

    protected Rombel $rombelA;

    protected Rombel $rombelB;

    protected User $userWaliKelas;

    protected Pegawai $pegawaiWaliKelas;

    protected Siswa $siswaRombelA;

    protected Siswa $siswaRombelB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'is_aktif' => true]);
        $this->semesterAktif = Semester::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'is_aktif' => true,
        ]);

        // Setup Wali Kelas User & Pegawai
        $this->userWaliKelas = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userWaliKelas->assignRole('wali_kelas');

        $this->pegawaiWaliKelas = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->userWaliKelas->id,
            'nama' => 'Guru Wali Kelas A',
        ]);

        // Rombel A diwalikan oleh userWaliKelas
        $this->rombelA = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'wali_kelas_id' => $this->pegawaiWaliKelas->id,
            'nama' => 'X-A',
        ]);

        // Rombel B diwalikan oleh pegawai lain
        $otherPegawai = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->rombelB = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'wali_kelas_id' => $otherPegawai->id,
            'nama' => 'X-B',
        ]);

        // Siswa di Rombel A
        $this->siswaRombelA = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id]);
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombelA->id,
            'siswa_id' => $this->siswaRombelA->id,
            'semester_id' => $this->semesterAktif->id,
        ]);

        // Siswa di Rombel B
        $this->siswaRombelB = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id]);
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombelB->id,
            'siswa_id' => $this->siswaRombelB->id,
            'semester_id' => $this->semesterAktif->id,
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'sekolah_id' => $role === 'super_admin' ? null : $this->sekolah->id,
        ]);
        setPermissionsTeamId($this->sekolah->id);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_has_full_crud_access(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        $this->actingAs($admin)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.destroy', $this->siswaRombelA->id))
            ->assertRedirect();

        $this->assertSoftDeleted('siswa', ['id' => $this->siswaRombelA->id]);
    }

    public function test_operator_has_full_crud_access(): void
    {
        $operator = $this->createUserWithRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.index'))
            ->assertOk();

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.destroy', $this->siswaRombelB->id))
            ->assertRedirect();

        $this->assertSoftDeleted('siswa', ['id' => $this->siswaRombelB->id]);
    }

    public function test_kepsek_and_waka_have_read_only_access(): void
    {
        foreach (['kepsek', 'waka_kurikulum'] as $role) {
            $user = $this->createUserWithRole($role);

            // Read: OK
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->get(route('siswa.index'))
                ->assertOk();

            // Create: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->post(route('siswa.store'), ['nama' => 'Test'])
                ->assertForbidden();

            // Update: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->put(route('siswa.update', $this->siswaRombelA->id), ['nama' => 'Test'])
                ->assertForbidden();

            // Delete: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->delete(route('siswa.destroy', $this->siswaRombelA->id))
                ->assertForbidden();
        }
    }

    public function test_wali_kelas_can_update_allowed_fields_for_students_in_their_rombel(): void
    {
        $wali = WaliSiswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $this->siswaRombelA->id,
            'nama' => 'Wali Asli',
        ]);

        $payload = [
            'alamat' => 'Alamat baru diubah wali kelas',
            'no_hp' => '08999888777',
            'wali' => [
                [
                    'id' => $wali->id,
                    'hubungan' => 'ayah',
                    'nama' => 'Nama Wali Diperbarui',
                    'pekerjaan' => 'Pedagang',
                    'no_hp' => '08777666555',
                    'alamat' => 'Alamat baru diubah wali kelas',
                ],
            ],
        ];

        $response = $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('siswa.update', $this->siswaRombelA->id), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('siswa', [
            'id' => $this->siswaRombelA->id,
            'alamat' => 'Alamat baru diubah wali kelas',
            'no_hp' => '08999888777',
        ]);

        $this->assertDatabaseHas('wali_siswa', [
            'id' => $wali->id,
            'nama' => 'Nama Wali Diperbarui',
            'pekerjaan' => 'Pedagang',
        ]);
    }

    public function test_wali_kelas_update_rejects_rogue_payload_on_master_fields(): void
    {
        // Wali kelas mencoba mengubah NISN dan Nama siswa (payload nakal)
        $payload = [
            'alamat' => 'Alamat baru',
            'nama' => 'Nama Diganti Nakal',
            'nisn' => '0099999999',
        ];

        $response = $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('siswa.update', $this->siswaRombelA->id), $payload);

        $response->assertSessionHasErrors(['nama', 'nisn']);

        // Data tidak berubah
        $this->assertDatabaseMissing('siswa', ['nama' => 'Nama Diganti Nakal']);
    }

    public function test_wali_kelas_cannot_update_student_in_other_rombel_receives_403(): void
    {
        $payload = [
            'alamat' => 'Alamat coba ubah rombel B',
        ];

        $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('siswa.update', $this->siswaRombelB->id), $payload)
            ->assertForbidden();
    }

    public function test_wali_kelas_cannot_delete_student_receives_403(): void
    {
        $this->actingAs($this->userWaliKelas)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.destroy', $this->siswaRombelA->id))
            ->assertForbidden();
    }

    public function test_guru_and_orang_tua_are_forbidden_by_default(): void
    {
        foreach (['guru', 'orang_tua'] as $role) {
            $user = $this->createUserWithRole($role);

            // View Any: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->get(route('siswa.index'))
                ->assertForbidden();

            // Create: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->post(route('siswa.store'), ['nama' => 'Test'])
                ->assertForbidden();

            // Update: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->put(route('siswa.update', $this->siswaRombelA->id), ['alamat' => 'Test'])
                ->assertForbidden();

            // Delete: 403
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->delete(route('siswa.destroy', $this->siswaRombelA->id))
                ->assertForbidden();
        }
    }
}
