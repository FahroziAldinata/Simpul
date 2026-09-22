# SIMPUL — Sistem Informasi Manajemen Pembelajaran & Urusan Lembaga

SIMPUL adalah platform sistem informasi sekolah modern yang berfokus pada **4 modul inti mendalam**: Multi-Tenancy & Data Induk Akademik, Presensi Pegawai Geofencing & QR Dinamis, Penjadwalan Pelajaran Otomatis (Constraint Satisfaction Problem), dan Impor Data Dua Tahap.

---

## 1. Fondasi Teknis (Tech Stack)

- **Backend Framework:** Laravel 13
- **Runtime:** PHP 8.3 (pinned di container Sail & Dockerfile)
- **Frontend Bridge:** Inertia.js 2
- **UI & Components:** Vue 3 (Composition API) + TypeScript (`strict: true`) + shadcn-vue (Reka UI) + Tailwind CSS 4
- **Database:** PostgreSQL 16 (Image resmi `postgres:16` standar — koordinat GPS menggunakan kalkulasi Haversine tanpa dependensi PostGIS)
- **Cache & Queue:** Redis 7 + Laravel Horizon
- **Realtime WebSocket:** Laravel Reverb
- **PDF Renderer:** Gotenberg 8
- **Object Storage:** MinIO (lokal) / Cloudflare R2 kompatibel S3 (produksi)
- **Testing & Quality Tooling:** Pest 4 (Pest PHP 8.3 + Laravel 13 engine), Larastan Level 6, Laravel Pint, ESLint, Prettier

---

## 2. Arsitektur Docker Services & Alokasi Port

Untuk menghindari bentrok port dengan proyek lain (khususnya PALMVISION yang menggunakan port default Sail `80`, `5173`, `5432`, `6379`), SIMPUL menggunakan alokasi port khusus:

| Service | Container Image | Port Host : Container | Deskripsi |
|---|---|---|---|
| `laravel.test` | `sail-8.3/app` | `8080 : 80` (App) <br> `5174 : 5174` (Vite) | Web application server (PHP 8.3 + Nginx/Node) |
| `pgsql` | `postgres:16` | `5433 : 5432` | Basis data utama PostgreSQL 16 |
| `redis` | `redis:alpine` | `6380 : 6379` | Cache, session, dan queue driver |
| `horizon` | `sail-8.3/app` | *Internal network* | Queue worker supervisor (`php artisan horizon`) |
| `reverb` | `sail-8.3/app` | `8081 : 8080` | Server WebSocket real-time (`php artisan reverb:start`) |
| `gotenberg` | `gotenberg/gotenberg:8` | `3001 : 3000` | Microservice headless Chromium PDF renderer |
| `minio` | `minio/minio:latest` | `9002 : 9000` (API) <br> `9003 : 9001` (Console) | S3-compatible local object storage |

---

## 3. Menjalankan Proyek Secara Lokal

### Prerequisites
- Docker & Docker Compose
- WSL2 (Ubuntu) atau Linux

### Perintah Cepat
```bash
# 1. Salin environment config
cp .env.example .env

# 2. Nyalakan seluruh 7 service Docker
./vendor/bin/sail up -d

# 3. Jalankan migrasi basis data
./vendor/bin/sail artisan migrate

# 4. Bangun asset frontend
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Aplikasi dapat diakses di peramban: `http://localhost:8080`

---

## 4. Quality Tooling & Verification

```bash
# Menjalankan test suite (Pest)
./vendor/bin/sail artisan test

# Pemeriksaan code style PHP (Laravel Pint)
./vendor/bin/sail bin pint --test

# Analisis statis PHP (Larastan Level 6)
./vendor/bin/sail bin phpstan analyse --no-progress

# Pemeriksaan linter frontend (ESLint)
./vendor/bin/sail npm run lint

# Pemeriksaan tipe TypeScript
./vendor/bin/sail npm run types:check
```

---

## 5. Dokumentasi Arsitektur & Keputusan

- **Desain ERD Lengkap:** [`docs/erd.md`](docs/erd.md)
- **ADR-0000:** [`docs/adr/0000-ruang-lingkup-portofolio.md`](docs/adr/0000-ruang-lingkup-portofolio.md) (Ruang Lingkup & Catatan Deviasi Sadar)
- **ADR-0001:** [`docs/adr/0001-multi-tenancy-single-database.md`](docs/adr/0001-multi-tenancy-single-database.md) (Multi-Tenancy Single Database)
- **ADR-0002:** [`docs/adr/0002-postgresql-bukan-mysql.md`](docs/adr/0002-postgresql-bukan-mysql.md) (PostgreSQL 16 vs MySQL)
- **ADR-0003:** [`docs/adr/0003-inertia-bukan-rest-api-terpisah.md`](docs/adr/0003-inertia-bukan-rest-api-terpisah.md) (Inertia.js 2 Monolith Modern)
