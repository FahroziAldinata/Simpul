<?php

namespace Database\Seeders;

use App\Models\JenisIzin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed jenis izin default global (sekolah_id = null).
 *
 * Bisa di-override per sekolah dengan baris sekolah_id terisi.
 * Sekolah yang tidak membuat jenis izin sendiri akan memakai
 * daftar ini sebagai fallback (query di JenisIzinController).
 */
class JenisIzinSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'nama' => 'Izin',
                'kode' => 'izin',
                'butuh_lampiran' => false,
                'butuh_persetujuan' => true,
                'mengurangi_kuota_cuti' => false,
                'urutan_approval' => ['kepsek'],
            ],
            [
                'nama' => 'Sakit',
                'kode' => 'sakit',
                'butuh_lampiran' => true,  // surat dokter
                'butuh_persetujuan' => false, // langsung disetujui, lampiran cukup
                'mengurangi_kuota_cuti' => false,
                'urutan_approval' => [],
            ],
            [
                'nama' => 'Cuti Tahunan',
                'kode' => 'cuti',
                'butuh_lampiran' => false,
                'butuh_persetujuan' => true,
                'mengurangi_kuota_cuti' => true,
                'urutan_approval' => ['kepsek'],
            ],
            [
                'nama' => 'Dinas Luar',
                'kode' => 'dinas',
                'butuh_lampiran' => true,  // surat tugas
                'butuh_persetujuan' => true,
                'mengurangi_kuota_cuti' => false,
                'urutan_approval' => ['kepsek'],
            ],
        ];

        foreach ($defaults as $data) {
            JenisIzin::firstOrCreate(
                ['sekolah_id' => null, 'kode' => $data['kode']],
                array_merge($data, [
                    'id' => Str::uuid()->toString(),
                    'is_aktif' => true,
                ])
            );
        }
    }
}
