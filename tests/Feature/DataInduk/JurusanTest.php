<?php

namespace Tests\Feature\DataInduk;

use App\Models\Jurusan;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JurusanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_smk_can_view_and_create_jurusan(): void
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'smk']);

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('jurusan.index'))
            ->assertOk();

        $payload = [
            'kode' => 'RPL',
            'nama' => 'Rekayasa Perangkat Lunak',
            'bidang_keahlian' => 'Teknologi Informasi',
            'program_keahlian' => 'Pengembangan Perangkat Lunak',
            'is_aktif' => true,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('jurusan.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('jurusan', [
            'sekolah_id' => $sekolah->id,
            'kode' => 'RPL',
            'nama' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    public function test_rejects_duplicate_kode_within_same_school(): void
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'smk']);

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        Jurusan::factory()->create([
            'sekolah_id' => $sekolah->id,
            'kode' => 'TKJ',
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('jurusan.store'), [
                'kode' => 'TKJ',
                'nama' => 'Teknik Komputer dan Jaringan',
            ]);

        $response->assertSessionHasErrors('kode');
    }

    public function test_sd_cannot_create_jurusan(): void
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'sd']);

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('jurusan.store'), [
                'kode' => 'IPA',
                'nama' => 'Ilmu Pengetahuan Alam',
            ]);

        $response->assertStatus(422);
    }

    public function test_tenant_isolation_404_when_deleting_other_school_jurusan(): void
    {
        $sekolahA = Sekolah::factory()->create(['jenjang' => 'smk']);
        $sekolahB = Sekolah::factory()->create(['jenjang' => 'smk']);

        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        setPermissionsTeamId($sekolahA->id);
        $operatorA->assignRole('operator');

        $jurusanB = Jurusan::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'kode' => 'AKL',
        ]);

        $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('jurusan.destroy', $jurusanB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('jurusan', ['id' => $jurusanB->id]);
    }
}
