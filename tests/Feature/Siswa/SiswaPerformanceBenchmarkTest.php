<?php

namespace Tests\Feature\Siswa;

use App\Models\AnggotaRombel;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WaliSiswa;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiswaPerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_index_500_records_performance_and_query_count(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        // 1. Setup Sekolah, Tahun Ajaran, Semester
        $sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($sekolah->id);

        $tahunAjaran = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_aktif' => true,
        ]);
        $semester = Semester::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'is_aktif' => true,
        ]);

        // 2. Setup 10 Rombel
        $rombels = Rombel::factory()->count(10)->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
        ]);

        // 3. Seed 500 Siswa
        $siswaCollection = Siswa::factory()->count(500)->create([
            'sekolah_id' => $sekolah->id,
        ]);

        // Seed Wali & Anggota Rombel for all 500 siswa
        $waliData = [];
        $anggotaRombelData = [];
        $now = now();

        foreach ($siswaCollection as $idx => $s) {
            $selectedRombel = $rombels[$idx % 10];

            $waliData[] = [
                'id' => (string) Str::uuid(),
                'sekolah_id' => $sekolah->id,
                'siswa_id' => $s->id,
                'hubungan' => 'ayah',
                'nama' => 'Ayah dari '.$s->nama,
                'pekerjaan' => 'PNS',
                'no_hp' => '081234567890',
                'alamat' => $s->alamat,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $waliData[] = [
                'id' => (string) Str::uuid(),
                'sekolah_id' => $sekolah->id,
                'siswa_id' => $s->id,
                'hubungan' => 'ibu',
                'nama' => 'Ibu dari '.$s->nama,
                'pekerjaan' => 'Wiraswasta',
                'no_hp' => '081234567891',
                'alamat' => $s->alamat,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $anggotaRombelData[] = [
                'id' => (string) Str::uuid(),
                'sekolah_id' => $sekolah->id,
                'rombel_id' => $selectedRombel->id,
                'siswa_id' => $s->id,
                'semester_id' => $semester->id,
                'nomor_absen' => ($idx % 50) + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($waliData, 200) as $chunk) {
            WaliSiswa::insert($chunk);
        }

        foreach (array_chunk($anggotaRombelData, 200) as $chunk) {
            AnggotaRombel::insert($chunk);
        }

        // 4. Setup Operator User
        $operatorRole = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operator = User::factory()->create([
            'sekolah_id' => $sekolah->id,
        ]);
        $operator->assignRole($operatorRole);

        // 5. Measure query count & duration
        DB::flushQueryLog();
        DB::enableQueryLog();

        $startTime = microtime(true);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('siswa.index', ['per_page' => 25]));

        $durationMs = (microtime(true) - $startTime) * 1000;
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertOk();

        // Save benchmark log data
        $benchmarkSummary = [
            'total_students_in_database' => 500,
            'total_guardians' => 1000,
            'total_rombel_memberships' => 500,
            'page_size' => 25,
            'query_count' => $queryCount,
            'duration_ms' => round($durationMs, 2),
            'queries' => array_map(fn ($q) => [
                'sql' => $q['query'],
                'time_ms' => $q['time'],
            ], $queries),
        ];

        file_put_contents(
            base_path('docs/benchmark-minggu-5.json'),
            json_encode($benchmarkSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 6. Assertions
        // Query count must be <= 15 (Zero N+1 confirmed, constant query count)
        $this->assertLessThanOrEqual(15, $queryCount, "Query count {$queryCount} exceeds allowed threshold. Possible N+1 detected.");

        file_put_contents(
            base_path('docs/benchmark-minggu-5.json'),
            json_encode($benchmarkSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
