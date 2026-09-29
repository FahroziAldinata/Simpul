<?php

namespace App\Http\Requests\Pegawai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePegawaiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sekolahId = $this->user()?->hasRole('super_admin') ? session('sekolah_id') : $this->user()?->sekolah_id;

        return [
            'nama' => ['required', 'string', 'max:255'],
            'nip' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('pegawai', 'nip')
                    ->where(fn ($query) => $query->where('sekolah_id', $sekolahId)->whereNull('deleted_at')),
                Rule::unique('users', 'nip')
                    ->where(fn ($query) => $query->where('sekolah_id', $sekolahId)->whereNull('deleted_at')),
            ],
            'nuptk' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('pegawai', 'nuptk')
                    ->where(fn ($query) => $query->where('sekolah_id', $sekolahId)->whereNull('deleted_at')),
            ],
            'jenis' => ['required', Rule::in(['guru', 'tu', 'kepsek'])],
            'status_kepegawaian' => ['required', Rule::in(['pns', 'pppk', 'gty', 'gtt', 'honorer'])],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'jam_maks_per_minggu' => ['nullable', 'integer', 'min:1', 'max:60'],
            'hari_tidak_mengajar' => ['nullable', 'array'],
            'hari_tidak_mengajar.*' => [Rule::in(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::in(['guru', 'operator', 'kepsek', 'waka_kurikulum', 'wali_kelas']),
                Rule::notIn(['super_admin']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama' => 'nama lengkap',
            'nip' => 'NIP',
            'nuptk' => 'NUPTK',
            'jenis' => 'jenis pegawai',
            'status_kepegawaian' => 'status kepegawaian',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'agama' => 'agama',
            'alamat' => 'alamat domisili',
            'no_hp' => 'nomor HP',
            'email' => 'alamat email',
            'jam_maks_per_minggu' => 'jam mengajar maksimal per minggu',
            'hari_tidak_mengajar' => 'hari tidak mengajar',
        ];
    }
}
