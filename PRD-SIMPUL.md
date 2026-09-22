# PRD — SIMPUL

**Sistem Manajemen Pendidikan Terpadu**
_Satu simpul untuk seluruh administrasi sekolah._

|                    |                                                             |
| ------------------ | ----------------------------------------------------------- |
| **Versi**          | 1.0                                                         |
| **Status**         | Draft untuk eksekusi                                        |
| **Jenis proyek**   | Portofolio engineering (solo developer)                     |
| **Durasi**         | 14 minggu × ±15 jam = ±210 jam                              |
| **Stack**          | Laravel 12 · Inertia 2 · Vue 3 · TypeScript · PostgreSQL 16 |
| **Target jenjang** | SD, SMP, SMA/SMK (multi-tenant)                             |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Konteks Masalah](#2-konteks-masalah)
3. [Tujuan & Metrik Keberhasilan](#3-tujuan--metrik-keberhasilan)
4. [Persona & Peran](#4-persona--peran)
5. [Ruang Lingkup](#5-ruang-lingkup)
6. [Tiga Masalah Teknis Inti](#6-tiga-masalah-teknis-inti)
7. [Spesifikasi Fungsional](#7-spesifikasi-fungsional)
8. [Model Data](#8-model-data)
9. [Arsitektur Teknis](#9-arsitektur-teknis)
10. [Design System](#10-design-system)
11. [Persyaratan Non-Fungsional](#11-persyaratan-non-fungsional)
12. [Roadmap 14 Minggu](#12-roadmap-14-minggu)
13. [Task Breakdown Lengkap](#13-task-breakdown-lengkap)
14. [Definition of Done](#14-definition-of-done)
15. [Risiko & Mitigasi](#15-risiko--mitigasi)
16. [Kemasan Portofolio](#16-kemasan-portofolio)
17. [Backlog Pasca-Portofolio](#17-backlog-pasca-portofolio)

---

## 1. Ringkasan Eksekutif

### 1.1 Satu Paragraf

SIMPUL adalah platform administrasi sekolah multi-tenant berbasis web yang menyatukan data induk, kepegawaian, kesiswaan, dan penjadwalan ke dalam satu sumber kebenaran. Versi portofolio ini mengerjakan empat modul inti secara mendalam — Data Induk, Data Siswa & Guru, Absensi Guru, dan Jadwal Pelajaran — dengan tiga masalah rekayasa nyata sebagai jantung proyek: penjadwalan berkendala (constraint satisfaction), sinkronisasi absensi offline-first, dan pipeline impor data massal yang tahan input berantakan.

### 1.2 Prinsip Produk

| Prinsip                           | Konsekuensi konkret                                                                 |
| --------------------------------- | ----------------------------------------------------------------------------------- |
| **Kedalaman > keluasan**          | 4 modul selesai penuh, 14 modul lain sengaja dibuang dan didokumentasikan alasannya |
| **Guru dulu, admin kemudian**     | Alur harian guru harus selesai dalam ≤ 3 ketukan di HP                              |
| **Data tidak pernah hilang**      | Soft delete + audit log di semua entitas transaksional                              |
| **Excel adalah kenyataan**        | Impor dan ekspor Excel adalah fitur kelas satu, bukan pelengkap                     |
| **Offline adalah kondisi normal** | Absensi harus berfungsi tanpa jaringan, bukan sebagai fallback darurat              |
| **Aturan adalah data**            | Jam kerja, toleransi, alur persetujuan, bobot — semua konfigurasi, bukan kode       |

### 1.3 Prinsip Eksekusi

> Jika jadwal meleset, yang dipotong adalah **Minggu 13 (polish)** — bukan Minggu 7, 10, atau 12. Tiga minggu itu adalah isi portofolionya.

---

## 2. Konteks Masalah

### 2.1 Kondisi Lapangan

Administrasi sekolah tersebar di media yang tidak saling bicara:

| Kebutuhan        | Cara sekarang                                 | Biaya tersembunyi                           |
| ---------------- | --------------------------------------------- | ------------------------------------------- |
| Data siswa       | Excel per wali kelas + Dapodik                | Data ganda, versi berbeda, NISN salah ketik |
| Absensi guru     | Buku tanda tangan / fingerprint tanpa laporan | Rekap manual 3–5 jam per bulan              |
| Jadwal pelajaran | Excel manual + kertas tempel                  | Bentrok baru ketahuan di minggu pertama     |
| Izin & cuti      | Grup WhatsApp + surat kertas                  | Tidak ada jejak, tidak ada rekap            |
| Laporan ke dinas | Ketik ulang dari beberapa sumber              | Entri ganda, rawan salah                    |

### 2.2 Akar Masalah

1. **Tidak ada entitas bersama.** "Siswa" di file wali kelas, di file TU, dan di Dapodik adalah tiga objek berbeda yang kebetulan bernama sama.
2. **Periode tidak dimodelkan.** Excel tidak punya konsep tahun ajaran, sehingga data lama ditimpa atau file diduplikasi tiap tahun.
3. **Validasi terjadi di kepala manusia.** Bentrok jadwal, NISN ganda, dan absen dobel baru ketahuan setelah jadi masalah.

### 2.3 Riset yang Wajib Dilakukan (Minggu 1)

Ini bukan formalitas. Ini pembeda utama proyek ini dari ratusan repo "Sistem Informasi Sekolah" di GitHub.

- Wawancara **minimal 2 operator sekolah** dari jenjang berbeda (idealnya 1 SD + 1 SMK).
- Kumpulkan artefak asli: file Excel data siswa, format rekap absensi bulanan, format jadwal pelajaran, format surat izin.
- Ukur: berapa jam per bulan dihabiskan untuk rekap absensi? Berapa lama entri satu siswa baru?
- Anonimkan dan simpan di `docs/research/` sebagai bukti.

**Output Minggu 1:** dokumen `docs/research/findings.md` berisi kutipan langsung, daftar field wajib versi nyata, dan format laporan yang harus bisa direproduksi sistem.

---

## 3. Tujuan & Metrik Keberhasilan

### 3.1 Tujuan Produk

| #   | Tujuan                           | Metrik                           | Target     | Cara ukur                |
| --- | -------------------------------- | -------------------------------- | ---------- | ------------------------ |
| G1  | Hilangkan rekap absensi manual   | Waktu hasilkan rekap bulanan     | < 60 detik | Stopwatch di demo        |
| G2  | Hilangkan bentrok jadwal         | Jumlah bentrok lolos ke produksi | 0          | Constraint DB            |
| G3  | Percepat entri data massal       | Waktu impor 800 siswa            | < 3 menit  | Job duration log         |
| G4  | Absensi tetap jalan tanpa sinyal | Absen tersimpan saat offline     | 100%       | Uji manual airplane mode |
| G5  | Data lengkap & konsisten         | Field wajib terisi               | > 95%      | Query kelengkapan        |

### 3.2 Tujuan Portofolio

| #   | Tujuan                                                | Bukti                                             |
| --- | ----------------------------------------------------- | ------------------------------------------------- |
| P1  | Menunjukkan penilaian prioritas, bukan sekadar coding | Bagian "Scope & Trade-offs" di README             |
| P2  | Menunjukkan pemecahan masalah non-CRUD                | 3 masalah inti terdokumentasi dengan ADR          |
| P3  | Menunjukkan disiplin rekayasa                         | CI hijau, Larastan level 6, Pest pada alur kritis |
| P4  | Menunjukkan sistem yang hidup                         | Demo online, seeder realistis, video 3 menit      |
| P5  | Menunjukkan proyek lahir dari masalah nyata           | Artefak riset lapangan di repo                    |

### 3.3 Anti-Metrik (jangan dikejar)

- Jumlah modul. Jumlah tabel. Jumlah baris kode. Persentase coverage 100%.

---

## 4. Persona & Peran

### 4.1 Persona

**Bu Rina — Operator Sekolah (utama).**
Usia 34, laptop 1366×768, Chrome, mengetik cepat, benci mouse. Mengerjakan entri data, rekap, dan laporan dinas. Kebutuhan: kecepatan entri, impor Excel, ekspor yang bisa langsung dikirim. Frustrasi terbesar: mengetik ulang data yang sudah ada di file lain.

**Pak Adi — Guru & Wali Kelas.**
Usia 41, HP Android mid-range, sinyal lemah di lorong sekolah. Butuh: absen masuk cepat, lihat jadwal, isi jurnal mengajar, ajukan izin. Akan berhenti memakai sistem jika absen butuh lebih dari 3 ketukan atau gagal saat sinyal jelek.

**Bu Sari — Kepala Sekolah.**
Butuh gambaran, bukan form. Membuka sistem 2× seminggu. Menyetujui izin/cuti. Butuh laporan siap cetak untuk rapat yayasan.

**Pak Budi — Waka Kurikulum.**
Menyusun jadwal tiap awal semester. Saat ini butuh 2 minggu dengan Excel dan kertas. Ini pengguna yang paling merasakan nilai modul jadwal.

### 4.2 Matriks Peran & Izin

| Modul / Aksi            | Super Admin | Operator | Kepsek | Waka Kurikulum | Guru | Wali Kelas | Orang Tua |
| ----------------------- | ----------- | -------- | ------ | -------------- | ---- | ---------- | --------- |
| Data Induk              | CRUD        | CRU      | R      | R              | –    | –          | –         |
| Tahun Ajaran (rollover) | CRUD        | –        | A      | –              | –    | –          | –         |
| Data Siswa              | CRUD        | CRUD     | R      | R              | R¹   | RU¹        | R²        |
| Data Pegawai            | CRUD        | CRUD     | R      | R              | R³   | R³         | –         |
| Impor Excel             | ✓           | ✓        | –      | –              | –    | –          | –         |
| Absensi (diri sendiri)  | ✓           | ✓        | ✓      | ✓              | ✓    | ✓          | –         |
| Rekap Absensi           | R           | R        | R      | –              | R³   | R³         | –         |
| Izin/Cuti — ajukan      | ✓           | ✓        | ✓      | ✓              | ✓    | ✓          | –         |
| Izin/Cuti — setujui     | ✓           | –        | ✓      | –              | –    | –          | –         |
| Jadwal                  | CRUD        | R        | R      | CRUD           | R³   | R³         | R²        |
| Audit Log               | R           | –        | R      | –              | –    | –          | –         |

¹ hanya siswa di rombel/mapel yang diampu · ² hanya anak sendiri · ³ hanya data sendiri
**A** = aksi khusus (approve/eksekusi)

### 4.3 Model Izin Teknis

- RBAC via `spatie/laravel-permission`, dengan **teams feature aktif** → `team_id` dipetakan ke `sekolah_id`.
- Izin granular: `{modul}.{aksi}` — contoh `siswa.create`, `izin.approve`, `jadwal.generate`.
- **Scoping** ditegakkan di Policy, bukan di query controller: `SiswaPolicy::view()` memeriksa apakah user adalah wali kelas rombel siswa tersebut.
- Semua peran bawaan di-seed, tapi bisa dikustomisasi per sekolah.

---

## 5. Ruang Lingkup

### 5.1 Masuk (In Scope)

| Epic | Modul                                                                                | Kedalaman    |
| ---- | ------------------------------------------------------------------------------------ | ------------ |
| E1   | Platform: auth, RBAC, multi-tenancy, audit log, app shell                            | Penuh        |
| E2   | Data Induk: sekolah, tahun ajaran, semester, jurusan, rombel, ruang, mapel, kalender | Penuh        |
| E3   | Data Siswa & Guru: biodata, wali, riwayat kelas, mutasi, berkas                      | Penuh        |
| E4   | Pipeline Impor Excel                                                                 | **Mendalam** |
| E5   | Absensi Guru: QR berotasi, izin/cuti berjenjang, rekap                               | Penuh        |
| E6   | PWA Offline-First untuk absensi                                                      | **Mendalam** |
| E7   | Jadwal Pelajaran: constraint, auto-generate, drag-drop                               | **Mendalam** |
| E8   | Dashboard per peran, ekspor Excel/PDF, dark mode, a11y                               | Cukup        |

### 5.2 Keluar (Out of Scope) — dan alasannya

Tuliskan ini apa adanya di README:

| Modul dibuang                         | Alasan                                                              |
| ------------------------------------- | ------------------------------------------------------------------- |
| Inventaris & Aset                     | CRUD standar; tidak menambah sinyal teknis baru                     |
| Arsip & Persuratan                    | Menarik (full-text search, disposisi), tapi tidak muat di 14 minggu |
| PPDB / SPMB                           | Butuh payment gateway dan aturan regulasi yang berubah tiap tahun   |
| Keuangan & SPP                        | Butuh integrasi pembayaran; risiko tinggi, sinyal rendah            |
| Penilaian & Rapor                     | Terikat kurikulum yang berubah; scope creep terbesar                |
| Perpustakaan, BK, UKS, Ekskul, Alumni | CRUD turunan                                                        |
| Aplikasi native Android/iOS           | PWA sudah cukup untuk alur guru                                     |
| LMS / ujian online                    | Produk yang sama sekali berbeda                                     |

> **Kalimat untuk README:** _"Modul-modul ini dirancang di tingkat model data tapi sengaja tidak diimplementasikan. Prioritas diberikan pada kedalaman empat modul inti. Keputusan ini didokumentasikan di ADR-000."_

### 5.3 Asumsi

- Satu instalasi melayani banyak sekolah (single database, kolom `sekolah_id`).
- Guru memiliki smartphone dengan browser modern (Chrome/Safari terkini).
- Sekolah memiliki internet, meskipun tidak stabil.
- Tidak ada integrasi Dapodik dua arah di v1 (hanya format impor yang kompatibel).

---

## 6. Tiga Masalah Teknis Inti

Ini adalah isi portofolio. Setiap masalah harus punya ADR, tes otomatis, dan segmen khusus di video demo.

---

### 6.1 Masalah A — Penjadwalan Berkendala (Constraint Satisfaction)

**Pernyataan masalah.**
Waka Kurikulum harus menempatkan ±400 slot pelajaran (12 rombel × ±35 jam/minggu) ke dalam grid hari × jam, tanpa melanggar kendala keras, sambil mengoptimalkan kendala lunak.

**Kendala Keras (HARD — wajib 0 pelanggaran).**

| Kode | Kendala                                                                                  |
| ---- | ---------------------------------------------------------------------------------------- |
| H1   | Satu guru tidak boleh mengajar di dua tempat pada slot waktu yang sama                   |
| H2   | Satu ruang tidak boleh dipakai dua rombel pada slot waktu yang sama                      |
| H3   | Satu rombel tidak boleh punya dua mapel pada slot waktu yang sama                        |
| H4   | Jumlah jam mapel per rombel per minggu harus tepat sesuai alokasi kurikulum              |
| H5   | Jadwal tidak boleh jatuh pada hari libur atau di luar jam operasional sekolah            |
| H6   | Mapel yang butuh ruang khusus (Lab IPA, Bengkel) hanya boleh di ruang berkategori sesuai |

**Kendala Lunak (SOFT — dioptimalkan, dilaporkan sebagai skor).**

| Kode | Kendala                                                              | Bobot |
| ---- | -------------------------------------------------------------------- | ----- |
| S1   | Minimalkan jam kosong (gap) di jadwal guru                           | 10    |
| S2   | Mapel berat (Matematika, Fisika) diutamakan di jam ke-1 s/d 4        | 8     |
| S3   | Hindari mapel yang sama 2 hari berturut-turut untuk rombel yang sama | 5     |
| S4   | Hormati preferensi hari tidak-mengajar guru (guru paruh waktu)       | 9     |
| S5   | Blok 2 jam berurutan untuk mapel dengan alokasi ≥ 4 jam/minggu       | 6     |

**Strategi implementasi — dua lapis pertahanan.**

**Lapis 1 — Database.** Kendala H1, H2, H3 ditegakkan di PostgreSQL, bukan hanya di aplikasi. Ini poin teknis paling kuat dari modul ini.

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- H1: bentrok guru
ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_guru_bentrok
EXCLUDE USING gist (
    guru_id        WITH =,
    semester_id    WITH =,
    hari           WITH =,
    int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
) WHERE (deleted_at IS NULL);

-- H2: bentrok ruang
ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_ruang_bentrok
EXCLUDE USING gist (
    ruang_id       WITH =,
    semester_id    WITH =,
    hari           WITH =,
    int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
) WHERE (deleted_at IS NULL AND ruang_id IS NOT NULL);

-- H3: bentrok rombel
ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_rombel_bentrok
EXCLUDE USING gist (
    rombel_id      WITH =,
    semester_id    WITH =,
    hari           WITH =,
    int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
) WHERE (deleted_at IS NULL);
```

> Catatan implementasi: `btree_gist` diperlukan agar operator `=` pada kolom integer bisa dipakai di dalam `EXCLUDE`. Constraint ini dibuat lewat `DB::statement()` di dalam migrasi Laravel.

**Lapis 2 — Aplikasi.** Service `ConflictDetector` mengecek bentrok sebelum menulis, agar UI bisa memberi pesan manusiawi alih-alih menangkap `QueryException`. Database tetap menjadi penjaga terakhir, dan harus ada tes yang membuktikan bahwa penulisan langsung yang melanggar akan ditolak.

**Auto-generator.**

```
Algoritma: Greedy + Backtracking Terbatas

1. Bangun daftar TugasMengajar: (rombel, mapel, guru, jumlah_jam)
2. Urutkan menurun berdasarkan derajat kendala:
   - butuh ruang khusus?  (+30)
   - guru punya preferensi hari?  (+20)
   - jumlah jam per minggu  (+jam × 2)
   - jumlah rombel yang diampu guru  (+n)
3. Untuk setiap tugas (paling terkendala duluan):
   a. Hasilkan kandidat slot yang lolos semua kendala HARD
   b. Skor tiap kandidat dengan bobot kendala SOFT
   c. Ambil kandidat skor tertinggi, tempatkan
   d. Jika tidak ada kandidat valid → backtrack:
      - lepas penempatan terakhir, coba kandidat berikutnya
      - batas 500 langkah backtrack, lalu berhenti
4. Kembalikan: penempatan + daftar tugas gagal + skor soft constraint
```

Penting: **generator tidak wajib menyelesaikan 100%.** Menyisakan 3 dari 400 slot untuk dibereskan manusia adalah hasil yang jujur dan realistis. UI harus menampilkan daftar tugas gagal beserta alasannya ("Pak Adi sudah penuh 24 jam; tidak ada slot tersisa yang cocok dengan Lab IPA").

Generator berjalan di **queue job** dengan progress broadcast via Reverb, karena bisa memakan 10–60 detik.

**Antarmuka.**

- Grid drag-and-drop: baris = jam ke-1..10, kolom = Senin..Sabtu.
- Tampilan bisa dipertukarkan: per rombel / per guru / per ruang.
- Saat kartu diangkat, seluruh sel yang menimbulkan bentrok langsung diwarnai `--danger-bg`, sel valid `--success-bg`. Validasi optimistis di klien, dikonfirmasi server.
- Panel samping: daftar tugas belum terjadwal, skor kendala lunak, dan tombol "Susun Otomatis".
- Ekspor PDF per rombel dan per guru.

**Bukti selesai:** generate jadwal 12 rombel × 35 jam tanpa pelanggaran HARD, dengan laporan skor SOFT, dalam < 60 detik.

---

### 6.2 Masalah B — Absensi Offline-First

**Pernyataan masalah.**
Pak Adi absen di lorong sekolah pada pukul 06:45 dengan sinyal satu bar. Request timeout. Jika sistem gagal di sini, dia kembali ke buku tanda tangan selamanya.

**Persyaratan.**

| Kode | Persyaratan                                                              |
| ---- | ------------------------------------------------------------------------ |
| B1   | Absensi tersimpan lokal saat offline dan tersinkron otomatis saat online |
| B2   | Sinkronisasi ulang tidak boleh menghasilkan catatan ganda (idempoten)    |
| B3   | QR tidak boleh bisa difoto lalu dikirim ke rekan yang tidak hadir        |
| B4   | Waktu yang dicatat adalah waktu server, bukan waktu perangkat            |
| B5   | Guru harus tahu dengan jelas status: terkirim / menunggu sinkron / gagal |

**Anti-spoofing QR — TOTP + HMAC.**

Layar QR ditampilkan di monitor depan kantor atau di HP petugas piket. Token berotasi tiap 30 detik.

```php
// Pembuatan token (server)
$window  = intdiv(now()->timestamp, 30);
$payload = "{$sekolahId}|{$titikAbsenId}|{$window}";
$token   = hash_hmac('sha256', $payload, config('simpul.qr_secret'));
$qr      = base64_encode("{$sekolahId}.{$titikAbsenId}.{$window}.{$token}");
```

Validasi menerima window saat ini, satu window sebelumnya, dan satu sesudahnya (toleransi ±30 detik untuk clock drift dan waktu pemindaian). Tiap kombinasi `(pegawai_id, tanggal, jenis)` hanya boleh tercatat sekali — ditegakkan dengan unique index.

Lapis tambahan: validasi koordinat GPS terhadap radius sekolah (default 150 m, bisa dikonfigurasi, bisa dinonaktifkan). Tandai sebagai `lokasi_mencurigakan` alih-alih menolak — menolak absen orang yang benar-benar hadir jauh lebih merusak kepercayaan daripada meloloskan satu kecurangan untuk ditinjau manual.

**Antrean offline.**

```
1. Guru memindai QR → payload dibentuk di klien:
   { client_uuid, token_qr, jenis, captured_at, lat, lng }
   client_uuid = UUIDv4 yang dibuat SEKALI di perangkat
2. Simpan ke IndexedDB, store "antrean_absensi", status = "pending"
3. Tampilkan UI optimistis: "Tercatat (menunggu sinkron)"
4. Service Worker mendaftarkan Background Sync
5. Saat online: POST /api/absensi/sync dengan array antrean
   Header: Idempotency-Key: <client_uuid>
6. Server:
   - cek tabel idempotency_keys → jika ada, kembalikan respons tersimpan
   - validasi token QR terhadap captured_at (BUKAN waktu terima)
   - simpan absensi, simpan idempotency key (TTL 7 hari)
7. Klien menandai item "synced" dan menghapusnya dari antrean
8. Kegagalan → exponential backoff (1s, 2s, 4s, ... maks 5 percobaan),
   lalu status "failed" dengan tombol coba lagi manual
```

**Penanganan waktu (B4).**
Perangkat menyimpan `captured_at` dari jam lokal dan `captured_at_monotonic` dari `performance.now()`. Server menghitung selisih antara waktu terima dan `captured_at`; jika selisih di luar batas wajar (> 12 jam) atau `captured_at` di masa depan, catatan ditandai `perlu_ditinjau`. Waktu final yang dipakai untuk perhitungan keterlambatan adalah `captured_at` yang sudah dikoreksi dengan offset clock yang diukur saat handshake terakhir.

**Bukti selesai (harus ada di video demo):**
Buka PWA → aktifkan mode pesawat → scan QR → muncul "menunggu sinkron" → tutup browser sepenuhnya → nyalakan jaringan → buka lagi → status berubah "tersinkron" → tekan tombol sinkron ulang 5× → database tetap berisi tepat satu baris.

---

### 6.3 Masalah C — Pipeline Impor Excel

**Pernyataan masalah.**
Bu Rina mengunggah `data_siswa_2025.xlsx` berisi 800 baris dari sistem lama. Header tidak standar, ada baris judul yang di-merge, NISN tersimpan sebagai angka ilmiah, tanggal dalam 4 format berbeda, dan 40 baris punya masalah. Impor "semua atau tidak sama sekali" akan membuat dia menyerah.

**Alur lengkap.**

```
TAHAP 1 — UNGGAH
  Terima .xlsx/.xls/.csv, maks 10 MB, maks 5.000 baris
  Simpan ke storage, buat record ImportBatch (status: uploaded)

TAHAP 2 — PEMETAAN KOLOM
  Baca 20 baris pertama
  Tebak pemetaan kolom otomatis (Levenshtein terhadap nama field yang dikenal)
    "NISN" → nisn | "Nama Lengkap" → nama | "Tgl Lahir" → tanggal_lahir
  Tampilkan UI pemetaan: kolom Excel ↔ field sistem, bisa dikoreksi manual
  Simpan pemetaan sebagai template yang bisa dipakai ulang

TAHAP 3 — VALIDASI (queue job, chunk 100 baris)
  Per baris, jalankan:
    - normalisasi   : trim, NISN ke string (bukan float), tanggal multi-format,
                      nama ke Title Case, NIK dibersihkan dari spasi
    - validasi      : NISN 10 digit, NIK 16 digit, tanggal masuk akal,
                      rombel ada di data induk, jenis kelamin valid
    - deteksi duplikat :
        a. dalam file itu sendiri (NISN sama muncul 2×)
        b. terhadap database (NISN sudah ada)
    - klasifikasi   : VALID | PERINGATAN | GAGAL
  Simpan hasil ke import_rows (satu baris = satu record)
  Broadcast progress via Reverb tiap 100 baris

TAHAP 4 — PRATINJAU
  Tabel bertab: Valid (742) | Peringatan (18) | Gagal (40)
  Baris gagal menampilkan alasan spesifik per kolom, bukan "validation error"
  Baris duplikat menawarkan aksi: Lewati | Perbarui data lama | Buat baru
  Baris bisa diperbaiki inline langsung di pratinjau, lalu divalidasi ulang
  Tombol: "Impor 742 baris valid" / "Unduh 40 baris gagal sebagai Excel"

TAHAP 5 — EKSEKUSI
  Queue job, chunked insert 200 baris per transaksi
  DB transaction per chunk (bukan satu transaksi raksasa — menghindari lock panjang)
  Jika satu chunk gagal → rollback chunk itu saja, lanjutkan, catat di laporan
  Progress real-time via Reverb

TAHAP 6 — RINGKASAN & ROLLBACK
  Laporan: X dibuat, Y diperbarui, Z dilewati, W gagal
  Tombol "Batalkan Impor Ini" aktif selama 24 jam
    → menghapus semua record dengan import_batch_id tersebut
    → hanya untuk record yang belum disentuh transaksi lain
  Unduh laporan lengkap sebagai Excel
```

**Keputusan teknis penting.**

- Gunakan `WithChunkReading` + `ShouldQueue` dari maatwebsite/excel agar file 5.000 baris tidak menghabiskan memori.
- Validasi dan eksekusi adalah **dua tahap terpisah**. Ini yang membuat pratinjau mungkin, dan ini pembeda dari 99% implementasi impor Excel.
- Setiap record hasil impor menyimpan `import_batch_id` → memungkinkan rollback dan audit.
- `Bus::batch()` untuk orkestrasi chunk, dengan callback `then`, `catch`, `finally`.

**Bukti selesai:** unggah file 800 baris cacat buatan sendiri (`tests/fixtures/siswa_berantakan.xlsx`), tampilkan pratinjau yang menjelaskan tiap kegagalan, impor yang valid, lalu batalkan seluruh batch dan tunjukkan database kembali bersih.

---

## 7. Spesifikasi Fungsional

Format: **US-xx** = User Story, dengan kriteria penerimaan (AC) yang bisa diuji.

---

### 7.1 E1 — Platform

**US-01 — Autentikasi**
_Sebagai pengguna, saya ingin masuk dengan email/NIP dan kata sandi._

- AC1: Login menerima email **atau** NIP/NUPTK sebagai identitas.
- AC2: Rate limit 5 percobaan gagal per menit per IP.
- AC3: Sesi berbasis cookie (Fortify), `remember me` 30 hari.
- AC4: Pengguna nonaktif (`is_active = false`) ditolak dengan pesan jelas.
- AC5: Ganti kata sandi wajib saat login pertama untuk akun hasil impor.

**US-02 — Multi-tenancy**
_Sebagai sistem, data satu sekolah tidak boleh bocor ke sekolah lain._

- AC1: Semua model tenant-scoped menggunakan `BelongsToSekolah` trait dengan global scope.
- AC2: `sekolah_id` diisi otomatis saat pembuatan record (model event `creating`).
- AC3: Ada tes yang membuktikan: user Sekolah A melakukan `Siswa::find($idMilikSekolahB)` → mengembalikan `null`.
- AC4: Ada tes yang membuktikan akses langsung via route `/siswa/{id}` milik sekolah lain → 404 (bukan 403, agar tidak membocorkan keberadaan record).
- AC5: Super Admin dapat berpindah sekolah lewat pemilih di topbar.

**US-03 — Peran & Izin**

- AC1: 7 peran bawaan di-seed sesuai matriks 4.2.
- AC2: Izin diperiksa di Policy, bukan di blade/vue saja.
- AC3: Menu sidebar dirender sesuai izin — item tanpa izin tidak muncul.
- AC4: Setiap controller method dilindungi `authorize()`.

**US-04 — Audit Log**

- AC1: Semua create/update/delete pada entitas inti tercatat.
- AC2: Log menyimpan: aktor, aksi, model, id, nilai lama, nilai baru, IP, user agent, waktu.
- AC3: Halaman audit bisa difilter per pengguna, per model, per rentang tanggal.
- AC4: Field sensitif (kata sandi, token) di-redact.

**US-05 — Tahun Ajaran Aktif**

- AC1: Topbar menampilkan pemilih tahun ajaran + semester.
- AC2: Pilihan disimpan di sesi dan diterapkan sebagai scope ke semua query berperiode.
- AC3: Periode non-aktif bersifat read-only; ada banner peringatan berwarna `--warning-bg`.

---

### 7.2 E2 — Data Induk

**US-06 — Profil Sekolah**

- AC1: Field: NPSN (8 digit, unik), nama, jenjang (SD/SMP/SMA/SMK), status (Negeri/Swasta), alamat, koordinat, logo, kepala sekolah, akreditasi.
- AC2: Jenjang menentukan perilaku sistem: jurusan hanya aktif untuk SMA/SMK; penamaan tingkat berbeda (SD: 1–6, SMP: 7–9, SMA/SMK: 10–12).
- AC3: Koordinat dipakai untuk validasi radius absensi.

**US-07 — Tahun Ajaran & Semester**

- AC1: Format `2025/2026`, dengan tanggal mulai dan selesai.
- AC2: Tepat satu tahun ajaran berstatus aktif per sekolah (ditegakkan partial unique index).
- AC3: Tiap tahun ajaran punya 2 semester (Ganjil, Genap).
- AC4: Aksi **Rollover**: menyalin rombel, menaikkan tingkat siswa, memindahkan siswa tingkat akhir ke status "Lulus", menyalin struktur jadwal sebagai draf.
- AC5: Rollover berjalan dalam satu transaksi dan menampilkan pratinjau sebelum dieksekusi.

**US-08 — Rombel**

- AC1: Field: nama (contoh "X RPL 1"), tingkat, jurusan (opsional), wali kelas, ruang utama, kuota.
- AC2: Satu guru hanya boleh menjadi wali kelas di satu rombel per semester.
- AC3: Menampilkan jumlah siswa aktif vs kuota, dengan indikator warna saat melebihi.

**US-09 — Mata Pelajaran & Alokasi Jam**

- AC1: Mapel punya kode, nama, kelompok (A/B/C untuk SMK), dan flag `butuh_ruang_khusus` + kategori ruang.
- AC2: Alokasi jam per mapel per tingkat per jurusan dapat diatur (ini input untuk generator jadwal).
- AC3: Validasi: total jam semua mapel per rombel tidak boleh melebihi total slot tersedia per minggu.

**US-10 — Ruang**

- AC1: Field: nama, kode, kategori (Kelas/Lab/Bengkel/Aula/Olahraga), kapasitas.
- AC2: Kategori dipakai kendala H6 pada penjadwalan.

**US-11 — Kalender Akademik**

- AC1: Hari libur nasional, libur sekolah, ujian, dan kegiatan.
- AC2: Hari libur mengecualikan absensi (tidak dihitung alfa) dan memblokir slot jadwal.
- AC3: Jam operasional per hari dapat diatur (Senin–Kamis 10 jam, Jumat 6 jam, Sabtu libur).

---

### 7.3 E3 — Data Siswa & Guru

**US-12 — Biodata Siswa**

- AC1: Form stepper 5 tahap: Identitas → Orang Tua/Wali → Alamat → Berkas → Akademik.
- AC2: Autosave draft tiap 10 detik ke `localStorage`; draf dipulihkan saat halaman dibuka lagi.
- AC3: NISN unik secara global (lintas sekolah) dengan pesan jelas jika sudah terpakai.
- AC4: NIK divalidasi 16 digit; tanggal lahir diverifikasi silang dengan digit NIK ke-7..12 dan memunculkan peringatan (bukan error) jika tidak cocok.
- AC5: Unggah berkas (akta, KK, foto, ijazah) maks 2 MB per file, format jpg/png/pdf.
- AC6: Navigasi keyboard penuh: `Tab` antar field, `Ctrl+Enter` simpan, `Ctrl+Shift+Enter` simpan dan buat baru.

**US-13 — Riwayat Kelas & Mutasi**

- AC1: Siswa punya riwayat penempatan rombel per semester, tidak ditimpa.
- AC2: Jenis mutasi: masuk, keluar, pindah rombel, naik kelas, tinggal kelas, lulus, drop out.
- AC3: Mutasi keluar mewajibkan tanggal, alasan, dan sekolah tujuan.
- AC4: Siswa tidak pernah dihapus, hanya berubah status. Soft delete hanya untuk koreksi entri salah.

**US-14 — Data Pegawai**

- AC1: Field: NUPTK, NIP, nama, jenis (Guru/TU/Kepsek), status kepegawaian (PNS/PPPK/GTY/GTT), mapel yang diampu, jam mengajar maksimal per minggu.
- AC2: `jam_maks_per_minggu` menjadi kendala pada generator jadwal.
- AC3: Preferensi hari tidak mengajar (untuk guru paruh waktu) → kendala lunak S4.
- AC4: Akun pengguna dibuat otomatis saat pegawai dibuat, dengan kata sandi awal acak yang ditampilkan sekali.

**US-15 — Kartu Digital**

- AC1: Generate kartu pelajar/pegawai PDF dengan QR berisi ID terenkripsi.
- AC2: Cetak massal per rombel dalam satu PDF, 8 kartu per halaman A4.

**US-16 — Tabel Data Performa Tinggi**

- AC1: Tabel 500+ baris tampil dalam < 1 detik (server-side pagination, 25/50/100 per halaman).
- AC2: Pencarian global debounce 300 ms, mencari di nama, NISN, dan NIK.
- AC3: Filter chip: rombel, tingkat, jenis kelamin, status, kelengkapan data.
- AC4: Header lengket vertikal; kolom nama lengket horizontal.
- AC5: Pengatur kolom (tampil/sembunyi) disimpan per pengguna.
- AC6: Ekspor hasil filter saat ini ke Excel.
- AC7: Aksi massal: ubah rombel, ubah status, ekspor terpilih.

---

### 7.4 E4 — Impor Excel

**US-17 s/d US-19** — detail lengkap ada di [bagian 6.3](#63-masalah-c--pipeline-impor-excel).

Kriteria penerimaan ringkas:

- AC1: Unduh template Excel dengan header yang benar, validasi dropdown, dan sheet petunjuk.
- AC2: Pemetaan kolom otomatis dengan akurasi ≥ 80% pada header umum.
- AC3: Pratinjau menampilkan alasan kegagalan per kolom, bukan pesan generik.
- AC4: Impor 800 baris selesai < 3 menit dengan progress real-time.
- AC5: Rollback batch mengembalikan database ke kondisi sebelum impor.
- AC6: File > 5.000 baris ditolak dengan pesan yang menyarankan pemecahan file.

---

### 7.5 E5 — Absensi Guru

**US-20 — Konfigurasi Jam Kerja**

- AC1: Jadwal kerja per hari: jam masuk, jam pulang, toleransi keterlambatan (menit).
- AC2: Jadwal berbeda dapat diterapkan per kelompok pegawai (misal guru paruh waktu).
- AC3: Status dihitung otomatis: Hadir, Terlambat, Pulang Cepat, Alfa.

**US-21 — Absen Masuk & Pulang**

- AC1: Alur guru: buka PWA → tap "Absen" → kamera terbuka → scan → selesai. Maksimal 3 ketukan.
- AC2: Berfungsi offline (lihat [6.2](#62-masalah-b--absensi-offline-first)).
- AC3: Satu pegawai hanya bisa absen masuk sekali per hari (unique index).
- AC4: Umpan balik jelas: berhasil (hijau), menunggu sinkron (kuning), gagal (merah) — dengan ikon, tidak hanya warna.
- AC5: Fallback manual oleh operator jika QR/kamera bermasalah, wajib menyertakan alasan dan tercatat di audit log.

**US-22 — Izin, Sakit, Cuti, Dinas Luar**

- AC1: Jenis pengajuan dapat dikonfigurasi, masing-masing dengan: butuh lampiran?, butuh persetujuan?, mengurangi kuota cuti?
- AC2: Alur persetujuan berjenjang dapat diatur (contoh: Guru → Waka → Kepsek).
- AC3: Pengaju mendapat notifikasi saat status berubah.
- AC4: Pengajuan yang disetujui otomatis mengisi status absensi pada tanggal terkait.
- AC5: Kuota cuti tahunan dilacak dan ditampilkan sisa kuotanya.
- AC6: Tidak boleh mengajukan izin untuk tanggal yang sudah tercatat hadir.

**US-23 — Rekap & Laporan**

- AC1: Rekap bulanan berbentuk matriks: baris = pegawai, kolom = tanggal 1–31, sel = kode status berwarna.
- AC2: Kolom ringkasan: total hadir, terlambat, izin, sakit, alfa, total menit keterlambatan.
- AC3: Ekspor Excel dengan format yang **meniru format asli sekolah** (dari hasil riset Minggu 1).
- AC4: Ekspor PDF siap tanda tangan, dengan kop sekolah dan blok tanda tangan kepala sekolah.
- AC5: Rekap per pegawai dengan detail harian dan jam masuk/pulang.
- AC6: Dihasilkan dalam < 60 detik untuk 45 pegawai × 30 hari.

---

### 7.6 E7 — Jadwal Pelajaran

**US-24 s/d US-27** — detail lengkap ada di [bagian 6.1](#61-masalah-a--penjadwalan-berkendala-constraint-satisfaction).

Kriteria penerimaan ringkas:

- AC1: Pembuatan jadwal manual dengan deteksi bentrok real-time di UI.
- AC2: Percobaan menyimpan jadwal bentrok ditolak dengan pesan spesifik menyebut penyebabnya.
- AC3: Auto-generate menghasilkan jadwal 12 rombel tanpa pelanggaran HARD.
- AC4: Tugas yang gagal dijadwalkan dilaporkan beserta alasan yang bisa dipahami manusia.
- AC5: Tampilan per rombel, per guru, per ruang.
- AC6: Ekspor PDF per rombel dan per guru.
- AC7: Jadwal pengganti: saat guru berhalangan, tandai dan tugaskan guru pengganti tanpa mengubah jadwal induk.
- AC8: Ada tes yang membuktikan constraint database menolak insert bentrok yang mem-bypass aplikasi.

---

### 7.7 E8 — Dashboard, Ekspor, Polish

**US-28 — Dashboard per Peran**

| Peran          | Isi dashboard                                                                                   |
| -------------- | ----------------------------------------------------------------------------------------------- |
| Guru           | Jadwal hari ini, tombol absen, status absensi bulan ini, sisa kuota cuti                        |
| Operator       | Antrean kerja: data tidak lengkap, izin menunggu, batch impor berjalan, bentrok jadwal          |
| Kepsek         | Grafik kehadiran 30 hari, jumlah siswa per tingkat, izin menunggu persetujuan, rasio guru–siswa |
| Waka Kurikulum | Kelengkapan jadwal per rombel, beban jam per guru, ruang paling padat                           |

- AC1: Dashboard dimuat < 2 detik, query di-cache 5 menit.
- AC2: Tidak ada form entri di dashboard kepala sekolah.

**US-29 — Ekspor**

- AC1: Semua tabel utama punya ekspor Excel yang menghormati filter aktif.
- AC2: Ekspor > 1.000 baris diproses via queue dan dikirim sebagai tautan unduhan.
- AC3: PDF dihasilkan via Gotenberg dengan template kop sekolah.

**US-30 — Dark Mode & Aksesibilitas**

- AC1: Dark mode via atribut `data-theme`, preferensi disimpan per pengguna, menghormati `prefers-color-scheme` pada kunjungan pertama.
- AC2: Kontras minimal WCAG AA pada semua kombinasi teks/latar.
- AC3: Semua status kehadiran ditampilkan dengan warna **dan** huruf/ikon.
- AC4: Navigasi keyboard penuh, focus ring terlihat jelas, skip-to-content link.
- AC5: Target sentuh minimal 44×44 px di tampilan mobile.

---

## 8. Model Data

### 8.1 Diagram Relasi Inti

```
sekolah ─┬─ tahun_ajaran ── semester ─┬─ rombel ─┬─ anggota_rombel ── siswa ─┬─ wali_siswa
         │                            │          │                          ├─ berkas_siswa
         │                            │          │                          └─ mutasi_siswa
         │                            │          └─ jadwal_pelajaran ─┬─ mata_pelajaran
         │                            │                               ├─ pegawai
         │                            │                               └─ ruang
         │                            └─ alokasi_jam_mapel
         ├─ jurusan
         ├─ ruang
         ├─ mata_pelajaran
         ├─ hari_libur
         ├─ jam_kerja
         ├─ titik_absen
         ├─ pegawai ─┬─ absensi ─ idempotency_keys
         │           ├─ pengajuan_izin ── persetujuan_izin
         │           └─ users ── roles ── permissions
         └─ import_batches ── import_rows
                            └─ activity_log
```

### 8.2 Tabel Utama (kolom kunci)

**`sekolah`**
`id`, `npsn` (unique), `nama`, `jenjang` (enum: sd|smp|sma|smk), `status`, `alamat`, `latitude`, `longitude`, `radius_absen_meter`, `logo_path`, `akreditasi`, `timestamps`, `deleted_at`

**`tahun_ajaran`**
`id`, `sekolah_id`, `nama` ("2025/2026"), `tanggal_mulai`, `tanggal_selesai`, `is_aktif`
→ _Partial unique index:_ `UNIQUE (sekolah_id) WHERE is_aktif = true`

**`semester`**
`id`, `tahun_ajaran_id`, `tipe` (ganjil|genap), `tanggal_mulai`, `tanggal_selesai`, `is_aktif`

**`rombel`**
`id`, `sekolah_id`, `semester_id`, `nama`, `tingkat`, `jurusan_id` (nullable), `wali_kelas_id`, `ruang_id`, `kuota`
→ _Unique:_ `(semester_id, nama)` · _Unique:_ `(semester_id, wali_kelas_id)`

**`siswa`**
`id`, `sekolah_id`, `nisn` (unique global), `nik`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `alamat`, `no_hp`, `status` (aktif|lulus|pindah|do), `import_batch_id` (nullable), `timestamps`, `deleted_at`

**`anggota_rombel`**
`id`, `rombel_id`, `siswa_id`, `semester_id`, `nomor_absen`
→ _Unique:_ `(semester_id, siswa_id)` — satu siswa satu rombel per semester

**`pegawai`**
`id`, `sekolah_id`, `user_id`, `nuptk`, `nip`, `nama`, `jenis` (guru|tu|kepsek), `status_kepegawaian`, `jam_maks_per_minggu`, `hari_tidak_mengajar` (jsonb), `timestamps`, `deleted_at`

**`mata_pelajaran`**
`id`, `sekolah_id`, `kode`, `nama`, `kelompok`, `butuh_ruang_kategori` (nullable), `bobot_berat` (int, untuk kendala S2)

**`alokasi_jam_mapel`**
`id`, `semester_id`, `mata_pelajaran_id`, `tingkat`, `jurusan_id` (nullable), `jam_per_minggu`

**`ruang`**
`id`, `sekolah_id`, `kode`, `nama`, `kategori` (kelas|lab|bengkel|aula|olahraga), `kapasitas`

**`jadwal_pelajaran`** ← tabel dengan EXCLUDE constraint
`id`, `sekolah_id`, `semester_id`, `rombel_id`, `mata_pelajaran_id`, `guru_id`, `ruang_id` (nullable), `hari` (1–6), `jam_mulai_ke`, `jam_selesai_ke`, `timestamps`, `deleted_at`

**`jam_kerja`**
`id`, `sekolah_id`, `kelompok`, `hari`, `jam_masuk`, `jam_pulang`, `toleransi_menit`, `is_libur`

**`absensi`**
`id`, `sekolah_id`, `pegawai_id`, `tanggal`, `jenis` (masuk|pulang), `waktu_server`, `waktu_perangkat`, `status` (hadir|terlambat|pulang_cepat|alfa), `menit_terlambat`, `latitude`, `longitude`, `lokasi_valid` (bool), `perlu_ditinjau` (bool), `sumber` (qr|manual|import), `client_uuid`, `dicatat_oleh` (nullable), `alasan_manual`
→ _Unique:_ `(pegawai_id, tanggal, jenis) WHERE deleted_at IS NULL`
→ _Unique:_ `client_uuid`

**`idempotency_keys`**
`key` (PK), `sekolah_id`, `endpoint`, `response_body` (jsonb), `status_code`, `expires_at`

**`titik_absen`**
`id`, `sekolah_id`, `nama`, `secret` (encrypted), `latitude`, `longitude`, `is_aktif`

**`pengajuan_izin`**
`id`, `sekolah_id`, `pegawai_id`, `jenis_izin_id`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `lampiran_path`, `status` (draft|menunggu|disetujui|ditolak|dibatalkan), `timestamps`

**`persetujuan_izin`**
`id`, `pengajuan_izin_id`, `urutan`, `approver_id`, `status`, `catatan`, `diputuskan_pada`

**`import_batches`**
`id`, `sekolah_id`, `user_id`, `tipe` (siswa|pegawai), `nama_file`, `path`, `pemetaan_kolom` (jsonb), `total_baris`, `valid`, `peringatan`, `gagal`, `dibuat`, `diperbarui`, `dilewati`, `status` (uploaded|mapping|validating|preview|importing|done|failed|rolled_back), `dapat_dirollback_hingga`

**`import_rows`**
`id`, `import_batch_id`, `nomor_baris`, `data_mentah` (jsonb), `data_bersih` (jsonb), `status` (valid|peringatan|gagal|diimpor|dilewati), `errors` (jsonb), `aksi_duplikat`, `model_id` (nullable)

### 8.3 Aturan Lintas Tabel (wajib ditegakkan)

| #   | Aturan                                       | Cara penegakan                                                 |
| --- | -------------------------------------------- | -------------------------------------------------------------- |
| D1  | Semua tabel transaksional punya `sekolah_id` | Trait `BelongsToSekolah` + global scope + tes                  |
| D2  | Semua data berperiode punya `semester_id`    | Migrasi + foreign key                                          |
| D3  | Siswa & pegawai tidak pernah hard delete     | `SoftDeletes` + status arsip                                   |
| D4  | Semua perubahan tercatat                     | `LogsActivity` dari spatie                                     |
| D5  | Uang & jam disimpan sebagai integer          | `menit_terlambat` int, bukan float                             |
| D6  | Semua timestamp disimpan UTC                 | `app.timezone = UTC`, konversi di presentasi ke `Asia/Jakarta` |
| D7  | Tidak ada enum di level DB                   | Gunakan string + PHP enum backed class, agar mudah ditambah    |

---

## 9. Arsitektur Teknis

### 9.1 Stack Final

| Lapisan         | Pilihan                                  | Versi   |
| --------------- | ---------------------------------------- | ------- |
| Runtime         | PHP                                      | 8.3     |
| Framework       | Laravel                                  | 12      |
| Frontend bridge | Inertia.js                               | 2       |
| UI              | Vue 3 (Composition API) + TypeScript     | —       |
| Styling         | Tailwind CSS                             | 4       |
| Komponen        | shadcn-vue (Reka UI)                     | —       |
| Database        | PostgreSQL                               | 16      |
| Cache & Queue   | Redis + Laravel Horizon                  | —       |
| Realtime        | Laravel Reverb                           | —       |
| Auth            | Laravel Fortify (session-based)          | —       |
| Izin            | spatie/laravel-permission (teams mode)   | —       |
| Audit           | spatie/laravel-activitylog               | —       |
| Storage         | MinIO (lokal) / Cloudflare R2 (produksi) | —       |
| Excel           | maatwebsite/excel                        | —       |
| PDF             | Gotenberg (container)                    | —       |
| QR              | endroid/qr-code + html5-qrcode (klien)   | —       |
| Offline         | Workbox + Dexie.js (IndexedDB)           | —       |
| Test            | Pest                                     | 3       |
| Static analysis | Larastan                                 | level 6 |
| Formatter       | Laravel Pint + ESLint + Prettier         | —       |
| CI/CD           | GitHub Actions                           | —       |
| Deploy          | Docker Compose di VPS                    | —       |
| Monitoring      | Sentry                                   | —       |

### 9.2 Keputusan Arsitektur (ADR yang harus ditulis)

| ADR     | Judul                                                     | Inti argumen                                                                                                                    |
| ------- | --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| ADR-000 | Ruang lingkup portofolio: kedalaman di atas keluasan      | 4 modul dalam > 18 modul dangkal                                                                                                |
| ADR-001 | Multi-tenancy: single database dengan kolom diskriminator | Biaya operasional rendah, migrasi tunggal; risiko kebocoran dimitigasi global scope + tes                                       |
| ADR-002 | PostgreSQL, bukan MySQL                                   | Butuh `EXCLUDE USING gist`, `jsonb` + GIN, `tsvector`, partial unique index                                                     |
| ADR-003 | Inertia, bukan REST API terpisah                          | Solo developer; menghilangkan biaya sinkronisasi kontrak API. Trade-off: butuh lapisan API tambahan jika kelak ada klien native |
| ADR-004 | Session auth, bukan JWT                                   | Aplikasi web first-party satu domain; JWT menambah kompleksitas revokasi tanpa manfaat                                          |
| ADR-005 | Kendala jadwal ditegakkan di database                     | Aplikasi bisa punya bug; database adalah penjaga terakhir kebenaran data                                                        |
| ADR-006 | Impor dua tahap: validasi terpisah dari eksekusi          | Memungkinkan pratinjau; menghindari impor parsial yang tidak bisa dijelaskan                                                    |
| ADR-007 | Offline-first dengan idempotency key sisi klien           | Klien menentukan identitas operasi, server menjamin eksekusi sekali                                                             |

### 9.3 Struktur Direktori

```
app/
├── Actions/                  # Aksi bisnis satu tujuan
│   ├── Absensi/CatatAbsensi.php
│   ├── Jadwal/GenerateJadwal.php
│   └── TahunAjaran/RolloverTahunAjaran.php
├── Domain/
│   ├── Absensi/
│   │   ├── QrTokenService.php
│   │   ├── IdempotencyStore.php
│   │   └── StatusKehadiranCalculator.php
│   ├── Jadwal/
│   │   ├── ConflictDetector.php
│   │   ├── SchedulingEngine.php
│   │   ├── ConstraintSet.php
│   │   ├── Constraints/Hard/*.php
│   │   └── Constraints/Soft/*.php
│   └── Import/
│       ├── ColumnMapper.php
│       ├── RowNormalizer.php
│       ├── RowValidator.php
│       ├── DuplicateDetector.php
│       └── BatchRollback.php
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/SetSekolahAktif.php
├── Models/
│   └── Concerns/BelongsToSekolah.php
├── Policies/
├── Jobs/
└── Exports/ & Imports/

resources/js/
├── Layouts/AppLayout.vue
├── Pages/{Siswa,Pegawai,Absensi,Jadwal,Import,Dashboard}/
├── Components/
│   ├── DataTable/          # tabel generik: sticky, filter, kolom, ekspor
│   ├── Form/               # Stepper, FormField, FileUpload
│   ├── Jadwal/             # ScheduleGrid, DraggableCard, ConflictOverlay
│   └── ui/                 # shadcn-vue
├── composables/
│   ├── useOfflineQueue.ts
│   ├── useDataTable.ts
│   └── useAutosave.ts
└── offline/
    ├── db.ts               # Dexie schema
    └── sync.ts

docs/
├── adr/
├── research/
└── PRD.md
```

### 9.4 Deployment

```yaml
# Layanan di docker-compose
app: PHP-FPM 8.3 + Laravel
web: Nginx
db: PostgreSQL 16
redis: Redis 7
horizon: Worker queue
reverb: WebSocket server
gotenberg: Renderer PDF
minio: Storage S3-compatible (lokal)
```

**Pipeline CI (GitHub Actions):**
`push` → Pint check → ESLint → Larastan level 6 → Pest (dengan PostgreSQL service) → build assets → _(pada branch main)_ deploy via SSH + `php artisan migrate --force` + `horizon:terminate`.

---

## 10. Design System

### 10.1 Token Warna

```css
:root {
    /* Brand — teal institusional */
    --brand-50: #eef6f6;
    --brand-100: #d4e9e9;
    --brand-200: #a9d5d5;
    --brand-300: #7fc3c3;
    --brand-400: #3e9b9b;
    --brand-500: #1e7a7a;
    --brand-600: #176363;
    --brand-700: #114c4c;
    --brand-800: #0d3b3b;
    --brand-900: #0a2e2e;

    /* Netral */
    --ink-900: #101828;
    --ink-700: #344054;
    --ink-500: #667085;
    --ink-300: #d0d5dd;
    --ink-200: #eaecf0;
    --ink-100: #f2f4f7;
    --ink-50: #f9fafb;
    --surface: #ffffff;

    /* Semantik */
    --success: #067647;
    --success-bg: #ecfdf3;
    --warning: #b54708;
    --warning-bg: #fffaeb;
    --danger: #b42318;
    --danger-bg: #fef3f2;
    --info: #175cd3;
    --info-bg: #eff8ff;

    /* Status kehadiran */
    --st-hadir: #067647;
    --st-izin: #175cd3;
    --st-sakit: #b54708;
    --st-alfa: #b42318;
    --st-terlambat: #d97706;
    --st-cuti: #6d42c9;
    --st-libur: #98a2b3;

    /* Chart */
    --chart-1: #1e7a7a;
    --chart-2: #d97706;
    --chart-3: #6d42c9;
    --chart-4: #c0355f;
    --chart-5: #175cd3;

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
}

[data-theme='dark'] {
    --ink-900: #f2f4f7;
    --ink-700: #d0d5dd;
    --ink-500: #98a2b3;
    --ink-300: #344054;
    --ink-200: #1f2a33;
    --ink-100: #151c23;
    --ink-50: #0c1116;
    --surface: #151c23;
    --brand-500: #4fb3b3;
    --brand-600: #7fc3c3;
}
```

### 10.2 Tipografi

| Peran                    | Nilai                                                   |
| ------------------------ | ------------------------------------------------------- |
| Keluarga                 | Plus Jakarta Sans (variable), fallback system sans      |
| Body tabel               | 14px / 20px                                             |
| Body form & halaman baca | 16px / 24px                                             |
| Judul halaman            | 24px / 32px, weight 600                                 |
| Judul seksi              | 18px / 28px, weight 600                                 |
| Label & caption          | 13px / 18px, weight 500, `--ink-500`                    |
| Angka                    | `font-variant-numeric: tabular-nums` **wajib** di tabel |

### 10.3 Spacing & Elevasi

- Skala spacing 4px: `4, 8, 12, 16, 24, 32, 48, 64`.
- Border 1px `--ink-300`. Radius default 10px.
- Shadow hanya untuk elemen melayang: dropdown, modal, toast, popover. Kartu statis tidak pakai shadow.

### 10.4 Aturan Komponen Mengikat

| #   | Aturan                                                                                   |
| --- | ---------------------------------------------------------------------------------------- |
| C1  | Status kehadiran selalu warna **+** huruf (H/I/S/A/T)                                    |
| C2  | Semua tabel: header lengket, kolom pertama lengket, zebra halus, aksi di menu titik tiga |
| C3  | Form: label di atas field; error di bawah field; tidak ada validasi hanya lewat warna    |
| C4  | Form panjang → stepper + autosave draft                                                  |
| C5  | Empty state selalu menawarkan aksi (impor / tambah manual)                               |
| C6  | Skeleton loader, bukan spinner layar penuh                                               |
| C7  | Indikator offline permanen di topbar saat koneksi hilang                                 |
| C8  | Aksi destruktif butuh konfirmasi dengan mengetik nama objek                              |
| C9  | Istilah UI memakai kosakata sekolah: Rombel, Wali Kelas, Tahun Ajaran, Jurnal Mengajar   |
| C10 | Setiap pesan error menjelaskan cara memperbaiki, bukan kode teknis                       |

### 10.5 Struktur Layout

- **Sidebar kiri** permanen, bisa collapse ke ikon (target laptop 1366×768). Kelompok: Beranda · Kesiswaan · Kepegawaian · Akademik · Impor · Laporan · Pengaturan.
- **Topbar tipis:** pemilih tahun ajaran & semester (kritikal), pencarian global `Ctrl+K`, indikator offline, notifikasi, avatar.
- **Area konten:** breadcrumb → judul halaman + aksi utama kanan → filter bar → konten.
- **Mobile:** bottom navigation 4 item (Beranda, Absen, Jadwal, Profil). Entri massal diarahkan ke desktop dengan pesan yang jelas.

---

## 11. Persyaratan Non-Fungsional

| Kode   | Kategori          | Persyaratan                                                                            |
| ------ | ----------------- | -------------------------------------------------------------------------------------- |
| NFR-01 | Performa          | Tabel 500 baris < 1 detik; dashboard < 2 detik; TTFB < 300 ms                          |
| NFR-02 | Performa          | Rekap absensi 45 pegawai × 30 hari < 60 detik                                          |
| NFR-03 | Performa          | Generate jadwal 12 rombel < 60 detik                                                   |
| NFR-04 | Performa          | Tidak ada N+1 query — ditegakkan `Model::preventLazyLoading()` di non-produksi         |
| NFR-05 | Ketersediaan      | Absensi berfungsi penuh offline                                                        |
| NFR-06 | Keamanan          | Semua input divalidasi di Form Request; tidak ada mass assignment tanpa `$fillable`    |
| NFR-07 | Keamanan          | Berkas identitas disimpan private, diakses lewat signed URL kadaluarsa 5 menit         |
| NFR-08 | Keamanan          | Rate limit: login 5/menit, sync absensi 30/menit, impor 3/jam                          |
| NFR-09 | Privasi           | Mengacu UU PDP: hak akses, koreksi, dan ekspor data; data minor butuh persetujuan wali |
| NFR-10 | Isolasi           | Tidak ada kebocoran lintas sekolah — dibuktikan dengan tes eksplisit                   |
| NFR-11 | Aksesibilitas     | WCAG AA; navigasi keyboard penuh; zoom 200% tanpa layout pecah                         |
| NFR-12 | Kompatibilitas    | Chrome/Edge/Firefox 2 versi terakhir, Safari iOS 16+, Android Chrome                   |
| NFR-13 | Backup            | Dump harian terenkripsi; uji restore terdokumentasi                                    |
| NFR-14 | Observability     | Sentry untuk error; log terstruktur JSON; Horizon untuk queue                          |
| NFR-15 | Portabilitas data | Ekspor penuh data satu sekolah sebagai ZIP (Excel + berkas)                            |

---

## 12. Roadmap 14 Minggu

| Mg  | Fokus                              | Epic | Deliverable yang bisa ditunjukkan                         |
| --- | ---------------------------------- | ---- | --------------------------------------------------------- |
| 1   | Riset lapangan                     | E0   | `docs/research/findings.md` + artefak Excel asli (anonim) |
| 2   | Fondasi teknis                     | E0   | Docker jalan, CI hijau, ERD, ADR-000..003                 |
| 3   | Auth, RBAC, audit, shell           | E1   | Login 5 peran → sidebar berbeda per peran                 |
| 4   | Data induk                         | E2   | Seeder demo 1 SMK + 1 SD lengkap                          |
| 5   | Siswa: model, tabel, form          | E3   | Tabel 500 siswa < 1 detik                                 |
| 6   | Siswa: berkas, mutasi, pegawai     | E3   | Riwayat kelas + kartu digital PDF                         |
| 7   | **Pipeline impor Excel**           | E4   | Impor 800 baris dengan 40 gagal tertangani                |
| 8   | Absensi: model, QR, konfigurasi    | E5   | Scan QR berotasi → tercatat                               |
| 9   | Izin/cuti + rekap                  | E5   | Rekap bulanan tercetak PDF                                |
| 10  | **PWA offline-first**              | E6   | Airplane mode → absen → sinkron                           |
| 11  | **Jadwal: constraint + UI manual** | E7   | Insert bentrok ditolak database                           |
| 12  | **Jadwal: auto-generate**          | E7   | 12 rombel tergenerate tanpa bentrok                       |
| 13  | Dashboard, ekspor, dark mode, a11y | E8   | Lighthouse > 90                                           |
| 14  | Kemasan portofolio                 | E9   | Demo online + README + video                              |

**Milestone:**

| Milestone            | Minggu | Kondisi                                            |
| -------------------- | ------ | -------------------------------------------------- |
| M1 — Fondasi siap    | 4      | Multi-tenancy teruji, data induk lengkap, CI hijau |
| M2 — Data masuk      | 7      | 800 siswa bisa diimpor dari Excel berantakan       |
| M3 — Absensi hidup   | 10     | Guru bisa absen offline dan tersinkron             |
| M4 — Jadwal cerdas   | 12     | Generator menghasilkan jadwal bebas bentrok        |
| M5 — Siap dipamerkan | 14     | Demo online, README, video, CI hijau               |

---

## 13. Task Breakdown Lengkap

Format: `[ ] T-mm.nn — deskripsi (estimasi jam)`

---

### Minggu 1 — Riset Lapangan (15 j)

```
[ ] T-01.01  Susun panduan wawancara: 12 pertanyaan terbuka                      (2)
[ ] T-01.02  Wawancara operator sekolah #1 (SD), rekam & transkrip               (3)
[ ] T-01.03  Wawancara operator sekolah #2 (SMK), rekam & transkrip              (3)
[ ] T-01.04  Kumpulkan artefak: Excel siswa, rekap absensi, jadwal, surat izin   (1)
[ ] T-01.05  Anonimkan artefak, simpan di docs/research/artifacts/               (1)
[ ] T-01.06  Tulis docs/research/findings.md: kutipan, angka, pain point         (3)
[ ] T-01.07  Ekstrak daftar field wajib versi nyata → docs/research/fields.md    (1)
[ ] T-01.08  Buat file uji "siswa_berantakan.xlsx" meniru kekacauan nyata        (1)
```

**Exit:** ada minimal 3 kutipan langsung dan 2 angka terukur (jam per bulan) yang bisa dipakai di README.

---

### Minggu 2 — Fondasi Teknis (15 j)

```
[ ] T-02.01  Inisialisasi Laravel 12 + Inertia + Vue 3 + TS + Tailwind 4         (2)
[ ] T-02.02  docker-compose: app, web, db, redis, horizon, reverb, gotenberg,
             minio — semua layanan hidup                                         (3)
[ ] T-02.03  Konfigurasi Pest 3, Larastan level 6, Pint, ESLint, Prettier        (2)
[ ] T-02.04  GitHub Actions: lint → static analysis → test (service PostgreSQL)  (2)
[ ] T-02.05  Rancang skema DB lengkap, gambar ERD (dbdiagram.io)                 (3)
[ ] T-02.06  Tulis ADR-000 (scope), ADR-001 (tenancy), ADR-002 (PostgreSQL),
             ADR-003 (Inertia)                                                   (2)
[ ] T-02.07  Aktifkan Model::preventLazyLoading() & strict mode di non-produksi  (1)
```

**Exit:** `git push` → CI hijau. `docker compose up` → aplikasi terbuka di browser.

---

### Minggu 3 — Platform: Auth, RBAC, Audit, Shell (15 j)

```
[ ] T-03.01  Migrasi: sekolah, users, pegawai (minimal)                          (1)
[ ] T-03.02  Fortify: login via email ATAU NIP, rate limit, remember me          (2)
[ ] T-03.03  Trait BelongsToSekolah + global scope + auto-fill sekolah_id        (2)
[ ] T-03.04  Middleware SetSekolahAktif (sesi) + pemilih sekolah super admin     (1)
[ ] T-03.05  spatie/laravel-permission teams mode; seeder 7 peran + izin         (2)
[ ] T-03.06  TES ISOLASI TENANT: user A akses data B → null / 404                (1)
[ ] T-03.07  spatie/activitylog: konfigurasi + redact field sensitif             (1)
[ ] T-03.08  Halaman Audit Log: tabel, filter user/model/tanggal, diff nilai     (2)
[ ] T-03.09  AppLayout.vue: sidebar collapsible, topbar, breadcrumb              (2)
[ ] T-03.10  Terapkan token warna + Plus Jakarta Sans ke tailwind config         (1)
```

**Exit:** login sebagai 5 peran berbeda → sidebar menampilkan menu berbeda. Tes isolasi tenant lulus.

---

### Minggu 4 — Data Induk (15 j)

```
[ ] T-04.01  CRUD Profil Sekolah + upload logo + koordinat                       (2)
[ ] T-04.02  CRUD Tahun Ajaran + Semester, partial unique index is_aktif         (2)
[ ] T-04.03  Pemilih tahun ajaran/semester di topbar + scope sesi                (2)
[ ] T-04.04  Banner read-only untuk periode non-aktif                            (1)
[ ] T-04.05  CRUD Jurusan (aktif hanya untuk SMA/SMK)                            (1)
[ ] T-04.06  CRUD Ruang dengan kategori + kapasitas                              (1)
[ ] T-04.07  CRUD Mata Pelajaran + flag butuh_ruang_kategori + bobot_berat       (1)
[ ] T-04.08  CRUD Rombel + validasi wali kelas unik per semester                 (2)
[ ] T-04.09  Alokasi Jam Mapel per tingkat/jurusan + validasi total jam          (2)
[ ] T-04.10  Kalender Akademik: hari libur + jam operasional per hari            (1)
```

**Exit:** ganti tahun ajaran di topbar → seluruh data ikut berganti konteks.

---

### Minggu 5 — Siswa: Model, Tabel, Form (15 j)

```
[ ] T-05.01  Migrasi: siswa, wali_siswa, anggota_rombel, berkas_siswa            (2)
[ ] T-05.02  Komponen DataTable generik: server pagination, sticky header,
             sticky kolom pertama, sort, zebra                                   (4)
[ ] T-05.03  Pencarian global debounce 300ms (nama, NISN, NIK)                   (1)
[ ] T-05.04  Filter chip: rombel, tingkat, JK, status, kelengkapan               (2)
[ ] T-05.05  Pengatur kolom, disimpan per user                                   (1)
[ ] T-05.06  Form stepper 5 tahap + validasi per tahap                           (3)
[ ] T-05.07  Composable useAutosave → draft ke localStorage tiap 10 detik        (1)
[ ] T-05.08  Shortcut keyboard: Ctrl+Enter simpan, Ctrl+Shift+Enter simpan+baru  (1)
```

**Exit:** seed 500 siswa → tabel termuat < 1 detik, terukur dan dicatat.

---

### Minggu 6 — Siswa: Berkas, Mutasi & Pegawai (15 j)

```
[ ] T-06.01  Upload berkas ke MinIO + signed URL 5 menit + validasi tipe/ukuran  (2)
[ ] T-06.02  Riwayat kelas per semester (tidak ditimpa)                          (2)
[ ] T-06.03  Modul Mutasi: 7 jenis + validasi + pencatatan alasan                (2)
[ ] T-06.04  CRUD Pegawai + auto-create akun user + kata sandi awal sekali tampil(3)
[ ] T-06.05  Field jam_maks_per_minggu & hari_tidak_mengajar (jsonb)             (1)
[ ] T-06.06  Kartu digital PDF via Gotenberg, 8 kartu/A4, QR terenkripsi         (3)
[ ] T-06.07  Aksi massal: ubah rombel, ubah status, ekspor terpilih              (2)
```

**Exit:** cetak kartu satu rombel jadi satu PDF; mutasi siswa tercatat dengan riwayat utuh.

---

### Minggu 7 — Pipeline Impor Excel ⭐ (18 j — minggu berat)

```
[ ] T-07.01  Migrasi import_batches + import_rows                                (1)
[ ] T-07.02  Generator template Excel: header benar, dropdown, sheet petunjuk    (2)
[ ] T-07.03  Upload + parse 20 baris pertama untuk pratinjau header              (1)
[ ] T-07.04  ColumnMapper: tebak pemetaan otomatis (Levenshtein + sinonim)       (2)
[ ] T-07.05  UI pemetaan kolom (drag/select) + simpan sebagai template           (2)
[ ] T-07.06  RowNormalizer: NISN anti-notasi-ilmiah, tanggal multi-format,
             nama Title Case, NIK bersih                                         (2)
[ ] T-07.07  RowValidator + DuplicateDetector (dalam-file & terhadap DB)         (2)
[ ] T-07.08  Job validasi chunked 100 baris + broadcast progress via Reverb      (2)
[ ] T-07.09  UI pratinjau bertab: Valid | Peringatan | Gagal, alasan per kolom   (2)
[ ] T-07.10  Perbaikan inline di pratinjau + validasi ulang baris                (1)
[ ] T-07.11  Resolusi duplikat: Lewati | Perbarui | Buat baru                    (1)
[ ] T-07.12  Job eksekusi: chunk 200/transaksi, kegagalan per-chunk tidak
             menggagalkan seluruh impor                                          (2)
[ ] T-07.13  Rollback batch (24 jam) + guard record yang sudah tersentuh         (2)
[ ] T-07.14  Laporan hasil + unduh baris gagal sebagai Excel                     (1)
[ ] T-07.15  Tes Pest: impor siswa_berantakan.xlsx → 742 valid, 40 gagal,
             rollback mengembalikan DB bersih                                    (2)
[ ] T-07.16  Tulis ADR-006 (impor dua tahap)                                     (1)
```

**Exit:** demo penuh dari unggah sampai rollback, dengan progress real-time.

---

### Minggu 8 — Absensi: Model, QR, Konfigurasi (15 j)

```
[ ] T-08.01  Migrasi: jam_kerja, titik_absen, absensi, idempotency_keys          (2)
[ ] T-08.02  CRUD Jam Kerja per kelompok pegawai + toleransi                     (2)
[ ] T-08.03  QrTokenService: HMAC + window 30 detik, validasi ±1 window          (3)
[ ] T-08.04  Halaman tampilan QR (layar kantor) dengan auto-refresh 30 detik     (2)
[ ] T-08.05  Halaman scan (html5-qrcode) + permintaan izin kamera                (2)
[ ] T-08.06  StatusKehadiranCalculator: hadir/terlambat/pulang cepat/alfa        (2)
[ ] T-08.07  Validasi radius GPS → tandai lokasi_mencurigakan (tidak menolak)    (1)
[ ] T-08.08  Absen manual oleh operator + alasan wajib + audit log               (1)
```

**Exit:** scan QR di layar → tercatat dengan status dan menit keterlambatan benar. QR hasil screenshot 2 menit lalu → ditolak.

---

### Minggu 9 — Izin, Cuti & Rekap (15 j)

```
[ ] T-09.01  CRUD Jenis Izin (konfigurasi: lampiran?, persetujuan?, kuota?)      (2)
[ ] T-09.02  Form pengajuan izin + upload lampiran + validasi tanggal            (2)
[ ] T-09.03  Alur persetujuan berjenjang (tabel persetujuan_izin, urutan)        (3)
[ ] T-09.04  Inbox persetujuan untuk approver + aksi setuju/tolak + catatan      (2)
[ ] T-09.05  Izin disetujui → otomatis mengisi status absensi tanggal terkait    (1)
[ ] T-09.06  Pelacakan kuota cuti tahunan + tampilan sisa kuota                  (1)
[ ] T-09.07  Rekap matriks bulanan: pegawai × tanggal, sel berwarna + huruf      (3)
[ ] T-09.08  Ekspor Excel meniru format asli sekolah (hasil riset Mg 1)          (1)
```

**Exit:** rekap bulanan 45 pegawai dihasilkan < 60 detik, ekspor Excel cocok dengan format nyata sekolah.

---

### Minggu 10 — PWA Offline-First ⭐ (17 j)

```
[ ] T-10.01  Setup PWA: manifest, ikon, Workbox service worker                   (2)
[ ] T-10.02  Dexie.js: skema IndexedDB untuk antrean_absensi                     (1)
[ ] T-10.03  Composable useOfflineQueue: enqueue, flush, retry, status           (3)
[ ] T-10.04  UI optimistis: "Tercatat (menunggu sinkron)" + badge antrean        (2)
[ ] T-10.05  Endpoint POST /api/absensi/sync menerima array + Idempotency-Key    (2)
[ ] T-10.06  IdempotencyStore: simpan respons, TTL 7 hari, kembalikan saat ulang (2)
[ ] T-10.07  Background Sync API + fallback polling saat online kembali          (2)
[ ] T-10.08  Exponential backoff 5 percobaan + tombol coba lagi manual           (1)
[ ] T-10.09  Deteksi clock drift → tandai perlu_ditinjau jika tidak wajar        (1)
[ ] T-10.10  Indikator offline permanen di topbar                                (1)
[ ] T-10.11  Tes: sinkron 5× dengan payload identik → tepat 1 baris di DB        (1)
[ ] T-10.12  Tulis ADR-007 (idempotency sisi klien)                              (1)
```

**Exit:** skenario airplane mode lengkap berhasil, termasuk menutup browser di tengah jalan.

---

### Minggu 11 — Jadwal: Constraint & UI Manual ⭐ (17 j)

```
[ ] T-11.01  Migrasi jadwal_pelajaran + CREATE EXTENSION btree_gist              (1)
[ ] T-11.02  3 EXCLUDE constraint (guru, ruang, rombel) via DB::statement        (2)
[ ] T-11.03  TES: insert bentrok langsung via DB → QueryException                (1)
[ ] T-11.04  ConflictDetector service (lapis aplikasi) + pesan manusiawi         (3)
[ ] T-11.05  Kendala H4 (total jam sesuai alokasi), H5 (libur), H6 (ruang)       (2)
[ ] T-11.06  Komponen ScheduleGrid: hari × jam, responsif                        (3)
[ ] T-11.07  Drag & drop kartu jadwal + validasi optimistis di klien             (3)
[ ] T-11.08  ConflictOverlay: sel merah/hijau saat kartu diangkat                (2)
[ ] T-11.09  Tampilan dipertukarkan: per rombel / per guru / per ruang           (2)
[ ] T-11.10  Tulis ADR-005 (constraint di database)                              (1)
```

**Exit:** coba taruh 2 mapel di slot guru yang sama → UI langsung merah, simpan paksa → ditolak database.

---

### Minggu 12 — Jadwal: Auto-Generate ⭐ (17 j)

```
[ ] T-12.01  ConstraintSet: antarmuka HardConstraint & SoftConstraint            (2)
[ ] T-12.02  Implementasi 6 kendala keras sebagai class terpisah                 (3)
[ ] T-12.03  Implementasi 5 kendala lunak + sistem bobot skor                    (3)
[ ] T-12.04  SchedulingEngine: greedy + backtracking, batas 500 langkah          (4)
[ ] T-12.05  Pengurutan tugas berdasarkan derajat kendala                        (1)
[ ] T-12.06  Job generate + broadcast progress via Reverb                        (2)
[ ] T-12.07  UI hasil: penempatan, tugas gagal + alasan, skor soft constraint    (2)
[ ] T-12.08  Ekspor PDF jadwal per rombel dan per guru                           (2)
[ ] T-12.09  Jadwal pengganti (guru berhalangan) tanpa ubah jadwal induk         (2)
[ ] T-12.10  Tes: generate 12 rombel → 0 pelanggaran HARD, < 60 detik            (2)
```

**Exit:** tekan "Susun Otomatis" → 12 rombel terjadwal, laporan menyebut tugas gagal dengan alasan yang bisa dipahami.

---

### Minggu 13 — Dashboard, Ekspor, Polish (15 j)

```
[ ] T-13.01  Dashboard Guru: jadwal hari ini, tombol absen, status bulan ini     (2)
[ ] T-13.02  Dashboard Operator: antrean kerja (data kurang, izin menunggu)      (2)
[ ] T-13.03  Dashboard Kepsek: grafik kehadiran 30 hari, statistik siswa         (2)
[ ] T-13.04  Dashboard Waka: kelengkapan jadwal, beban guru, kepadatan ruang     (2)
[ ] T-13.05  Cache dashboard 5 menit + invalidasi saat data berubah              (1)
[ ] T-13.06  Ekspor > 1000 baris via queue + notifikasi tautan unduhan           (1)
[ ] T-13.07  Dark mode: data-theme, preferensi user, prefers-color-scheme        (2)
[ ] T-13.08  Audit aksesibilitas: kontras, focus ring, skip link, target 44px    (2)
[ ] T-13.09  Audit performa: Lighthouse, hapus N+1, tambah indeks yang kurang    (1)
```

**Exit:** Lighthouse Performance & Accessibility > 90 pada halaman utama.

---

### Minggu 14 — Kemasan Portofolio (15 j)

```
[ ] T-14.01  Seeder demo realistis: 1 SMK (600 siswa, 45 guru, 12 rombel,
             jadwal penuh, absensi 3 bulan) + 1 SD (180 siswa, 12 guru)          (4)
[ ] T-14.02  Login demo satu klik untuk 5 peran                                  (1)
[ ] T-14.03  Scheduled job reset data demo tiap malam 02:00                      (1)
[ ] T-14.04  Deploy ke VPS + domain + SSL + Sentry                               (2)
[ ] T-14.05  README: masalah nyata (dengan kutipan riset), scope & trade-offs,
             3 masalah teknis + solusinya, diagram arsitektur, apa selanjutnya   (3)
[ ] T-14.06  Rapikan docs/adr/ (8 ADR) dan docs/research/                        (1)
[ ] T-14.07  Rekam video demo 3 menit: 60 dtk jadwal, 60 dtk offline,
             60 dtk impor — tanpa tur menu                                       (2)
[ ] T-14.08  Screenshot untuk README (light + dark), badge CI                    (1)
```

**Exit:** kirim satu tautan ke orang lain → mereka bisa mencoba sistem dalam 10 detik tanpa registrasi.

---

### Ringkasan Beban

| Minggu        | Jam     | Catatan                           |
| ------------- | ------- | --------------------------------- |
| 1–6           | 90      | Fondasi & CRUD, ritme normal      |
| 7, 10, 11, 12 | 69      | **Minggu inti** — jangan dipotong |
| 8, 9, 13, 14  | 60      | Pendukung                         |
| **Total**     | **219** | ±15,6 jam/minggu                  |

---

## 14. Definition of Done

Sebuah task dianggap selesai bila **seluruh** poin berikut terpenuhi:

```
[ ] Fungsionalitas sesuai seluruh kriteria penerimaan (AC)
[ ] Form Request untuk validasi (bukan validasi di controller)
[ ] Policy untuk otorisasi + dipanggil dengan authorize()
[ ] Tenant scope aktif, dan diuji bila menyentuh data lintas sekolah
[ ] Tidak ada N+1 query (dicek dengan preventLazyLoading)
[ ] Pest test untuk happy path + minimal 1 edge case
[ ] Pint & ESLint bersih, Larastan level 6 lolos
[ ] Empty state, loading state, dan error state tersedia
[ ] Dark mode tidak rusak
[ ] Dapat dioperasikan dengan keyboard
[ ] Teks UI dalam Bahasa Indonesia sesuai kosakata sekolah
[ ] Commit mengikuti conventional commits
[ ] CI hijau
```

**Definition of Done untuk Milestone:** seluruh task selesai + demo end-to-end berhasil direkam + ADR terkait ditulis.

---

## 15. Risiko & Mitigasi

| #   | Risiko                                                 | Dampak | Prob.  | Mitigasi                                                                                                                                                              |
| --- | ------------------------------------------------------ | ------ | ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| R1  | Scope creep — tergoda menambah modul                   | Tinggi | Tinggi | Daftar out-of-scope di 5.2 bersifat mengikat. Ide baru masuk `docs/backlog.md`, bukan sprint                                                                          |
| R2  | Minggu 12 (auto-generate) meleset                      | Tinggi | Sedang | Sudah ada fallback: jadwal manual + deteksi bentrok (Mg 11) tetap menjadi fitur utuh. Generator adalah bonus yang bisa disederhanakan jadi greedy tanpa backtracking  |
| R3  | Tidak dapat akses ke sekolah nyata untuk riset         | Sedang | Sedang | Alternatif: wawancara guru lewat jaringan pribadi, atau gunakan format Dapodik & Permendikbud publik sebagai basis. Tetap dokumentasikan keterbatasannya dengan jujur |
| R4  | `EXCLUDE USING gist` ternyata rumit dipasang           | Sedang | Rendah | Kerjakan T-11.01 s/d T-11.03 lebih awal sebagai spike di Minggu 2 kalau ragu                                                                                          |
| R5  | Offline sync memakan waktu lebih lama                  | Sedang | Sedang | Potong lingkup ke absen masuk saja (tanpa pulang) untuk v1 offline                                                                                                    |
| R6  | Kelelahan di minggu 10–12 (tiga minggu berat berturut) | Tinggi | Sedang | Sisipkan Minggu 13 sebagai penyangga; jadwalkan istirahat di akhir Mg 9                                                                                               |
| R7  | Seeder demo tidak realistis → aplikasi terlihat kosong | Sedang | Sedang | Alokasikan 4 jam penuh untuk T-14.01; gunakan Faker dengan nama Indonesia dan distribusi kehadiran yang masuk akal                                                    |
| R8  | Biaya VPS untuk demo                                   | Rendah | Tinggi | VPS 2 GB cukup (~Rp 80 rb/bln); alternatif gratis: Fly.io atau Railway tier hobi                                                                                      |

---

## 16. Kemasan Portofolio

### 16.1 Struktur README

```
# SIMPUL — Sistem Manajemen Pendidikan Terpadu

[badge CI] [badge PHP] [badge Laravel] [badge lisensi]
[screenshot hero — tampilan jadwal drag-and-drop, light mode]

## Masalah
> "Setiap akhir bulan saya butuh empat jam hanya untuk merekap absensi guru."
> — Operator SMK, wawancara Februari 2026
[2 paragraf: kondisi lapangan + angka dari riset]

## Demo
→ simpul.contoh.id · login satu klik sebagai 5 peran · data reset tiap malam
[video 3 menit]

## Ruang Lingkup & Trade-offs
Apa yang dikerjakan dalam, apa yang sengaja dibuang, dan mengapa. (ADR-000)

## Tiga Masalah Teknis
### 1. Penjadwalan berkendala
[masalah → pendekatan → cuplikan kode EXCLUDE constraint → hasil]
### 2. Absensi offline-first
[masalah → diagram alur sync → idempotency → hasil]
### 3. Pipeline impor Excel
[masalah → alur 6 tahap → screenshot pratinjau → hasil]

## Arsitektur
[diagram] [tabel stack] [tautan ke 8 ADR]

## Menjalankan Secara Lokal
docker compose up -d && php artisan migrate --seed

## Apa Selanjutnya
[3 hal konkret yang akan dikerjakan berikutnya, menunjukkan visi produk]
```

### 16.2 Checklist Kelayakan Pamer

```
[ ] Demo online hidup dan cepat dibuka
[ ] Login satu klik — tanpa registrasi, tanpa mengingat kredensial
[ ] Data demo padat dan realistis (nama Indonesia, distribusi masuk akal)
[ ] README menjelaskan KEPUTUSAN, bukan cara instalasi
[ ] 8 ADR tertulis dan singkat (maks 1 halaman masing-masing)
[ ] Artefak riset lapangan ada di repo
[ ] Video 3 menit fokus ke 3 masalah sulit, bukan tur menu
[ ] CI hijau dan badge-nya terlihat
[ ] Riwayat commit rapi dengan conventional commits
[ ] Tes ada pada alur kritis (impor, sync, constraint)
[ ] Screenshot light & dark mode
[ ] Dark mode berfungsi (reviewer akan mengeceknya)
```

### 16.3 Naskah Wawancara

Siapkan tiga cerita berdurasi 2 menit, masing-masing dengan struktur **Situasi → Kendala teknis → Keputusan → Trade-off → Hasil terukur**:

1. _"Bagaimana kamu memastikan jadwal tidak pernah bentrok?"_ → dua lapis pertahanan, mengapa constraint di database dan bukan hanya di aplikasi.
2. _"Bagaimana kamu menangani jaringan yang tidak bisa diandalkan?"_ → idempotency key dari klien, mengapa klien yang menentukan identitas operasi.
3. _"Kenapa hanya empat modul?"_ → ini pertanyaan tentang penilaian prioritas, dan jawaban yang bagus lebih bernilai daripada modul kelima.

---

## 17. Backlog Pasca-Portofolio

Jika proyek ini berlanjut menjadi produk nyata, urutan berikutnya:

| Prioritas | Modul                            | Alasan urutan                                                       |
| --------- | -------------------------------- | ------------------------------------------------------------------- |
| 1         | Absensi Siswa & Jurnal Mengajar  | Membuat guru membuka sistem setiap hari — kunci adopsi              |
| 2         | Notifikasi WhatsApp ke orang tua | Nilai yang paling langsung terasa oleh keluarga                     |
| 3         | PPDB / SPMB                      | Siklus tahunan bernilai tinggi; konversi otomatis pendaftar → siswa |
| 4         | Keuangan & SPP                   | Sumber pendapatan sekolah; pendorong kesediaan membayar             |
| 5         | Inventaris dengan QR             | Permintaan umum setelah demo                                        |
| 6         | Arsip & Persuratan               | Full-text search dengan `tsvector`                                  |
| 7         | Penilaian & Rapor                | Kompleks, terikat kurikulum                                         |
| 8         | Integrasi Dapodik dua arah       | Pembeda kompetitif terbesar                                         |
| 9         | Dashboard yayasan multi-sekolah  | Menaikkan nilai kontrak                                             |
| 10        | Paket on-premise                 | Untuk sekolah dengan internet buruk                                 |

---

_Dokumen ini adalah kontrak dengan diri sendiri. Bagian 5.2 (out of scope) dan bagian 12 (urutan minggu) adalah bagian yang paling sering ingin dilanggar, dan justru itulah yang paling penting dipertahankan._
