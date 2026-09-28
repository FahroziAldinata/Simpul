# Hasil Pengujian Performa & Benchmark Database — Minggu 5 (Modul Siswa)

Tanggal: 28 September 2026  
Lingkungan: Docker Sail (PHP 8.3, PostgreSQL 16, Laravel 13)

---

## 1. Spesifikasi Pengujian

Pengujian dilakukan secara otomatis menggunakan skenario benchmark pada dataset riil berukuran menengah:
- **Jumlah Siswa Terdaftar:** 500 siswa
- **Jumlah Data Orang Tua / Wali:** 1.000 record (`wali_siswa`)
- **Jumlah Penempatan Rombel:** 500 record (`anggota_rombel`) pada 10 rombel aktif
- **Konfigurasi Pagination:** 25 data per halaman (`LIMIT 25 OFFSET 0`)
- **Peran Pengguna:** Operator Sekolah

---

## 2. Metrik Hasil Pengujian

| Metrik | Target DoD | Hasil Aktual | Status |
|---|---|---|---|
| **Waktu Eksekusi Endpoint (`GET /siswa`)** | < 1.000 ms (1 detik) | **118.97 ms** | **Lulus (8.4x lebih cepat)** |
| **Jumlah Kueri SQL Total** | Terbatas (Bebas N+1) | **14 Kueri** (konstan) | **Lulus (Zero N+1)** |
| **Kueri Data Siswa & Relasi Eager Load** | Bebas N+1 | **5 Kueri** | **Lulus** |
| **Kueri Filter Kelengkapan Data** | SQL Query Scope (Bukan Accessor) | **Didukung Indeks SQL** | **Lulus** |

---

## 3. Rincian Eksekusi Kueri SQL

Dari total 14 kueri yang tercatat pada `DB::getQueryLog()`:
- **7 Kueri:** Autentikasi sesi, tenant context resolver, dan cache/permission spatie.
- **1 Kueri:** Pengecekan semester aktif (`select * from semester where is_aktif = true and sekolah_id = ? limit 1`).
- **1 Kueri:** Perhitungan agregat total pagination (`select count(*) as aggregate from siswa where sekolah_id = ? and deleted_at is null`).
- **1 Kueri:** Pengambilan batch siswa halaman aktif (`select * from siswa where sekolah_id = ? and deleted_at is null order by nama asc limit 25 offset 0`).
- **1 Kueri:** Eager load wali siswa (`select * from wali_siswa where siswa_id in (...) and sekolah_id = ?`).
- **1 Kueri:** Eager load anggota rombel aktif (`select * from anggota_rombel where exists (...) and siswa_id in (...) and sekolah_id = ?`).
- **1 Kueri:** Eager load detail rombel (`select * from rombel where id in (...) and sekolah_id = ?`).
- **1 Kueri:** Data opsi dropdown rombel pada filter (`select id, nama, tingkat from rombel where semester_id = ? and sekolah_id = ? order by tingkat, nama`).

Semua kueri data memanfaatkan indeks komposit `['sekolah_id', 'siswa_id']` dan indeks partial `siswa_nisn_unique` serta indeks `['nama', 'nisn', 'nik']` sehingga seluruh kueri database selesai dalam rentang **0.72 ms s/d 1.38 ms** per kueri.
