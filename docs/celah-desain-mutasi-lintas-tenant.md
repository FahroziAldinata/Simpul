# Solusi Desain: Mutasi Siswa Pindah Sekolah dan Pembebasan NISN Lintas Tenant

## Status
**TERSELESAIKAN** (Diterapkan dan diverifikasi sebelum Minggu 8).

---

## Konteks & Analisis Masalah
1. **Identitas Permanen NISN:**
   - NISN adalah nomor identitas nasional siswa seumur hidup yang tidak berubah saat naik jenjang atau pindah sekolah.
   - Dalam arsitektur multi-tenant SIMPUL di mana tiap jenjang/sekolah memiliki `sekolah_id` masing-masing, skenario "siswa naik jenjang ke sekolah lain" dan "siswa pindah sekolah" adalah kasus identik dari sudut pandang sistem: keduanya menuntut NISN yang sama dapat didaftarkan di sekolah tujuan setelah siswa tidak lagi aktif di sekolah asal.

2. **Kendala Partial Unique Index Sebelumnya:**
   - Skema database memiliki partial unique index:
     ```sql
     CREATE UNIQUE INDEX siswa_nisn_unique ON siswa (nisn) WHERE deleted_at IS NULL;
     ```
   - Sebelumnya, mutasi Keluar/Lulus/DO hanya memperbarui kolom `status` (`pindah`, `lulus`, `drop_out`) tanpa melakukan soft delete (`deleted_at` tetap `NULL`).
   - Akibatnya, partial unique index tetap mengunci NISN tersebut secara nasional, sehingga sekolah baru tidak dapat mendaftarkan siswa dengan NISN yang sama.

---

## Solusi yang Diterapkan

1. **Pembebasan Otomatis NISN via Soft Delete pada Mutasi Keluar, Lulus, dan Drop Out:**
   - Pada `MutasiService::executeMutasi()`, untuk jenis mutasi `Keluar`, `Lulus`, dan `DropOut`:
     - Kolom `status` tetap diperbarui ke status mutasi terkait (`pindah`, `lulus`, `drop_out`).
     - Baris `siswa` di-soft-delete (`$siswa->delete()`) dalam transaksi database yang sama.
     - Baris historis pada `anggota_rombel` dan `mutasi_siswa` **tetap utuh 100%** (tidak ikut dihapus).
     - Karena partial unique index hanya mengevaluasi baris dengan `WHERE deleted_at IS NULL`, pembebasan NISN terjadi secara otomatis dan instan tanpa risiko race condition. Sekolah tujuan dapat langsung mendaftarkan siswa dengan NISN tersebut.

2. **Integritas Relasi dan Akses Data Historis (Riwayat Kelas):**
   - Relasi `siswa()` pada model `MutasiSiswa`, `AnggotaRombel`, `BerkasSiswa`, dan `WaliSiswa` dikonfigurasi dengan `->withTrashed()`.
   - Endpoint detail siswa (`siswa.show`) dan endpoint riwayat mutasi/kelas (`siswa.mutasi.index`) mendukung pemanggilan siswa yang berstatus soft-deleted (`->withTrashed()`), sehingga Operator dan Super Admin tetap dapat mengakses riwayat kelas dan data historis siswa yang sudah keluar/lulus/DO.

3. **Pembatalan Mutasi LIFO dengan Dual-Path & Proteksi Konflik NISN:**
   - Pembatalan mutasi `Keluar`, `Lulus`, atau `DropOut` mencakup pemulihan record siswa (`$siswa->restore()`).
   - **Guard Anti-Konflik:** Sebelum me-restore, sistem memeriksa apakah NISN siswa telah digunakan oleh siswa aktif lain di tenant manapun di seluruh sistem.
   - Jika NISN sudah aktif digunakan di sekolah lain, pembatalan **ditolak secara eksplisit** dengan validasi:
     > *"Mutasi ini tidak bisa dibatalkan karena NISN siswa sudah aktif digunakan di sekolah lain."*
   - Jika NISN masih bebas, siswa dipulihkan (`restore()`), status dikembalikan ke status sebelum mutasi, dan histori pembatalan dicatat rapi.
