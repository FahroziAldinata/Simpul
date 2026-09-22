# ADR-0002: Pemilihan PostgreSQL 16 Dibandingkan MySQL

## Status

Diterima (Accepted)

## Konteks

Aplikasi SIMPUL memiliki karakteristik unik yang membutuhkan fitur basis data tingkat lanjut, khususnya pada modul penjadwalan pelajaran (mencegah bentrok jadwal secara deterministik), penyimpanan metadata dinamis (impor data dan formulir fleksibel), serta status keaktifan periode akademik. MySQL/MariaDB sering menjadi pilihan default dalam ekosistem Laravel, namun memiliki keterbatasan mendasar untuk kebutuhan-kebutuhan tersebut.

## Keputusan

Memilih **PostgreSQL 16** sebagai mesin basis data utama sistem SIMPUL.

### Alasan Teknis Utama:

1. **Exclusion Constraint (`EXCLUDE USING gist`):**
   Pada tabel `jadwal_pelajaran`, validasi bentrok guru, ruang, atau rombel tidak boleh hanya bergantung pada validasi aplikasi yang rentan terhadap kondisi balapan (race condition). PostgreSQL mendukung tipe data rentang (`int4range`) dan operator overlap (`&&`) dengan indeks GIST, sehingga bentrok slot waktu ditolak langsung di level ACID transaksi database. Fitur ini tidak ada di MySQL.
2. **Dukungan `jsonb` Asli & Indeks GIN:**
   Kolom seperti `hari_tidak_mengajar` pada pegawai, `pemetaan_kolom` pada impor massal, dan log perubahan data disimpan dalam format biner `jsonb` dengan kemampuan query ekspresif dan pengindeksan GIN berkecepatan tinggi.
3. **Partial Unique Indexes:**
   SIMPUL memerlukan aturan bisnis seperti: _"Hanya ada satu Tahun Ajaran yang berstatus aktif per sekolah"_. PostgreSQL mendukung `CREATE UNIQUE INDEX ... WHERE is_aktif = true`. Pada MySQL, aturan ini sulit ditegakkan secara deklaratif tanpa trigger atau kolom sintetis tambahan.
4. **Pencarian Teks Penuh (`tsvector` & `tsquery`):**
   Pencarian data siswa dan pegawai berdasarkan nama atau NIK/NISN dapat memanfaatkan mesin pencarian bawaan PostgreSQL yang hemat sumber daya tanpa perlu mengoperasikan klaster Elasticsearch terpisah.

### Catatan Terkait Ekstensi Spasial:

Meskipun PostgreSQL mendukung PostGIS, SIMPUL **tidak memerlukan** image PostGIS karena kebutuhan geolokasi hanya membandingkan jarak titik koordinat GPS (`latitude`, `longitude`) terhadap radius sekolah. Formula jarak Haversine di level query atau kalkulasi aplikasi sudah sangat memadai dengan image resmi `postgres:16` standar.

## Konsekuensi

- **Positif:** Integritas jadwal terjamin 100% di level penyimpanan; query JSON berkinerja tinggi; penegakan aturan bisnis parsial bersih.
- **Negatif:** Syntax migrasi Laravel yang memanfaatkan constraint khusus PostgreSQL memerlukan penulisan raw SQL di dalam file migration; developer lokal wajib menjalankan container PostgreSQL.
