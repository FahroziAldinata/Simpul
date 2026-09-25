<?php

namespace Tests\Feature\Tenancy;

use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_from_sekolah_a_accessing_sekolah_b_data_returns_404_not_403(): void
    {
        // 1. Arrange: Buat dua sekolah terpisah
        $sekolahA = Sekolah::factory()->create(['nama' => 'SMA Negeri 1']);
        $sekolahB = Sekolah::factory()->create(['nama' => 'SMA Negeri 2']);

        // 2. Buat user milik Sekolah A
        $userA = User::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'email_verified_at' => now(),
        ]);

        // 3. Buat pegawai milik Sekolah B (tanpa session aktif agar tidak terpengaruh auto-fill)
        $pegawaiB = Pegawai::withoutGlobalScopes()->create([
            'sekolah_id' => $sekolahB->id,
            'nama' => 'Pegawai Rahasia Sekolah B',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        // 4. Act: User Sekolah A mencoba mengakses pegawai Sekolah B
        $response = $this->actingAs($userA)->get(route('pegawai.show', $pegawaiB));

        // 5. Assert: Wajib mengembalikan 404 Not Found (bukan 403)
        // Hal ini penting sesuai PRD SIMPUL AC4 agar keberadaan record tidak bocor ke tenant lain
        $response->assertNotFound();
        $this->assertNotEquals(403, $response->getStatusCode(), 'Harus mengembalikan 404 bukan 403');
    }

    public function test_user_from_sekolah_a_can_access_own_sekolah_data(): void
    {
        $sekolahA = Sekolah::factory()->create();

        $userA = User::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'email_verified_at' => now(),
        ]);

        $pegawaiA = Pegawai::withoutGlobalScopes()->create([
            'sekolah_id' => $sekolahA->id,
            'nama' => 'Pegawai Sekolah A',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        $response = $this->actingAs($userA)->get(route('pegawai.show', $pegawaiA));

        $response->assertOk();
        $response->assertJson([
            'id' => $pegawaiA->id,
            'nama' => 'Pegawai Sekolah A',
            'sekolah_id' => $sekolahA->id,
        ]);
    }
}
