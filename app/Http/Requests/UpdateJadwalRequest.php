<?php

namespace App\Http\Requests;

use App\Models\JadwalPelajaran;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var JadwalPelajaran|null $jadwal */
        $jadwal = $this->route('jadwal');

        return $jadwal ? ($this->user()?->can('update', $jadwal) ?? false) : false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'uuid', 'exists:semester,id'],
            'rombel_id' => ['required', 'uuid', 'exists:rombel,id'],
            'mata_pelajaran_id' => ['required', 'uuid', 'exists:mata_pelajaran,id'],
            'guru_id' => ['required', 'uuid', 'exists:pegawai,id'],
            'ruang_id' => ['nullable', 'uuid', 'exists:ruang,id'],
            'hari' => ['required', 'integer', 'between:1,6'],
            'jam_mulai_ke' => ['required', 'integer', 'min:1', 'max:12'],
            'jam_selesai_ke' => ['required', 'integer', 'gt:jam_mulai_ke', 'max:13'],
        ];
    }
}
