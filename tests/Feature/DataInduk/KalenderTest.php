<?php

namespace Tests\Feature\DataInduk;

use App\Models\HariLibur;
use App\Models\JamKerja;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KalenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_view_kalender_and_manage_jam_kerja(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('kalender.index'))
            ->assertOk();

        // Update operational schedule
        $jamKerjaPayload = [
            'hari' => [
                [
                    'hari' => 1,
                    'is_libur' => false,
                    'jam_masuk' => '07:00',
                    'jam_pulang' => '15:00',
                    'jumlah_jam_pelajaran' => 8,
                ],
                [
                    'hari' => 2,
                    'is_libur' => false,
                    'jam_masuk' => '07:00',
                    'jam_pulang' => '15:00',
                    'jumlah_jam_pelajaran' => 8,
                ],
                [
                    'hari' => 3,
                    'is_libur' => false,
                    'jam_masuk' => '07:00',
                    'jam_pulang' => '15:00',
                    'jumlah_jam_pelajaran' => 8,
                ],
                [
                    'hari' => 4,
                    'is_libur' => false,
                    'jam_masuk' => '07:00',
                    'jam_pulang' => '15:00',
                    'jumlah_jam_pelajaran' => 8,
                ],
                [
                    'hari' => 5,
                    'is_libur' => false,
                    'jam_masuk' => '07:00',
                    'jam_pulang' => '11:30',
                    'jumlah_jam_pelajaran' => 5,
                ],
                [
                    'hari' => 6,
                    'is_libur' => true,
                    'jam_masuk' => null,
                    'jam_pulang' => null,
                    'jumlah_jam_pelajaran' => 0,
                ],
                [
                    'hari' => 7,
                    'is_libur' => true,
                    'jam_masuk' => null,
                    'jam_pulang' => null,
                    'jumlah_jam_pelajaran' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('kalender.jam-kerja.update'), $jamKerjaPayload);

        $response->assertRedirect();

        // 8*4 + 5 = 37 total slot
        $this->assertSame(37, JamKerja::totalSlotMingguan($sekolah->id));
    }

    public function test_operator_can_add_and_delete_hari_libur(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $payload = [
            'tanggal_mulai' => '2026-12-25',
            'tanggal_selesai' => '2026-12-26',
            'keterangan' => 'Libur Hari Raya Natal',
            'jenis' => 'nasional',
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('kalender.hari-libur.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('hari_libur', [
            'sekolah_id' => $sekolah->id,
            'keterangan' => 'Libur Hari Raya Natal',
        ]);

        $holiday = HariLibur::where('sekolah_id', $sekolah->id)->firstOrFail();

        // Operator delete is rejected with 403 Forbidden
        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('kalender.hari-libur.destroy', $holiday))
            ->assertForbidden();

        $this->assertDatabaseHas('hari_libur', ['id' => $holiday->id]);

        // Super Admin can delete holiday
        setPermissionsTeamId($sekolah->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('kalender.hari-libur.destroy', $holiday))
            ->assertRedirect();

        $this->assertDatabaseMissing('hari_libur', ['id' => $holiday->id]);
    }

    public function test_tenant_isolation_404_when_deleting_other_school_holiday(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        setPermissionsTeamId($sekolahA->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $holidayB = HariLibur::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'keterangan' => 'Libur Sekolah B',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('kalender.hari-libur.destroy', $holidayB))
            ->assertNotFound();

        $this->assertDatabaseHas('hari_libur', ['id' => $holidayB->id]);
    }

    public function test_fallback_default_40_jam_if_jam_kerja_empty(): void
    {
        $sekolah = Sekolah::factory()->create();
        $this->assertSame(40, JamKerja::totalSlotMingguan($sekolah->id));
    }
}
