export interface WaliItem {
    id?: string;
    jenis_wali: 'ayah' | 'ibu' | 'wali';
    nama: string;
    hubungan?: string | null;
    pekerjaan?: string | null;
    no_hp?: string | null;
    alamat?: string | null;
}

export interface AnggotaRombelItem {
    id?: string;
    rombel_id: string;
    rombel?: {
        id: string;
        nama: string;
        tingkat: number;
    };
}

export interface BerkasItem {
    id: string;
    sekolah_id: string;
    siswa_id: string;
    jenis: 'foto' | 'akta' | 'kk' | 'ijazah';
    file_path: string;
    nama_file_asli?: string | null;
    mime_type: string;
    file_size_bytes: number;
    created_at?: string;
    updated_at?: string;
}

export interface SiswaItem {
    id: string;
    sekolah_id: string;
    nisn: string;
    nik: string;
    nama: string;
    jenis_kelamin: 'L' | 'P';
    tempat_lahir: string;
    tanggal_lahir: string;
    agama: string;
    alamat: string | null;
    no_hp: string | null;
    status: 'aktif' | 'lulus' | 'mutasi_keluar' | 'drop_out' | 'non_aktif';
    is_data_lengkap?: boolean;
    wali?: WaliItem[];
    anggota_rombel_aktif?: AnggotaRombelItem | null;
    berkas?: BerkasItem[];
    created_at?: string;
    [key: string]: unknown;
}

export interface RombelOption {
    id: string;
    nama: string;
    tingkat: number;
}

export interface SemesterInfo {
    id: string;
    nama: string;
    is_aktif: boolean;
}
