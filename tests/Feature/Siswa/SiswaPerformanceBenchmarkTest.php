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

        // 5. Measure query count & duration for 25 per page
        DB::flushQueryLog();
        DB::enableQueryLog();

        $startTime25 = microtime(true);
        $response25 = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('siswa.index', ['per_page' => 25]));
        $durationMs25 = (microtime(true) - $startTime25) * 1000;
        $queries25 = DB::getQueryLog();
        $queryCount25 = count($queries25);
        $response25->assertOk();

        // 6. Measure query count & duration for 100 per page
        DB::flushQueryLog();
        $startTime100 = microtime(true);
        $response100 = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('siswa.index', ['per_page' => 100]));
        $durationMs100 = (microtime(true) - $startTime100) * 1000;
        $queries100 = DB::getQueryLog();
        $queryCount100 = count($queries100);
        $response100->assertOk();

        // Save benchmark log data
        $benchmarkSummary = [
            'methodology' => 'DB::getQueryLog() and microtime() on seeded PostgreSQL database with 500 students, 1000 guardians, 10 classes',
            'total_students_in_database' => 500,
            'total_guardians' => 1000,
            'total_rombel_memberships' => 500,
            'benchmark_25_per_page' => [
                'page_size' => 25,
                'query_count' => $queryCount25,
                'duration_ms' => round($durationMs25, 2),
            ],
            'benchmark_100_per_page' => [
                'page_size' => 100,
                'query_count' => $queryCount100,
                'duration_ms' => round($durationMs100, 2),
            ],
            'queries_sample_25' => array_map(fn ($q) => [
                'sql' => $q['query'],
                'time_ms' => $q['time'],
            ], $queries25),
        ];

        file_put_contents(
            base_path('docs/benchmark-minggu-5.json'),
            json_encode($benchmarkSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Assertions
        $this->assertLessThanOrEqual(15, $queryCount25, "Query count for 25/page ({$queryCount25}) exceeds threshold.");
        $this->assertLessThan(1000, $durationMs25, "Duration for 25/page ({$durationMs25}ms) exceeds 1000ms.");

        $this->assertLessThanOrEqual(15, $queryCount100, "Query count for 100/page ({$queryCount100}) exceeds threshold.");
        $this->assertLessThan(1000, $durationMs100, "Duration for 100/page ({$durationMs100}ms) exceeds 1000ms.");
    }
}
