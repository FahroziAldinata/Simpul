# Definisi & Spesifikasi Teknis Kelengkapan Data Siswa

Dokumen ini mendefinisikan kriteria formal "Kelengkapan Data" pada entitas Siswa di Sistem Informasi Manajemen Sekolah (SIMPUL).

---

## 1. Latar Belakang & Prinsip Arsitektur

Status kelengkapan data siswa digunakan untuk memantau pemenuhan berkas pokok sebelum penerbitan kartu digital, pelaporan EMIS/Dapodik, dan integrasi ujian. 

Untuk menghindari masalah performa dan regresi query:
1. **Penyaringan Database (Filtering):** Wajib dieksekusi secara native pada level SQL Query (`scopeDataLengkap()` dan `scopeDataBelumLengkap()`) sebelum *pagination* dieksekusi (`LIMIT/OFFSET`). Akses filter tidak boleh memicu N+1 atau *lazy loading*.
2. **Indikator Tampilan (Badge UI):** Dihitung via accessor aman `is_data_lengkap` yang hanya mengevaluasi relasi yang **sudah dieager-load** (`$this->relationLoaded('wali')`), sehingga tidak ada kueri tambahan saat me-render tabel.

---

## 2. Kriteria Formal Kelengkapan Data

Seorang siswa diklasifikasikan berstatus **"Data Lengkap"** (`is_data_lengkap === true`) jika dan hanya jika **seluruh 7 parameter berikut terpenuhi**:

| No | Parameter | Kolom Database / Relasi | Aturan Validasi Kelengkapan |
|---|---|---|---|
| 1 | **NISN** | `siswa.nisn` | Tidak `NULL`, tidak kosong, dan tepat bernilai 10 digit (`length(nisn) = 10`). |
| 2 | **NIK** | `siswa.nik` | Tidak `NULL`, tidak kosong, dan tepat bernilai 16 digit (`length(nik) = 16`). |
| 3 | **Tempat Lahir** | `siswa.tempat_lahir` | Tidak `NULL` dan tidak string kosong (`!= ''`). |
| 4 | **Tanggal Lahir** | `siswa.tanggal_lahir` | Tidak `NULL` dan berformat tanggal valid. |
| 5 | **Alamat Domisili** | `siswa.alamat` | Tidak `NULL` dan tidak string kosong (`!= ''`). |
| 6 | **Kontak Siswa** | `siswa.no_hp` | Tidak `NULL` dan tidak string kosong (`!= ''`). |
| 7 | **Data Orang Tua / Wali** | `wali_siswa` (relasi `hasMany`) | Memiliki minimal **1 entri wali** di mana `nama` tidak kosong **DAN** minimal salah satu dari `no_hp` atau `pekerjaan` telah terisi. |

Jika salah satu atau lebih dari ketujuh kriteria di atas belum terpenuhi, maka siswa diklasifikasikan berstatus **"Belum Lengkap"** (`is_data_lengkap === false`).

---

## 3. Implementasi Query Scope (SQL Native)

Diimplementasikan pada `App\Models\Siswa`:

```php
public function scopeDataLengkap(Builder $query): Builder
{
    return $query
        ->whereNotNull('nisn')
        ->whereRaw('length(nisn) = 10')
        ->whereNotNull('nik')
        ->whereRaw('length(nik) = 16')
        ->whereNotNull('tempat_lahir')
        ->whereNotNull('tanggal_lahir')
        ->whereNotNull('alamat')
        ->where('alamat', '!=', '')
        ->whereNotNull('no_hp')
        ->where('no_hp', '!=', '')
        ->whereHas('wali', function (Builder $w) {
            $w->whereNotNull('nama')
                ->where('nama', '!=', '')
                ->where(function (Builder $sub) {
                    $sub->whereNotNull('no_hp')
                        ->orWhereNotNull('pekerjaan');
                });
        });
}
```
