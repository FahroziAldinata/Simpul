<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStatusRequest extends FormRequest
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
        $isKeluar = in_array($this->input('status'), ['keluar', 'mutasi_keluar', 'pindah'], true);

        return [
            'siswa_ids' => ['required', 'array', 'min:1'],
            'siswa_ids.*' => ['required', 'uuid', 'distinct'],
            'status' => ['required', 'string', Rule::in(['keluar', 'mutasi_keluar', 'pindah', 'lulus', 'drop_out', 'do'])],
            'tanggal' => [$isKeluar ? 'required' : 'nullable', 'date'],
            'alasan' => [$isKeluar ? 'required' : 'nullable', 'string', 'max:1000'],
            'sekolah_tujuan' => [$isKeluar ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Custom attribute names for validation.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'siswa_ids' => 'daftar siswa terpilih',
            'status' => 'status tujuan',
            'tanggal' => 'tanggal mutasi',
            'alasan' => 'alasan mutasi',
            'sekolah_tujuan' => 'sekolah tujuan',
        ];
    }
}
