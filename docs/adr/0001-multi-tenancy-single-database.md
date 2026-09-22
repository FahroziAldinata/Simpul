# ADR-0001: Multi-Tenancy — Single Database dengan Kolom Diskriminator

## Status

Diterima (Accepted)

## Konteks

SIMPUL melayani beberapa sekolah (tenant) dalam satu instalasi. Ada tiga opsi umum arsitektur multi-tenancy:

1. **Database terpisah per tenant:** Satu PostgreSQL database untuk setiap sekolah.
2. **Skema terpisah per tenant:** Satu database, skema (search path) berbeda per sekolah.
3. **Single database dengan kolom diskriminator (`sekolah_id`):** Semua tabel transaksional berbagi skema dan tabel yang sama, dipisahkan oleh nilai kolom `sekolah_id`.

## Keputusan

Dipilih pendekatan **Single Database dengan Kolom Diskriminator (`sekolah_id`)**.

### Alasan:

- **Biaya Operasional Rendah:** Pengelolaan satu koneksi database, satu pool koneksi, dan satu proses backup/restore. Cocok untuk deployment server VPS tunggal.
- **Kemudahan Migrasi:** Migrasi database dijalankan sekali (`php artisan migrate`), bukan ratusan kali ke database berbeda yang rentan parsial fail.
- **Kemudahan Agregasi Global:** Super Admin / Dinas Pendidikan dapat melihat statistik atau laporan agregat lintas sekolah tanpa query federasi yang rumit.

### Mitigasi Risiko Kebocoran Data (Data Leakage):

Pemisahan logis memiliki risiko kebocoran data jika pengembang lupa menambahkan klausa `WHERE sekolah_id = ?`. Risiko ini dimitigasi secara berlapis:

1. **Trait `BelongsToSekolah`:** Diterapkan ke semua model transaksional untuk secara otomatis menyuntikkan `GlobalScope` filtering `sekolah_id = session('sekolah_id')`.
2. **Auto-Filling Observers / Model Boot:** Mengisi atribut `sekolah_id` secara otomatis saat pembuatan record baru (`creating`).
3. **Automated Tenant Isolation Tests:** Pengujian regresi otomatis (Pest) yang memverifikasi bahwa User dari Tenant A tidak dapat melihat, memodifikasi, atau menghapus resource milik Tenant B (menghasilkan `404 Not Found` atau koleksi kosong).

## Konsekuensi

- **Positif:** Siklus pengembangan cepat, deployment sederhana, penggunaan memori minimal.
- **Negatif:** Diperlukan disiplin ketat dalam penggunaan `withoutGlobalScope` dan penulisan query raw SQL. Seluruh indeks komposit pada tabel transaksional harus memuat `sekolah_id` di kolom pertama.
