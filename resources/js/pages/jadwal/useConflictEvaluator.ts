import type { AlokasiMapelItem, DropEvaluation, JadwalItem, JamKerjaItem } from './types';

export function useConflictEvaluator() {
    const HARI_NAMES: Record<number, string> = {
        1: 'Senin',
        2: 'Selasa',
        3: 'Rabu',
        4: 'Kamis',
        5: 'Jumat',
        6: 'Sabtu',
    };

    function evaluateSlot(
        targetHari: number,
        targetJamMulai: number,
        activeCard: JadwalItem,
        allSchedules: JadwalItem[],
        jamKerjaList: JamKerjaItem[],
        _alokasiList?: AlokasiMapelItem[],
    ): DropEvaluation {
        const duration = Math.max(1, activeCard.jam_selesai_ke - activeCard.jam_mulai_ke);
        const targetJamSelesai = targetJamMulai + duration;
        const hariNama = HARI_NAMES[targetHari] ?? `Hari ${targetHari}`;

        // 1. Cek H5: Jam Kerja & Hari Libur
        const jamKerja = jamKerjaList.find((jk) => jk.hari === targetHari);
        if (jamKerja) {
            if (jamKerja.is_libur) {
                return {
                    isValid: false,
                    type: 'hari_libur',
                    message: `${hariNama} adalah hari libur operasional`,
                };
            }
            if (jamKerja.jumlah_jam_pelajaran > 0) {
                const maxJam = jamKerja.jumlah_jam_pelajaran;
                // jam selesai eksklusif, jadi jam pelajaran terakhir adalah targetJamSelesai - 1
                if (targetJamMulai < 1 || (targetJamSelesai - 1) > maxJam) {
                    return {
                        isValid: false,
                        type: 'jam_operasional',
                        message: `Di luar jam operasional ${hariNama} (maksimal jam ke-${maxJam})`,
                    };
                }
            }
        }

        // 2. Cek H1 (Guru), H2 (Ruang), H3 (Rombel) terhadap seluruh jadwal semester
        for (const schedule of allSchedules) {
            if (schedule.id === activeCard.id) {
                continue;
            }

            if (schedule.hari !== targetHari) {
                continue;
            }

            // Cek overlap rentang jam half-open [mulai, selesai):
            // schedule.jam_mulai_ke < targetJamSelesai AND schedule.jam_selesai_ke > targetJamMulai
            const overlap = (schedule.jam_mulai_ke < targetJamSelesai) && (schedule.jam_selesai_ke > targetJamMulai);
            if (!overlap) {
                continue;
            }

            // H1: Bentrok Guru
            if (schedule.guru_id === activeCard.guru_id) {
                const guruNama = schedule.guru?.nama ?? activeCard.guru?.nama ?? 'Guru';
                const rombelNama = schedule.rombel?.nama ?? 'kelas lain';
                return {
                    isValid: false,
                    type: 'guru',
                    message: `Bentrok Guru: ${guruNama} sudah mengajar di ${rombelNama} (Jam ke-${schedule.jam_mulai_ke}–${schedule.jam_selesai_ke})`,
                };
            }

            // H2: Bentrok Ruang (jika ruang_id terisi di kedua jadwal)
            if (activeCard.ruang_id && schedule.ruang_id && schedule.ruang_id === activeCard.ruang_id) {
                const ruangNama = schedule.ruang?.nama ?? activeCard.ruang?.nama ?? 'Ruang';
                const rombelNama = schedule.rombel?.nama ?? 'kelas lain';
                return {
                    isValid: false,
                    type: 'ruang',
                    message: `Bentrok Ruang: ${ruangNama} sudah digunakan oleh ${rombelNama} (Jam ke-${schedule.jam_mulai_ke}–${schedule.jam_selesai_ke})`,
                };
            }

            // H3: Bentrok Rombel
            if (schedule.rombel_id === activeCard.rombel_id) {
                const rombelNama = schedule.rombel?.nama ?? activeCard.rombel?.nama ?? 'Kelas';
                const mapelNama = schedule.mata_pelajaran?.nama ?? 'mata pelajaran lain';
                return {
                    isValid: false,
                    type: 'rombel',
                    message: `Bentrok Kelas: ${rombelNama} sudah memiliki jadwal ${mapelNama} (Jam ke-${schedule.jam_mulai_ke}–${schedule.jam_selesai_ke})`,
                };
            }
        }

        return {
            isValid: true,
            type: 'valid',
            message: 'Slot Tersedia',
        };
    }

    return {
        evaluateSlot,
        HARI_NAMES,
    };
}
