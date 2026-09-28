<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Konvensi Penamaan Permission SIMPUL (PRD 4.3):
 *
 * 1. Seluruh permission WAJIB memakai format granular `{modul}.{aksi}`, contoh:
 *    - `pegawai.view`, `pegawai.create`, `pegawai.update`, `pegawai.delete`
 *    - `audit_log.view`
 *    - `data_induk.view`, `data_induk.create`, `data_induk.update`, `data_induk.delete`
 *    - `siswa.view`, `siswa.create`, `siswa.update`, `siswa.delete`
 *    - `jadwal.view`, `jadwal.create`, `jadwal.update`, `jadwal.delete`
 *
 * 2. TIDAK ADA permission majemuk/alias seperti `.manage`.
 *    Jika suatu role memiliki wewenang CRUD penuh (misal Operator = CRUD Pegawai pada Matriks 4.2),
 *    role tersebut diberikan kumpulan permission granular yang lengkap:
 *    `['pegawai.view', 'pegawai.create', 'pegawai.update', 'pegawai.delete']`.
 *
 * 3. Scoping kontekstual (misal guru hanya melihat data dirinya sendiri, atau wali kelas
 *    hanya mengelola rombelnya) ditegakkan di level Policy/Scope, bukan dengan memecah nama permission.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Daftar Permission Granular (Minggu 3 & pondasi modul ke depan)
        $permissions = [
            // Audit Log
            'audit_log.view',

            // Pegawai
            'pegawai.view',
            'pegawai.create',
            'pegawai.update',
            'pegawai.delete',

            // Data Induk (Persiapan Minggu 4)
            'data_induk.view',
            'data_induk.create',
            'data_induk.update',
            'data_induk.delete',

            // Data Siswa (Persiapan Minggu 5)
            'siswa.view',
            'siswa.create',
            'siswa.update',
            'siswa.delete',

            // Absensi & Izin (Persiapan Minggu 5/6)
            'absensi.view',
            'absensi.create',
            'izin.ajukan',
            'izin.approve',

            // Jadwal (Persiapan Minggu 7+)
            'jadwal.view',
            'jadwal.create',
            'jadwal.update',
            'jadwal.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 7 Peran sesuai Matriks PRD Bagian 4.2
        $roles = [
            'super_admin' => $permissions, // Super Admin memiliki semua permission (dan bypass di Gate::before)
            // Operator mendapat CRU Tahun Ajaran (tanpa rollover). Baris 'Tahun Ajaran (rollover)' di PRD 4.2 ditafsirkan sebagai aksi rollover saja, yang baru diimplementasikan kemudian. Keputusan final ada di user.
            'operator' => [
                'data_induk.view', 'data_induk.create', 'data_induk.update',
                'siswa.view', 'siswa.create', 'siswa.update', 'siswa.delete',
                'pegawai.view', 'pegawai.create', 'pegawai.update', 'pegawai.delete',
                'jadwal.view',
                'absensi.view', 'absensi.create',
                'izin.ajukan',
            ],
            'kepsek' => [
                'data_induk.view',
                'siswa.view',
                'pegawai.view',
                'jadwal.view',
                'absensi.view', 'absensi.create',
                'izin.ajukan', 'izin.approve',
                'audit_log.view',
            ],
            'waka_kurikulum' => [
                'data_induk.view',
                'siswa.view',
                'pegawai.view',
                'jadwal.view', 'jadwal.create', 'jadwal.update', 'jadwal.delete',
                'absensi.view', 'absensi.create',
                'izin.ajukan',
            ],
            'guru' => [
                'siswa.view',
                'pegawai.view',
                'jadwal.view',
                'absensi.view', 'absensi.create',
                'izin.ajukan',
            ],
            'wali_kelas' => [
                'siswa.view', 'siswa.update',
                'pegawai.view',
                'jadwal.view',
                'absensi.view', 'absensi.create',
                'izin.ajukan',
            ],
            'orang_tua' => [
                'siswa.view',
                'jadwal.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
