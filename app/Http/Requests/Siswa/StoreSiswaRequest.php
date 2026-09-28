<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiswaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('siswa.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nisn' => [
                'required',
                'string',
                'size:10',
                'regex:/^[0-9]{10}$/',
                Rule::unique('siswa', 'nisn')->whereNull('deleted_at'),
            ],
            'nik' => ['required', 'string', 'size:16', 'regex:/^[0-9]{16}$/'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'agama' => ['required', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:aktif,lulus,pindah,do'],
            'wali' => ['nullable', 'array'],
            'wali.*.hubungan' => ['required_with:wali.*.nama', 'in:ayah,ibu,wali'],
            'wali.*.nama' => ['nullable', 'string', 'max:255'],
            'wali.*.pekerjaan' => ['nullable', 'string', 'max:100'],
            'wali.*.no_hp' => ['nullable', 'string', 'max:20'],
            'wali.*.alamat' => ['nullable', 'string'],
            'rombel_id' => ['nullable', 'uuid', 'exists:rombel,id'],
            'nomor_absen' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nisn.unique' => 'NISN sudah terdaftar di sistem.',
            'nisn.size' => 'NISN harus tepat 10 digit angka.',
            'nisn.regex' => 'NISN harus berupa 10 digit angka.',
            'nik.size' => 'NIK harus tepat 16 digit angka.',
            'nik.regex' => 'NIK harus berupa 16 digit angka.',
        ];
    }
}
