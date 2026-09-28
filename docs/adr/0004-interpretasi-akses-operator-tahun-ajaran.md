# ADR-0004: Interpretasi Hak Akses Operator pada Modul Tahun Ajaran

## Status

Diusulkan / Menunggu Keputusan Final User (Proposed)

## Konteks

Pada dokumen PRD bagian 4.2 (Matriks Akses Berdasarkan Peran), terdapat baris:
* `Tahun Ajaran (rollover)` dengan hak akses `Super Admin: CRUD` dan peran lain tidak memiliki akses atau hanya `Super Admin`.
* Di sisi lain, pada operasional sekolah sehari-hari, data master Tahun Ajaran dan Semester merupakan bagian dari Data Induk yang perlu diisi atau diperbarui oleh pihak sekolah (Operator TU) saat inisiasi maupun pembaruan data semester aktif.

Sebelum aksi rollover diimplementasikan, perlu disepakati batas kewenangan role Operator terhadap Tahun Ajaran agar tidak terjadi ambiguitas hak akses.

## Keputusan & Catatan Kebijakan

Operator mendapat hak **CRU** (Create, Read, Update) Tahun Ajaran (tanpa rollover). Baris "Tahun Ajaran (rollover)" di PRD 4.2 ditafsirkan sebagai aksi rollover saja, yang baru diimplementasikan kemudian.

1. **Hak Operator:**
   - **View (Read):** Melihat daftar tahun ajaran dan semester aktif.
   - **Create:** Menambahkan data tahun ajaran baru (nama, tanggal mulai, tanggal selesai).
   - **Update:** Mengubah nama atau rentang tanggal tahun ajaran yang belum dikunci, serta mengaktifkan semester berjalan.
2. **Batasan Operator:**
   - **Delete:** Dilarang (`403 Forbidden`). Hak delete hanya dimiliki `super_admin`.
   - **Rollover:** Aksi rollover otomatis (salin rombel, kenaikan kelas, mutasi data tahunan) dikunci terpisah dan diimplementasikan pada modul mendatang.
3. **Status Keputusan:**
   - Catatan ini menjadi acuan kerja sementara; keputusan final ada di tangan User/Product Owner.

## Konsekuensi

- Operator dapat menyiapkan data kalender tahun ajaran sekolah tanpa intervensi Super Admin setiap tahun.
- Integritas data tetap terjaga karena Operator tidak dapat menghapus Tahun Ajaran yang sudah terdaftar.
