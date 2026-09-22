# ADR-0000: Ruang Lingkup Portofolio — Kedalaman di Atas Keluasan

## Status

Diterima (Accepted)

## Konteks

Sistem Informasi Manajemen Pembelajaran & Urusan Lembaga (SIMPUL) dirancang sebagai proyek portofolio rekayasa perangkat lunak tingkat mahir. Banyak sistem informasi sekolah di pasaran mencakup 15–20 modul secara dangkal (hanya CRUD dasar). Pendekatan tersebut tidak mencerminkan kompleksitas dunia nyata dalam hal konkurensi, kebenaran penjadwalan, integritas data lintas tenant, dan keandalan operasional.

## Keputusan

Fokus pengembangan SIMPUL dibatasi secara ketat pada **4 modul inti** dengan tingkat kedalaman dan ketahanan setara produksi:

1. **Data Induk & Multi-Tenancy:** Struktur hierarki sekolah, tahun ajaran, semester, rombel, dan kesiswaan dengan isolasi data yang teruji otomatis.
2. **Absensi Pegawai & Geofencing:** Presensi berbasis token QR dinamis, validasi radius GPS, toleransi jam kerja, dan idempotency key offline-first.
3. **Penyusunan Jadwal Otomatis (Constraint Satisfaction Problem):** Algoritma penjadwalan dengan hard/soft constraints dan penegakan bentrok langsung di level database (`EXCLUDE USING gist`).
4. **Impor Data Massal:** Pipeline dua tahap (validasi & pratinjau sebelum commit) dengan toleransi kegagalan per baris dan kemampuan rollback batch.

Modul sekunder seperti modul SPP/keuangan kompleks, perpustakaan, e-learning/CBT, dan PPDB sengaja ditiadakan dari ruang lingkup awal agar tidak mendegradasi kualitas implementasi teknis modul utama.

## Catatan Deviasi Sadar dari PRD SIMPUL

1. **Peniadaan Riset Lapangan (Minggu 1):**
   Proyek ini ditujukan untuk kebutuhan portofolio teknis dan penguasaan arsitektur, bukan deployment langsung ke institusi komersial saat ini. Field database, alur kerja, dan batasan operasional diadopsi langsung dari dokumen PRD tanpa wawancara lapangan terhadap operator sekolah sungguhan. Keputusan ini diambil secara sadar dengan menerima trade-off bahwa field data mencerminkan spesifikasi PRD.
2. **Penggunaan Laravel 13 (Bukan Laravel 12):**
   Meskipun PRD 9.1 mencantumkan Laravel 12 sebagai acuan, proyek ini secara sengaja menggunakan versi mayor terbaru yaitu **Laravel 13** guna memanfaatkan kapabilitas performa, fitur core terbaru, dan dukungan jangka panjang framework.
3. **Penggunaan PostgreSQL 16 Standar (Tanpa Ekstensi PostGIS):**
   Berbeda dengan sistem berbasis spasial poligon (seperti PALMVISION), kebutuhan geolokasi SIMPUL hanya sebatas validasi jarak titik koordinat GPS (`latitude`, `longitude`) terhadap radius sekolah/titik absensi. Formula jarak lingkaran (Haversine) dapat diselesaikan secara efisien pada query standar atau kalkulasi backend, sehingga image `postgres:16` standar dipilih demi efisiensi resource dan portabilitas tanpa dependensi PostGIS.

## Konsekuensi

- **Positif:** Beban kognitif dan waktu tercurah 100% untuk menyelesaikan masalah arsitektural sulit (CSP solver, offline sync, exclusion constraint, tenant isolation).
- **Negatif:** Aplikasi tidak dapat langsung dipakai sebagai sistem manajemen sekolah menyeluruh (all-in-one ERP) tanpa penambahan modul administratif lain di masa mendatang.
