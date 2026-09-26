<?php

namespace Database\Factories;

use App\Models\Jurusan;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jurusan>
 */
class JurusanFactory extends Factory
{
    protected $model = Jurusan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jurusanList = [
            ['kode' => 'RPL', 'nama' => 'Rekayasa Perangkat Lunak', 'bidang' => 'Teknologi Informasi', 'program' => 'Pengembangan Perangkat Lunak dan Gim'],
            ['kode' => 'TKJ', 'nama' => 'Teknik Komputer dan Jaringan', 'bidang' => 'Teknologi Informasi', 'program' => 'Teknik Jaringan Komputer dan Telekomunikasi'],
            ['kode' => 'IPA', 'nama' => 'Ilmu Pengetahuan Alam', 'bidang' => 'Akademik', 'program' => 'MIPA'],
            ['kode' => 'IPS', 'nama' => 'Ilmu Pengetahuan Sosial', 'bidang' => 'Akademik', 'program' => 'IPS'],
            ['kode' => 'AKL', 'nama' => 'Akuntansi dan Keuangan Lembaga', 'bidang' => 'Bisnis dan Manajemen', 'program' => 'Akuntansi'],
        ];

        $selected = fake()->randomElement($jurusanList);

        return [
            'sekolah_id' => Sekolah::factory(),
            'kode' => $selected['kode'].fake()->unique()->numberBetween(1, 999),
            'nama' => $selected['nama'],
            'bidang_keahlian' => $selected['bidang'],
            'program_keahlian' => $selected['program'],
            'is_aktif' => true,
        ];
    }
}
