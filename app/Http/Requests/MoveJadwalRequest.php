<?php

namespace App\Http\Requests;

use App\Models\JadwalPelajaran;
use Illuminate\Foundation\Http\FormRequest;

class MoveJadwalRequest extends FormRequest
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
            'hari' => ['required', 'integer', 'between:1,6'],
            'jam_mulai_ke' => ['required', 'integer', 'min:1', 'max:12'],
            'jam_selesai_ke' => ['required', 'integer', 'gt:jam_mulai_ke', 'max:13'],
            'ruang_id' => ['nullable', 'uuid', 'exists:ruang,id'],
        ];
    }
}
