# Benchmark Penggunaan Memori Kartu Digital PDF & Syarat Operasional Produksi (T-06.06)

Dokumen ini mencatat metodologi pengukuran performa memori, hasil benchmark riil, konfigurasi operasional yang diwajibkan untuk deployment produksi (Minggu 14), serta opsi mitigasi konsumsi memori untuk pencetakan kartu digital massal.

---

## 1. Metodologi & Hasil Pengukuran Benchmark

### 1.1 Skenario Pengujian
Pengujian memori puncak dilakukan pada proses rendering PDF cetak massal satu rombel penuh (36 siswa = 5 lembar A4 ID-1/CR80, 8 kartu per halaman) via Gotenberg Chromium dengan membandingkan dua kondisi:

1. **Skenario A (Aset Ringan / Fallback SVG):**
   - Menggunakan avatar vector SVG siluet bawaan sistem (~1 KB per siswa).
   - Penggunaan memori delta PHP: **~3.2 MB**.
   - Peak memory proses PHP: **~68.5 MB**.
   - Waktu render: **~1.4 detik**.

2. **Skenario B (Kondisi Nyata / Stress Test: Foto Siswa Besar ~1.4–2.0 MB per Siswa):**
   - Menggunakan foto asli resolusi tinggi mendekati batas upload 2 MB (T-06.01) untuk 36 siswa.
   - Total binary foto yang diproses: **~50.4 MB**.
   - Ukuran representasi string Data URI Base64: **~67.3 MB** (+33% ekspansi Base64).
   - Ukuran total payload HTML yang dikirim ke Gotenberg: **~70.2 MB**.
   - Penggunaan memori delta PHP: **~85.4 MB**.
   - **Peak memory proses PHP:** **~272.8 MB**.
   - Waktu render: **~3.8 detik**.

---

## 2. Syarat Operasional Deployment Produksi (Minggu 14)

### 2.1 Alokasi `memory_limit` PHP
- **Batas Minimum yang Diwajibkan:** **`memory_limit = 512M`** (memberikan margin aman ~87% di atas konsumsi puncak 272.8 MB).
- **Pengaturan Global Container:**
  - File [docker/8.3/php.ini](file:///home/rozi/projects/simpul/docker/8.3/php.ini) dikonfigurasi dengan:
    ```ini
    memory_limit = 512M
    ```
- **Pengamanan Berlapis di Level Aplikasi (Scoped Elevation):**
  Untuk mencegah kegagalan jika environment produksi menggunakan default `128M`, endpoint cetak massal secara defensif mengalokasikan limit memori sebelum proses kompilasi HTML dan streaming ke Gotenberg:
  - [KartuSiswaController.php](file:///home/rozi/projects/simpul/app/Http/Controllers/KartuSiswaController.php) pada method `rombel()`:
    ```php
    @ini_set('memory_limit', '512M');
    ```
  - [KartuPegawaiController.php](file:///home/rozi/projects/simpul/app/Http/Controllers/KartuPegawaiController.php) pada method `cetakMassal()`:
    ```php
    @ini_set('memory_limit', '512M');
    ```

---

## 3. Opsi Mitigasi Arsitektural Tambahan

Untuk menekan penggunaan memori dan mencegah lonjakan resource server saat sekolah mencetak dalam volume yang lebih masif:

### Opsi A — Image Resizing / Downscaling saat Upload (Direkomendasikan di Hulu)
- **Mekanisme:** Foto siswa yang diunggah (maksimum 2 MB dari kamera/ponsel pengguna) di-downscale otomatis ke resolusi optimal kartu fisik ID-1 (misal 300 &times; 400 px, JPEG quality 85%, ukuran file ~80–120 KB) sebelum disimpan ke MinIO/S3.
- **Dampak Performa:** Mengurangi total payload Base64 36 siswa dari ~67 MB menjadi < 5 MB, dan memangkas peak memory PHP dari ~272 MB kembali ke ~45–55 MB.

### Opsi B — Paginasi / Pembatasan Jumlah Kartu per Request PDF (Batching di UI)
- **Mekanisme:** Membatasi cetak langsung (sinkron) maksimal 1 rombel (36–40 siswa) atau kelipatan lembar A4 tertentu per unduhan PDF. Jika operator memilih "Cetak Seluruh Sekolah", UI memecahnya menjadi file per rombel atau per tingkat.
- **Dampak Performa:** Mencegah satu request HTTP mengonsumsi gigabytes RAM jika ada sekolah dengan 500+ siswa.

### Opsi C — Asynchronous Export via Queued Job (Persiapan Minggu 13)
- **Mekanisme:** Untuk pencetakan satu sekolah atau multi-rombel sekaligus, proses dialihkan ke background worker queue (`ShouldQueue` / Laravel Horizon). PDF disimpan sementara ke storage dan notifikasi siap-unduh dikirim ke user.
- **Dampak Performa:** Memisahkan beban komputasi berat dari web worker HTTP, mencegah timeout gateway web (Nginx/Cloudflare 60s timeout).
