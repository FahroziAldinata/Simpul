# ADR-0007: Idempotency Key Sisi Klien untuk Sinkronisasi PWA Offline-First

## Status

Diterima (Accepted)

## Konteks

Pada modul absensi (PRD 6.2 & US-21), guru dan pegawai dapat melakukan pemindaian QR absensi dalam kondisi tanpa koneksi internet sama sekali (offline-first). Item absensi disimpan sementara dalam antrean lokal di perangkat (`IndexedDB` via Dexie.js).

Ketika koneksi internet pulih, antrean dikirim ke server melalui mekanisme Background Sync API atau fallback polling. Dalam lingkungan jaringan bergerak yang tidak stabil (flaky network / high packet loss), skenario berikut sangat lazim terjadi:
1. Klien mengirim data absensi ke server.
2. Server berhasil menyimpan data absensi ke database PostgreSQL.
3. Namun sebelum server sempat mengembalikan respon HTTP 200 ke klien, koneksi terputus.
4. Klien mengira request gagal, lalu mengirim ulang (retry) payload yang sama beberapa detik kemudian.

Tanpa mekanisme kontrol idempotensi yang ketat, retry ini berpotensi menyebabkan duplikasi data kehadiran atau error tabrakan unique constraint yang membingungkan pengguna.

## Keputusan

Menerapkan **Idempotency Key sisi klien**: klien yang menentukan identitas unik operasi, dan server memberikan jaminan eksekusi tepat satu kali (*exactly-once execution semantics*).

### 1. Klien Menentukan Identitas Operasi (`client_uuid`)
- Saat suatu aksi absensi dimasukkan ke antrean lokal (`enqueue`), browser menghasilkan **UUIDv4 tunggal** (`client_uuid`).
- `client_uuid` ini terikat pada item absensi tersebut dan tidak pernah berubah meskipun request dikirim berulang kali.
- Nilai ini disimpan di IndexedDB dan dikirimkan sebagai bagian dari payload dan header `Idempotency-Key`.

### 2. Jaminan Eksekusi Server-Side (`IdempotencyStore` & Unique Index)
- **Tingkat Database (`absensi.client_uuid`)**: Kolom `client_uuid` memiliki constraint `UNIQUE` di tabel `absensi`. Percobaan insert kedua dengan `client_uuid` yang sama akan dicegah oleh database dan dianggap sebagai respon sukses bagi klien.
- **Tingkat Request (`idempotency_keys`)**: Tabel `idempotency_keys` menyimpan `response_body` dan `status_code` dengan masa retensi (TTL) **7 hari**. Jika request dengan `Idempotency-Key` yang sama datang kembali dalam jendela 7 hari, server mengembalikan respon tersimpan secara langsung tanpa menjalankan ulang validasi atau query bisnis.
- **Verifikasi Exit Criteria**: Pengujian otomatis membuktikan skenario *sync ulang 5× dengan payload identik menghasilkan tepat 1 baris di database*.

### 3. Pengecualian Khusus CSRF untuk Service Worker
Endpoint `POST /api/absensi/sync` dikecualikan secara spesifik dari middleware `VerifyCsrfToken` di `bootstrap/app.php` dengan pertimbangan:
- Endpoint ini dipanggil secara asinkron oleh Service Worker / Background Sync dari context worker di background yang **tidak memiliki akses ke Document Object Model (DOM)** untuk membaca token CSRF.
- Endpoint **tetap dilindungi oleh autentikasi sesi web (`auth`)**: browser menyertakan session cookie (`credentials: 'include'`). Request tanpa sesi aktif tetap ditolak dengan kode `401 Unauthorized`.
- Proteksi terhadap serangan CSRF tetap terjaga karena:
  1. Header kustom `Idempotency-Key` dan `Content-Type: application/json` memicu CORS preflight jika dipanggil dari domain lain.
  2. Sifat operasi sync adalah idempoten terhadap `client_uuid` yang sudah terdaftar.
  3. `sekolah_id` diisolasi langsung dari relasi `pegawai->sekolah_id` pengguna yang login (Keputusan #1), bukan dari input klien yang bisa dimanipulasi.

## Konsekuensi & Trade-off

- **Positif:** 
  - Jaminan keandalan mutlak pada jaringan tidak stabil.
  - Perangkat pengguna tidak pernah menghasilkan data absensi ganda.
  - Alur Background Sync di Safari iOS dan browser Chromium berjalan mulus tanpa hambatan token CSRF stale.
- **Negatif / Mitigasi:**
  - Membutuhkan tabel `idempotency_keys` di database; dimitigasi dengan TTL 7 hari dan fungsi pembersihan record kedaluwarsa (`bersihkanKedaluwarsa`).
