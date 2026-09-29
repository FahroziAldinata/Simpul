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
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'nuptk' => ['nullable', 'string', 'max:30'],
            'jenis' => ['required', Rule::in(['guru', 'tu', 'kepsek'])],
            'status_kepegawaian' => ['required', Rule::in(['pns', 'pppk', 'gty', 'gtt', 'honorer'])],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'jam_maks_per_minggu' => ['nullable', 'integer', 'min:1', 'max:60'],
            'hari_tidak_mengajar' => ['nullable', 'array'],
            'hari_tidak_mengajar.*' => [Rule::in(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'])],
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
