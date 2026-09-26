<?php

namespace Database\Factories;

use App\Models\MataPelajaran;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MataPelajaran>
 */
class MataPelajaranFactory extends Factory
{
    protected $model = MataPelajaran::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = [
            ['kode' => 'MTK', 'nama' => 'Matematika', 'kelompok' => 'umum_a', 'bobot' => 'berat', 'ruang' => null],
            ['kode' => 'BIN', 'nama' => 'Bahasa Indonesia', 'kelompok' => 'umum_a', 'bobot' => 'sedang', 'ruang' => null],
            ['kode' => 'BIG', 'nama' => 'Bahasa Inggris', 'kelompok' => 'umum_a', 'bobot' => 'sedang', 'ruang' => null],
            ['kode' => 'FIS', 'nama' => 'Fisika', 'kelompok' => 'peminatan', 'bobot' => 'berat', 'ruang' => 'laboratorium'],
            ['kode' => 'KIM', 'nama' => 'Kimia', 'kelompok' => 'peminatan', 'bobot' => 'berat', 'ruang' => 'laboratorium'],
            ['kode' => 'BIO', 'nama' => 'Biologi', 'kelompok' => 'peminatan', 'bobot' => 'sedang', 'ruang' => 'laboratorium'],
            ['kode' => 'PJK', 'nama' => 'Pendidikan Jasmani & Olahraga', 'kelompok' => 'umum_b', 'bobot' => 'ringan', 'ruang' => 'lapangan'],
            ['kode' => 'RPL-DAS', 'nama' => 'Dasar Pemrograman', 'kelompok' => 'kejuruan', 'bobot' => 'berat', 'ruang' => 'laboratorium'],
        ];

        $selected = fake()->randomElement($subjects);
        $num = fake()->unique()->numberBetween(10, 999);

        return [
            'sekolah_id' => Sekolah::factory(),
            'kode' => $selected['kode'].'-'.$num,
            'nama' => $selected['nama'],
            'kelompok' => $selected['kelompok'],
            'tingkat' => fake()->randomElement([10, 11, 12, null]),
            'jurusan_id' => null,
            'bobot_beban_kognitif' => $selected['bobot'],
            'butuh_ruang_kategori' => $selected['ruang'],
            'is_aktif' => true,
        ];
    }
}
