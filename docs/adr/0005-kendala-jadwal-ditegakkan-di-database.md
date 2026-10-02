# ADR-0005: Kendala Jadwal Ditegakkan di Database (Database-Level Exclusion Constraints)

## Status

Diterima (Accepted) — Diimplementasikan pada Minggu 11 (T-11.01 s/d T-11.10)

## Konteks

Modul Penjadwalan Pelajaran SIMPUL menangani penempatan ±400 slot pelajaran per sekolah per semester (12 rombel × ±35 jam/minggu). Kendala Keras (HARD constraints) meliputi:
- **H1**: Satu guru tidak boleh mengajar di dua tempat pada slot waktu yang sama.
- **H2**: Satu ruang tidak boleh dipakai dua rombel pada slot waktu yang sama.
- **H3**: Satu rombel tidak boleh punya dua pelajaran pada slot waktu yang sama.

Jika validasi bentrok waktu hanya diterapkan di lapisan aplikasi (application-level validation), sistem rentan terhadap:
1. *Race condition* ketika dua pengguna (misal Waka Kurikulum dan Operator) atau proses background menyimpan slot pada detik yang bersamaan.
2. Celah *bypass* jika data dimanipulasi melalui script seeder, bulk import, atau query batch.
3. Potensi *software regression* atau bug logika di masa mendatang yang tidak sengaja meloloskan jadwal bentrok ke database produksi.

PRD SIMPUL Bagian 6.1 dan 9.2 menetapkan prinsip: **"Aplikasi bisa punya bug; database adalah penjaga terakhir kebenaran data."**

## Keputusan

Menerapkan strategi pertahanan dua lapis (*Defense-in-Depth*):

### 1. Lapis 1 — Basis Data (Penjaga Terakhir / Absolute Truth)
Menerapkan ekstensi `btree_gist` dan PostgreSQL 16 `EXCLUDE USING gist` pada tabel `jadwal_pelajaran` dengan tipe rentang *half-open* `int4range(jam_mulai_ke, jam_selesai_ke, '[)')` dan operator overlap `&&`:

- **Bentrok Guru (`excl_guru_bentrok`)**:
  ```sql
  ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_guru_bentrok
  EXCLUDE USING gist (
      sekolah_id     WITH =,
      guru_id        WITH =,
      semester_id    WITH =,
      hari           WITH =,
      int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
  ) WHERE (deleted_at IS NULL);
  ```
- **Bentrok Ruang (`excl_ruang_bentrok`)**:
  ```sql
  ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_ruang_bentrok
  EXCLUDE USING gist (
      sekolah_id     WITH =,
      ruang_id       WITH =,
      semester_id    WITH =,
      hari           WITH =,
      int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
  ) WHERE (deleted_at IS NULL AND ruang_id IS NOT NULL);
  ```
- **Bentrok Rombel (`excl_rombel_bentrok`)**:
  ```sql
  ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_rombel_bentrok
  EXCLUDE USING gist (
      sekolah_id     WITH =,
      rombel_id      WITH =,
      semester_id    WITH =,
      hari           WITH =,
      int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
  ) WHERE (deleted_at IS NULL);
  ```

### 2. Lapis 2 — Aplikasi (`ConflictDetector` / UX Layer)
Service `ConflictDetector` melakukan pengecekan H1, H2, H3 (serta H4, H5, H6) sebelum query `INSERT`/`UPDATE` dikirim ke database. Logika di aplikasi mencerminkan aturan yang sama persis dengan constraint database, sehingga pengguna mendapatkan pesan kesalahan yang manusiawi dan kontekstual (*"Guru Budi Santoso sudah mengajar di kelas X RPL 1 pada jam ke-1–3"*) tanpa pernah melihat pesan error teknis `QueryException`.

### 3. Bukti Konkret (Test Bypass Level DB)
Keputusan ini dibuktikan secara konkret melalui suite pengujian [JadwalDatabaseConstraintTest.php](file:///home/rozi/projects/simpul/tests/Feature/Jadwal/JadwalDatabaseConstraintTest.php) (T-11.03):
- Test membypass seluruh logika aplikasi dan Eloquent dengan mengeksekusi `DB::table('jadwal_pelajaran')->insert()`.
- Percobaan insert slot tumpang tindih untuk guru yang sama, ruang yang sama, atau rombel yang sama terbukti secara deterministik digagalkan langsung oleh engine PostgreSQL dengan error `QueryException` (kode SQLSTATE `23P01` *exclusion_violation*).
- Soft-deleted record (`deleted_at IS NOT NULL`) terbukti diabaikan oleh constraint parsial, sehingga slot lama dapat dipakai kembali tanpa hambatan.

## Konsekuensi

- **Positif:** Jaminan integritas 0 bentrok lolos ke database (Exit Criteria G2 PRD tercapai mutlak); tahan terhadap *race condition*; konsistensi data multi-tenant terisolasi per `sekolah_id`.
- **Negatif:** Schema builder bawaan Laravel migration tidak mendukung DDL `EXCLUDE`, sehingga pembuatan constraint harus ditulis dalam raw SQL via `DB::statement()`. Basis data terkunci pada fitur PostgreSQL (sejalan dengan ADR-0002).
