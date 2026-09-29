# Sumber Kebenaran Tunggal (Single Source of Truth) — Data Pegawai vs Akun Pengguna

Dokumen ini menjelaskan keputusan arsitektur terkait redundansi atribut `nama` dan `nip` antara tabel `pegawai` dan `users` di SIMPUL, penetapan sumber kebenaran utama, serta mekanisme sinkronisasi otomatis.

---

## 1. Penetapan Sumber Kebenaran (Single Source of Truth)

> **Keputusan:** Tabel `pegawai` adalah **sumber kebenaran tunggal (Primary Source of Truth)** untuk seluruh data identitas pegawai, termasuk `nama` dan `nip`.

Tabel `users` berperan sebagai **entitas akun autentikasi dan otorisasi (Identity & Access Management)**, bukan entitas profil induk kepegawaian.

---

## 2. Mengapa Kolom Tersebut Ada di Kedua Tabel?

Meskipun tabel `pegawai` menjadi acuan utama, kolom `name`, `nama_lengkap`, dan `nip` tetap dipertahankan di tabel `users` karena alasan teknis berikut:

### a. Kompatibilitas Framework dan Starter Kit
- Ekosistem Laravel (Fortify, Sanctum, Inertia starter kit) secara default bergantung pada `users.name` dan `users.email` untuk rendering profil header, breadcrumb pengguna, session state, dan penanganan autentikasi bawaan.
- Membaca `user.name` secara langsung pada session payload auth menghindari query join atau eager loading relasi `pegawai` pada setiap siklus request HTTP reguler.

### b. Fleksibilitas Multi-Aktor (Aktor Non-Pegawai)
- SIMPUL melayani aktor yang tidak memiliki catatan kepegawaian, seperti `super_admin` platform dan akun `orang_tua` (wali siswa).
- Jika `name` atau `nip` hanya berada di tabel `pegawai`, tabel `users` tidak akan mampu merepresentasikan identitas nama pengguna non-pegawai secara seragam.

### c. Mekanisme Multi-Identifier Login
- Tabel `users` mendukung login ganda: login berbasis `email` dan login berbasis `nip`.
- Indeks unik parsial `users_sekolah_id_nip_unique` pada tabel `users` memungkinkan pengecekan kredensial login NIP secara langsung pada layer autentikasi tanpa harus melakukan query silang ke tabel domain `pegawai`.

---

## 3. Mekanisme Sinkronisasi Otomatis

Untuk mencegah inkonsistensi data (data drift) antara kedua tabel, SIMPUL menerapkan sinkronisasi satu arah (*unidirectional synchronization*) dari `pegawai` ke `users`:

```mermaid
sequenceDiagram
    autonumber
    actor Op as Operator / Super Admin
    participant PC as PegawaiController
    participant P as Model: Pegawai (SSOT)
    participant U as Model: User (IAM)

    Op->>PC: Simpan / Ubah Data Pegawai
    Note over PC: Validasi Request (StorePegawaiRequest / UpdatePegawaiRequest)
    critical Transaksi Database (DB::transaction)
        PC->>P: Simpan / Update nama & nip
        PC->>U: Sinkronkan users.name, nama_lengkap, dan nip
        PC->>U: Sinkronkan peran (syncRoles)
    end
    PC-->>Op: Respons Berhasil
```

### Aturan Sinkronisasi:
1. **Pembuatan Pegawai Baru (`PegawaiController::store`):**
   - Membuat akun `users` dengan `name = pegawai.nama`, `nama_lengkap = pegawai.nama`, dan `nip = pegawai.nip`.
   - Mengisi flag `must_change_password = true` dan meng-generate kata sandi awal acak yang hanya tampil sekali.
   - Menetapkan peran dasar (`guru`, `operator`, `kepsek`) ditambah peran opsional penugasan (`waka_kurikulum`, `wali_kelas`).
2. **Pembaruan Pegawai (`PegawaiController::update`):**
   - Pembaruan dilakukan pada record `pegawai`.
   - Dalam transaksi database yang sama, jika pegawai memiliki akun `user`, sistem otomatis memperbarui `user.name`, `user.nama_lengkap`, dan `user.nip`.
3. **Penghapusan Pegawai (`PegawaiController::destroy`):**
   - Saat pegawai dihapus, akun `user` yang tertaut dihapus dalam transaksi yang sama.
   - Ini mencegah adanya "akun hantu" (ghost account) dan membebaskan batasan unik email/NIP di tabel `users`.
4. **Validasi Unik Tenant-Scoped:**
   - Baik `StorePegawaiRequest` maupun `UpdatePegawaiRequest` menegakkan aturan unik `nip` di kedua tabel secara serentak (`pegawai.nip` dan `users.nip`) terisolasi per sekolah (`sekolah_id`), dengan mengabaikan catatan saat ini pada operasi pembaruan.
   - Kolom `nip` dan `nuptk` bersifat `nullable`, sehingga validasi unik hanya aktif apabila field tersebut diisi (multiple `null` diperbolehkan).
