<?php

namespace App\Models;

use App\Enums\JenisAbsensi;
use App\Enums\StatusAbsensi;
use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\AbsensiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Model Absensi.
 *
 * Catatan kehadiran pegawai. Unique constraint (pegawai_id, tanggal, jenis) WHERE deleted_at IS NULL
 * ditegakkan via partial index di PostgreSQL — satu pegawai hanya satu catatan masuk/pulang per hari.
 *
 * `lokasi_mencurigakan` ditandai true jika di luar radius sekolah, tapi absensi TETAP disimpan
 * (tidak ditolak) — sesuai PRD 6.2: lebih baik meloloskan satu kecurangan untuk ditinjau daripada
 * menolak orang yang benar hadir.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $pegawai_id
 * @property Carbon $tanggal
 * @property JenisAbsensi $jenis
 * @property Carbon|null $waktu_server
 * @property Carbon|null $waktu_perangkat
 * @property StatusAbsensi|null $status
 * @property int $menit_terlambat
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $lokasi_mencurigakan
 * @property bool $perlu_ditinjau
 * @property string $sumber
 * @property string|null $client_uuid
 * @property int|null $dicatat_oleh
 * @property string|null $alasan_manual
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Absensi extends Model
{
    /** @use HasFactory<AbsensiFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity, SoftDeletes;

    protected $table = 'absensi';

    protected $fillable = [
        'sekolah_id',
        'pegawai_id',
        'tanggal',
        'jenis',
        'waktu_server',
        'waktu_perangkat',
        'status',
        'menit_terlambat',
        'latitude',
        'longitude',
        'lokasi_mencurigakan',
        'perlu_ditinjau',
        'sumber',
        'client_uuid',
        'dicatat_oleh',
        'alasan_manual',
        'pengajuan_izin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu_server' => 'datetime',
            'waktu_perangkat' => 'datetime',
            'jenis' => JenisAbsensi::class,
            'status' => StatusAbsensi::class,
            'menit_terlambat' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'lokasi_mencurigakan' => 'boolean',
            'perlu_ditinjau' => 'boolean',
        ];
    }

    /** @return BelongsTo<Pegawai, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /**
     * User (operator) yang mencatatkan absensi manual.
     *
     * @return BelongsTo<User, $this>
     */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
