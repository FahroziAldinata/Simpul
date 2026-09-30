# ADR-006: Pipeline Impor Excel Dua Tahap

**Tanggal:** 2026-09-30
**Status:** Diterima
**Minggu:** 7 — Pipeline Impor Excel

---

## Konteks

Simpul membutuhkan kemampuan impor massal data siswa dari file Excel yang disiapkan oleh operator sekolah. File tersebut sering kali berasal dari berbagai sumber (Dapodik, format sekolah sendiri, ekspor BOS) dengan:

- Header yang tidak standar (merged cells, baris judul di atas header)
- NISN dalam notasi ilmiah akibat Excel auto-formatting (`8.12345678E+09`)
- Tanggal lahir dalam 4 format berbeda (`YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY`, `17 Mei 2008`)
- Siswa duplikat di dalam file maupun terhadap database
- Sekitar 5% baris bermasalah dari total 800 baris

Tanpa pipeline terstruktur, operator akan mengimpor data buta tanpa memahami kegagalan per baris, lalu kesalahan tertanam di database.

---

## Keputusan

Mengimplementasikan pipeline impor **dua tahap terpisah** menggunakan queue job:

### Tahap 1 — Validasi (terpisah dari eksekusi)

- File diunggah dan disimpan ke storage (bukan diproses langsung).
- Job `ValidateImportBatchJob` dijalankan di background worker:
  - Membaca file dengan `readDataOnly = true` (hemat memori)
  - Mendeteksi baris header secara adaptif (tahan baris judul merged)
  - Menormalisasi data tiap baris: anti-notasi ilmiah NISN, padding leading zero, multi-format tanggal, title-case nama, bersihkan NIK
  - Memvalidasi setiap baris dengan aturan eksplisit per kolom
  - Mendeteksi duplikat di dalam file dan terhadap database
  - Menandai tiap baris `valid | peringatan | gagal` dengan alasan spesifik per kolom
  - Menyimpan hasil ke tabel `import_rows`
- Progress di-broadcast tiap 100 baris via Reverb ke private channel `sekolah.{sekolahId}.impor.{batchId}`
- Operator melihat pratinjau di tab **Valid | Peringatan | Gagal** sebelum memutuskan untuk mengimpor

### Tahap 2 — Eksekusi

- Job `ExecuteImportBatchJob` berjalan setelah operator konfirmasi.
- Chunked insert 200 baris per `DB::transaction()` — per-chunk isolation: kegagalan satu chunk tidak menggagalkan seluruh batch.
- Progress di-broadcast via Reverb.
- Setelah selesai: batch diberi window rollback 24 jam (`dapat_dirollback_hingga`).

---

## Alasan Pemilihan

### Mengapa dua tahap?

**Pratinjau sebelum komit** adalah perbedaan utama dari mayoritas implementasi impor Excel yang langsung memasukkan data dan melaporkan error setelah fakta. Dengan dua tahap:

1. Operator melihat 40 baris gagal dari 800 *sebelum* data masuk ke database.
2. Operator bisa memperbaiki inline atau mengunduh baris gagal sebagai Excel.
3. Operator bisa memilih aksi per baris duplikat (Lewati / Perbarui / Buat Baru).

### Mengapa chunk terpisah, bukan satu transaksi raksasa?

Satu transaksi untuk 800 baris akan memegang row-level locks dalam PostgreSQL selama berdetik-detik, berisiko deadlock dan memengaruhi query lain. Chunk 200 baris per transaksi meminimalkan durasi lock sambil tetap menjamin konsistensi per chunk.

### Mengapa `sekolah_id` eksplisit di constructor job?

Queue worker tidak memiliki sesi HTTP atau user aktif. Mengandalkan `session('sekolah_id')` atau `auth()->user()->sekolah_id` di dalam job berisiko menghasilkan data yang salah scope (atau error). Keputusan ini dikunci di ADR ini: semua job impor **WAJIB** menerima `sekolah_id` eksplisit via constructor, dibuktikan dengan test yang memverifikasi job berjalan tanpa sesi aktif.

### Mengapa rollback per batch (bukan per baris)?

Operator membutuhkan tombol "Batalkan seluruh impor ini" — bukan undo per baris. Rollback per baris terlalu granular dan membingungkan. Rollback per batch lebih mudah dipahami: "Batalkan impor yang saya lakukan tadi."

---

## Konsekuensi

### Positif
- Operator mendapat umpan balik transparan: alasan kegagalan per kolom, bukan pesan generik.
- Tidak ada data kotor yang masuk ke database tanpa sepengetahuan operator.
- Rollback 24 jam memberikan jaring pengaman.
- Sistem aman dari keputusan impor yang terburu-buru.

### Negatif / Trade-off
- Dua tahap = lebih banyak klik (upload → mapping → preview → execute). Ini disengaja: kami menganggap keamanan data lebih penting dari kecepatan proses.
- File fisik disimpan di storage hingga batch selesai/rollback. Perlu cleanup job berkala untuk batch abandoned.
- Reverb diperlukan untuk UX real-time. Tanpa Reverb, pengguna harus me-refresh halaman untuk melihat progress.

---

## Celah Desain Terkait

- **Mutasi Pindah Sekolah Lintas Tenant** — Saat ini belum ada alur resmi untuk siswa pindah dari Sekolah A ke Sekolah B, karena constraint `UNIQUE INDEX siswa_nisn_unique WHERE deleted_at IS NULL` mencegah pendaftaran ulang siswa yang sudah ada di sekolah lain. Dokumentasi lengkap di [`docs/celah-desain-mutasi-lintas-tenant.md`](../../docs/celah-desain-mutasi-lintas-tenant.md).

---

## Alternatif yang Dipertimbangkan

| Alternatif | Alasan Ditolak |
|---|---|
| Impor langsung (satu tahap, validasi + insert serentak) | Tidak ada pratinjau, error hanya diketahui setelah data masuk database |
| maatwebsite/excel `ToModel` | Tidak fleksibel untuk pipeline dua tahap, sulit broadcast progress per baris |
| Satu transaksi raksasa | Row-level lock terlalu panjang, berisiko deadlock untuk 800+ baris |
| Rollback menggunakan `import_batch_id` hard delete | Melanggar PRD Rule D3 (siswa tidak pernah hard delete) |
