<?php

namespace Tests\Feature\Absensi;

use App\Models\Absensi;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AbsensiManualTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');

        $this->pegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Siti Rahmawati',
        ]);
    }

    public function test_absen_manual_oleh_operator_berhasil_dengan_alasan_dan_tercatat_di_audit_log(): void
    {
        $today = now()->toDateString();

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.manual.simpan'), [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => $today,
                'jenis' => 'masuk',
                'alasan_manual' => 'Kamera smartphone pegawai bermasalah saat scan di gerbang.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('absensi', [
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => $today,
            'jenis' => 'masuk',
            'sumber' => 'manual',
            'dicatat_oleh' => $this->operator->id,
            'alasan_manual' => 'Kamera smartphone pegawai bermasalah saat scan di gerbang.',
        ]);

        // Verifikasi audit log (spatie/activitylog via LogsSimpulActivity)
        $latestLog = Activity::where('subject_type', Absensi::class)
            ->where('causer_id', $this->operator->id)
            ->latest()
            ->first();

        $this->assertNotNull($latestLog, 'Pencatatan absen manual wajib menghasilkan entri di audit log.');
        $this->assertEquals($this->operator->id, $latestLog->causer_id);
    }

    public function test_absen_manual_wajib_menyertakan_alasan(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.manual.simpan'), [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => now()->toDateString(),
                'jenis' => 'masuk',
                'alasan_manual' => '', // Kosong
            ]);

        $response->assertSessionHasErrors(['alasan_manual']);
        $this->assertDatabaseMissing('absensi', [
            'pegawai_id' => $this->pegawai->id,
        ]);
    }

    public function test_absen_manual_untuk_tanggal_lebih_dari_7_hari_lalu_ditolak(): void
    {
        // 8 hari lalu
        $tanggalLampau = now()->subDays(8)->toDateString();

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.manual.simpan'), [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => $tanggalLampau,
                'jenis' => 'masuk',
                'alasan_manual' => 'Lupa absen 8 hari lalu.',
            ]);

        $response->assertSessionHasErrors(['tanggal']);
        $this->assertDatabaseMissing('absensi', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => $tanggalLampau,
        ]);
    }

    public function test_absen_manual_untuk_tanggal_di_masa_depan_ditolak(): void
    {
        $tanggalDepan = now()->addDay()->toDateString();

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.manual.simpan'), [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => $tanggalDepan,
                'jenis' => 'masuk',
                'alasan_manual' => 'Absen untuk besok.',
            ]);

        $response->assertSessionHasErrors(['tanggal']);
        $this->assertDatabaseMissing('absensi', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => $tanggalDepan,
        ]);
    }

    public function test_guru_tidak_bisa_mengakses_absen_manual(): void
    {
        $guruUser = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $guruUser->assignRole('guru');

        $response = $this->actingAs($guruUser)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('absensi.manual.index'));

        $response->assertStatus(403);
    }

    public function test_operator_tidak_bisa_mencatat_absen_pegawai_sekolah_lain(): void
    {
        $sekolahB = Sekolah::factory()->create();
        $pegawaiB = Pegawai::factory()->create(['sekolah_id' => $sekolahB->id]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('absensi.manual.simpan'), [
                'pegawai_id' => $pegawaiB->id,
                'tanggal' => now()->toDateString(),
                'jenis' => 'masuk',
                'alasan_manual' => 'Mencoba absen pegawai tenant lain.',
            ]);

        // Tenant isolation: 404
        $response->assertStatus(404);
        $this->assertDatabaseMissing('absensi', [
            'pegawai_id' => $pegawaiB->id,
        ]);
    }
}
