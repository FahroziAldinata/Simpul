<?php

namespace Tests\Feature\Absensi;

use App\Models\JamKerja;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JamKerjaKelompokTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');
    }

    public function test_operator_dapat_mengatur_jam_kerja_per_kelompok_pegawai_dan_toleransi(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.jam-kerja.upsert'), [
                'kelompok' => 'guru',
                'hari' => 1,
                'jam_masuk' => '06:45',
                'jam_pulang' => '14:30',
                'toleransi_menit' => 10,
                'is_libur' => false,
                'jumlah_jam_pelajaran' => 8,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jam_kerja', [
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'guru',
            'hari' => 1,
            'jam_masuk' => '06:45:00',
            'jam_pulang' => '14:30:00',
            'toleransi_menit' => 10,
            'is_libur' => false,
            'jumlah_jam_pelajaran' => 8,
        ]);
    }

    public function test_upsert_jam_kerja_memperbarui_data_yang_ada_tanpa_duplikasi(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'guru',
            'hari' => 2,
            'jam_masuk' => '07:00',
            'jam_pulang' => '14:00',
            'toleransi_menit' => 15,
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.jam-kerja.upsert'), [
                'kelompok' => 'guru',
                'hari' => 2,
                'jam_masuk' => '07:15',
                'jam_pulang' => '15:00',
                'toleransi_menit' => 20,
                'is_libur' => false,
                'jumlah_jam_pelajaran' => 7,
            ]);

        $response->assertRedirect();

        $this->assertEquals(1, JamKerja::where('sekolah_id', $this->sekolah->id)
            ->where('kelompok', 'guru')
            ->where('hari', 2)
            ->count());

        $this->assertDatabaseHas('jam_kerja', [
            'id' => $jamKerja->id,
            'toleransi_menit' => 20,
            'jam_masuk' => '07:15:00',
        ]);
    }

    public function test_operator_dapat_menghapus_jadwal_jam_kerja(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'guru',
            'hari' => 3,
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('absensi.jam-kerja.destroy', $jamKerja->id));

        $response->assertRedirect();

        $this->assertDatabaseMissing('jam_kerja', [
            'id' => $jamKerja->id,
        ]);
    }
}
