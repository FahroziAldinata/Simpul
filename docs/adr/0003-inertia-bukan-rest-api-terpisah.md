# ADR-0003: Inertia.js 2 Dibandingkan Arsitektur REST API Terpisah

## Status

Diterima (Accepted)

## Konteks

Aplikasi berbasis web modern sering diimplementasikan dalam arsitektur decoupled: backend murni berupa REST/GraphQL API, dan frontend berupa SPA (Single Page Application) terpisah. Pola ini memunculkan overhead koordinasi:

- Definisi kontrak API (OpenAPI/Swagger) dan sinkronisasi tipe data DTO ganda.
- Kompleksitas otentikasi stateless (JWT token refresh, CSRF, cookie scoping).
- Duplikasi routing dan logika otorisasi di dua repositori terpisah.

Dalam konteks proyek yang dikembangkan secara terfokus (solo engineer), biaya sinkronisasi kontrak API memperlambat kecepatan rilis fitur secara signifikan.

## Keputusan

Memilih **Inertia.js 2** sebagai jembatan (monolith modern) antara Laravel dan Vue 3 + TypeScript.

### Alasan:

1. **Kecepatan Pengembangan Tunggal (Solo Developer Velocity):** Controller Laravel langsung mengembalikan `Inertia::render('Page', $props)`. Validasi form, session auth, otorisasi policy, dan routing dikelola di satu tempat.
2. **Kekuatan Vue 3 & Ekosistem Komponen Modern:** Tetap mendapatkan pengalaman SPA yang responsif, dynamic form interactions, reactive state management, serta komponen UI modern (shadcn-vue / Reka UI + Tailwind CSS 4) tanpa kerumitan membangun REST API endpoint untuk setiap tampilan.
3. **Type Safety End-to-End:** Integrasi TypeScript pada komponen Vue memanfaatkan props terstruktur yang diteruskan langsung dari backend Laravel.
4. **Keamanan Sesi First-Party:** Menggunakan session cookie standar Laravel yang aman dari serangan XSS token leakage, dilengkapi CSRF protection otomatis tanpa konfigurasi rumit.

## Konsekuensi & Trade-off

- **Positif:** Tidak ada biaya sinkronisasi kontrak API, routing terpusat di Laravel, waktu pengembangan berkurang hingga 40%.
- **Negatif (Trade-off yang Diterima):** Jika di masa mendatang dibutuhkan aplikasi klien native (Android/iOS) non-web, diperlukan penambahan lapisan REST/GraphQL API khusus untuk melayani klien tersebut. Namun untuk kebutuhan platform web SIMPUL saat ini, arsitektur monolitik modern Inertia adalah pilihan paling tepat.
