# Celah Desain Terbuka: Mutasi Siswa Pindah Sekolah Lintas Tenant

## Status
**TERBUKA / KEPUTUSAN TERTUNDA** (Ditemukan dan diverifikasi pada Minggu 7 via test `tests/Feature/Siswa/CrossTenantNisnTest.php`).

---

## Deskripsi Masalah
1. **Aturan Database & PRD:**
   - PRD D3 menyatakan bahwa *siswa tidak pernah dihapus*, perubahan status (keluar/pindah/lulus/DO) hanya mengubah kolom `status` pada tabel `siswa`. `deleted_at` tetap `NULL`.
   - Tabel `siswa` memiliki partial unique index di level database:
     ```sql
     CREATE UNIQUE INDEX siswa_nisn_unique ON siswa (nisn) WHERE deleted_at IS NULL;
     ```
     Hal ini bertujuan menegakkan keunikan NISN secara nasional (lintas sekolah).

2. **Dampak pada Mutasi Pindah Sekolah Lintas Tenant:**
   - Ketika seorang siswa di Sekolah B melakukan mutasi `Keluar` (pindah sekolah), record di Sekolah B statusnya berubah menjadi `pindah`, namun record tersebut **tetap aktif di database** (`deleted_at IS NULL`).
   - Ketika Sekolah A mencoba mendaftarkan siswa baru tersebut atau memasukkan data lewat alur mutasi/impor dengan NISN yang sama, operasi **ditolak oleh unique index / validasi sistem**:
     ```
     NISN sudah terdaftar di sistem.
     ```
   - Akibatnya, saat ini belum ada alur resmi yang memungkinkan perpindahan kepemilikan record siswa (`sekolah_id`) antar-sekolah multi-tenant tanpa menabrak constraint unik `deleted_at IS NULL`.

---

## Opsi Solusi Arsitektural untuk Masa Depan (Bukan Bagian Minggu 7)
1. **Opsi A: Alur Transfer Kepemilikan Siswa (Disetujui Super Admin atau Handshake Dua Sekolah)**
   - Sekolah asal menerbitkan "Tiket Mutasi Keluar" dengan kode transfer unik.
   - Sekolah tujuan mengklaim tiket tersebut, yang mengubah `sekolah_id` siswa ke sekolah tujuan serta mencatat histori perpindahan tenant di tabel audit/mutasi tanpa membuat baris `siswa` baru.
2. **Opsi B: Mengubah Indeks Unik ke Kombinasi Status**
   - Menjadikan indeks unik hanya berlaku untuk siswa dengan status aktif:
     ```sql
     CREATE UNIQUE INDEX siswa_nisn_unique_aktif ON siswa (nisn) WHERE status = 'aktif' AND deleted_at IS NULL;
     ```
   - Namun opsi ini memiliki risiko duplikasi profil jika siswa kembali aktif di beberapa sekolah sekaligus tanpa sinkronisasi data induk.
3. **Opsi C: Entitas Global Identity Siswa Terpisah dari Profil Sekolah**
   - Memisahkan tabel `siswa_master` (global data: NISN, NIK, nama, tanggal lahir) dari `siswa_sekolah` (relasi tenant, nomor induk lokal, rombel).

---

## Keputusan Sementara untuk Minggu 7 (Pipeline Impor Excel)
- Menghindari pesan yang menjanjikan fitur yang belum ada (misalnya: *"gunakan alur Mutasi Masuk"*).
- Pesan error jika NISN sudah ada di tenant lain dibuat netral:
  > **"NISN sudah terdaftar di sistem. Hubungi Super Admin untuk verifikasi lebih lanjut."**
- Baris impor dengan NISN duplikat lintas tenant otomatis ditandai `GAGAL` dan tidak boleh diimpor langsung demi menjaga integritas data dan isolasi multi-tenant.
