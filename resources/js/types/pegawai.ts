export interface PegawaiUser {
    id: number;
    name: string;
    email: string;
    must_change_password: boolean;
}

export interface PegawaiItem {
    id: string;
    sekolah_id: string;
    user_id?: number | null;
    nip?: string | null;
    nuptk?: string | null;
    nama: string;
    jenis: 'guru' | 'tu' | 'kepsek';
    status_kepegawaian: 'pns' | 'pppk' | 'gty' | 'gtt' | 'honorer';
    jenis_kelamin?: 'L' | 'P' | null;
    tempat_lahir?: string | null;
    tanggal_lahir?: string | null;
    agama?: string | null;
    alamat?: string | null;
    no_hp?: string | null;
    email?: string | null;
    jam_maks_per_minggu: number;
    hari_tidak_mengajar?: string[] | null;
    beban_mengajar_aktual?: number;
    created_at?: string;
    user?: PegawaiUser | null;
}
