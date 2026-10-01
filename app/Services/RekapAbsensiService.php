<?php

namespace App\Services;

use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\Pegawai;
use App\Models\Sekolah;
use Illuminate\Support\Carbon;

/**
 * RekapAbsensiService — matriks rekap bulanan per sekolah (T-09.07).
 *
 * Output: baris = pegawai, kolom = tanggal 1–31, sel = status + label huruf.
 * Kolom ringkasan: total H, T, I, S, C, D, A, total menit_terlambat.
 *
 * Performa target: <60 detik untuk 45 pegawai × 30 hari (US-23 AC6).
 * Strategi: satu query bulk ke tabel absensi, build matriks di PHP — tidak ada N+1.
 */
class RekapAbsensiService
{
    /**
     * Hasilkan data rekap matriks bulanan.
     *
     * @return array{
     *     bulan: int,
     *     tahun: int,
     *     hari_dalam_bulan: int,
     *     baris: array<int, array{
     *         pegawai_id: string,
     *         nama: string,
     *         nip: string|null,
     *         jenis: string,
     *         sel: array<string, array{label: string, warna: string, menit: int}|null>,
     *         ringkasan: array{H: int, T: int, I: int, S: int, C: int, D: int, A: int, total_menit: int},
     *     }>,
     *     durasi_ms: int,
     * }
     */
    public function generate(string $sekolahId, int $bulan, int $tahun, ?string $pegawaiId = null): array
    {
        $mulaiWaktu = hrtime(true);

        $mulai = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $selesai = $mulai->copy()->endOfMonth()->endOfDay();
        $hariDalamBulan = $mulai->daysInMonth;

        // Query pegawai aktif di sekolah ini
        $pegawaiQuery = Pegawai::where('sekolah_id', $sekolahId)
            ->orderBy('nama');

        if ($pegawaiId) {
            $pegawaiQuery->where('id', $pegawaiId);
        }

        $pegawaiList = $pegawaiQuery->get(['id', 'nama', 'nip', 'jenis']);

        if ($pegawaiList->isEmpty()) {
            return [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'hari_dalam_bulan' => $hariDalamBulan,
                'baris' => [],
                'durasi_ms' => 0,
            ];
        }

        $pegawaiIds = $pegawaiList->pluck('id')->toArray();

        // Satu query bulk — tidak ada N+1
        $absensiRows = Absensi::where('sekolah_id', $sekolahId)
            ->whereIn('pegawai_id', $pegawaiIds)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->where('jenis', 'masuk') // Satu baris per tanggal (izin pun jenis='masuk')
            ->get(['pegawai_id', 'tanggal', 'status', 'menit_terlambat'])
            ->groupBy(fn ($row) => $row->pegawai_id.'_'.$row->tanggal->toDateString());

        // Build matriks
        $baris = [];
        foreach ($pegawaiList as $pegawai) {
            $sel = [];
            $ringkasan = ['H' => 0, 'T' => 0, 'I' => 0, 'S' => 0, 'C' => 0, 'D' => 0, 'A' => 0, 'total_menit' => 0];

            for ($hari = 1; $hari <= $hariDalamBulan; $hari++) {
                $tanggalStr = Carbon::create($tahun, $bulan, $hari)->toDateString();
                $key = $pegawai->id.'_'.$tanggalStr;

                $absensi = $absensiRows->get($key)?->first();

                if ($absensi && $absensi->status instanceof StatusAbsensi) {
                    $status = $absensi->status;
                    $label = $status->labelRekap();
                    $warna = $status->warnaTailwind();
                    $menit = (int) $absensi->menit_terlambat;

                    $sel[$tanggalStr] = ['label' => $label, 'warna' => $warna, 'menit' => $menit];

                    // Update ringkasan
                    if (isset($ringkasan[$label])) {
                        $ringkasan[$label]++;
                    }
                    $ringkasan['total_menit'] += $menit;
                } else {
                    $sel[$tanggalStr] = null; // Tidak ada data absensi atau status null
                }
            }

            $baris[] = [
                'pegawai_id' => $pegawai->id,
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'jenis' => $pegawai->jenis,
                'sel' => $sel,
                'ringkasan' => $ringkasan,
            ];
        }

        $durasiMs = (int) round((hrtime(true) - $mulaiWaktu) / 1_000_000);

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'hari_dalam_bulan' => $hariDalamBulan,
            'baris' => $baris,
            'durasi_ms' => $durasiMs,
        ];
    }
}
