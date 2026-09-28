<?php

namespace App\Http\Requests\Siswa;

use App\Models\Siswa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSiswaWaliKelasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Siswa|string $siswaParam */
        $siswaParam = $this->route('siswa');
        $siswa = $siswaParam instanceof Siswa ? $siswaParam : Siswa::find($siswaParam);

        if (! $siswa) {
            return false;
        }

        return $this->user()?->can('update', $siswa) ?? false;
    }

    /**
     * Strict allowlist for Wali Kelas: only contact, address, and guardian data.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alamat' => ['nullable', 'string'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'wali' => ['nullable', 'array'],
            'wali.*.id' => ['nullable', 'uuid'],
            'wali.*.hubungan' => ['required_with:wali.*.nama', 'in:ayah,ibu,wali'],
            'wali.*.nama' => ['nullable', 'string', 'max:255'],
            'wali.*.pekerjaan' => ['nullable', 'string', 'max:100'],
            'wali.*.no_hp' => ['nullable', 'string', 'max:20'],
            'wali.*.alamat' => ['nullable', 'string'],
        ];
    }

    /**
     * Reject any forbidden master fields from rogue payloads.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $forbidden = [
                'nisn',
                'nik',
                'nama',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'status',
                'rombel_id',
            ];

            foreach ($forbidden as $field) {
                if ($this->has($field)) {
                    $v->errors()->add($field, "Wali Kelas tidak memiliki kewenangan mengubah kolom {$field}.");
                }
            }
        });
    }
}
