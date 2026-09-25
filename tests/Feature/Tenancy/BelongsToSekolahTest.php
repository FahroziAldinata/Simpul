<?php

namespace Tests\Feature\Tenancy;

use App\Models\Pegawai;
use App\Models\Scopes\SekolahScope;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToSekolahTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_automatically_fills_sekolah_id_from_session_on_creation(): void
    {
        $sekolah = Sekolah::factory()->create();
        session(['sekolah_id' => $sekolah->id]);

        $pegawai = Pegawai::create([
            'nama' => 'Budi Santoso',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        $this->assertEquals($sekolah->id, $pegawai->sekolah_id);
        $this->assertDatabaseHas('pegawai', [
            'id' => $pegawai->id,
            'sekolah_id' => $sekolah->id,
            'nama' => 'Budi Santoso',
        ]);
    }

    public function test_it_automatically_fills_sekolah_id_from_authenticated_user_when_no_session(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($user);

        $pegawai = Pegawai::create([
            'nama' => 'Siti Rahma',
            'jenis' => 'tu',
            'status_kepegawaian' => 'pppk',
        ]);

        $this->assertEquals($sekolah->id, $pegawai->sekolah_id);
    }

    public function test_it_scopes_queries_to_active_sekolah(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        $pegawaiA = Pegawai::create([
            'sekolah_id' => $sekolahA->id,
            'nama' => 'Guru Sekolah A',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        $pegawaiB = Pegawai::create([
            'sekolah_id' => $sekolahB->id,
            'nama' => 'Guru Sekolah B',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        session(['sekolah_id' => $sekolahA->id]);

        $results = Pegawai::all();

        $this->assertTrue($results->contains($pegawaiA));
        $this->assertFalse($results->contains($pegawaiB));
        $this->assertCount(1, $results);

        // Accessing pegawaiB in sekolahA context returns null
        $this->assertNull(Pegawai::find($pegawaiB->id));

        // findOrFail throws ModelNotFoundException (which turns into 404 in HTTP routes)
        $this->expectException(ModelNotFoundException::class);
        Pegawai::findOrFail($pegawaiB->id);
    }

    public function test_it_allows_bypassing_scope_via_without_global_scope(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        Pegawai::create([
            'sekolah_id' => $sekolahA->id,
            'nama' => 'Guru A',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        Pegawai::create([
            'sekolah_id' => $sekolahB->id,
            'nama' => 'Guru B',
            'jenis' => 'guru',
            'status_kepegawaian' => 'pns',
        ]);

        session(['sekolah_id' => $sekolahA->id]);

        $all = Pegawai::withoutGlobalScope(SekolahScope::class)->get();
        $this->assertCount(2, $all);
    }
}
