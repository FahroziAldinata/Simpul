export interface GuruItem {
    id: string;
    nama: string;
    nip?: string | null;
    jam_maks_per_minggu?: number | null;
    hari_tidak_mengajar?: number[] | null;
}

export interface RombelItem {
    id: string;
    nama: string;
    tingkat: number;
    semester_id: string;
}

export interface RuangItem {
    id: string;
    kode: string;
    nama: string;
    kategori: string;
    kapasitas: number;
}

export interface MataPelajaranItem {
    id: string;
    kode: string;
    nama: string;
    kelompok: string;
    bobot_beban_kognitif?: string;
    butuh_ruang_kategori?: string | null;
}

export interface JamKerjaItem {
    id: string;
    hari: number;
    kelompok: string;
    is_libur: boolean;
    jumlah_jam_pelajaran: number;
    jam_masuk?: string | null;
    jam_pulang?: string | null;
}

export interface AlokasiMapelItem {
    id: string;
    rombel_id: string;
    mata_pelajaran_id: string;
    guru_id?: string | null;
    jam_per_minggu: number;
    mata_pelajaran?: MataPelajaranItem;
    guru?: GuruItem | null;
    rombel?: RombelItem;
}

export interface JadwalItem {
    id: string;
    sekolah_id: string;
    semester_id: string;
    rombel_id: string;
    mata_pelajaran_id: string;
    guru_id: string;
    ruang_id?: string | null;
    hari: number; // 1=Senin..6=Sabtu
    jam_mulai_ke: number;
    jam_selesai_ke: number;
    durasi_jam?: number;
    guru?: GuruItem;
    rombel?: RombelItem;
    ruang?: RuangItem | null;
    mata_pelajaran?: MataPelajaranItem;
}

export interface DetailMapelRingkasan {
    mata_pelajaran_id: string;
    nama_mapel: string;
    alokasi: number;
    terjadwal: number;
    status: 'kurang' | 'tepat' | 'lebih';
}

export interface RombelRingkasan {
    rombel_id: string;
    nama_rombel: string;
    tingkat: number;
    total_alokasi: number;
    total_terjadwal: number;
    persentase: number;
    is_lengkap: boolean;
    detail_mapel: DetailMapelRingkasan[];
}

export interface SemesterItem {
    id: string;
    nama: string;
    is_aktif: boolean;
    tahun_ajaran?: {
        id: string;
        tahun_mulai: number;
        tahun_selesai: number;
    };
}

export type ViewMode = 'rombel' | 'guru' | 'ruang';

export interface DropEvaluation {
    isValid: boolean;
    type?: 'valid' | 'guru' | 'ruang' | 'rombel' | 'hari_libur' | 'jam_operasional' | 'alokasi' | 'ruang_kategori';
    message: string;
}
