<?php

namespace App\Http\Requests\Siswa;

use App\Enums\JenisMutasi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMutasiRequest extends FormRequest
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
            'tipe' => ['required', Rule::enum(JenisMutasi::class)],
            'tanggal' => ['required', 'date'],
            'alasan' => [
                Rule::requiredIf($this->input('tipe') === JenisMutasi::Keluar->value),
                'nullable',
                'string',
                'max:1000',
            ],
            'asal_sekolah' => [
                Rule::requiredIf($this->input('tipe') === JenisMutasi::Masuk->value),
                'nullable',
                'string',
                'max:255',
            ],
            'sekolah_tujuan' => [
                Rule::requiredIf($this->input('tipe') === JenisMutasi::Keluar->value),
                'nullable',
                'string',
                'max:255',
            ],
            'ke_rombel_id' => [
                Rule::requiredIf(in_array($this->input('tipe'), [
                    JenisMutasi::Masuk->value,
                    JenisMutasi::PindahRombel->value,
                    JenisMutasi::NaikKelas->value,
                    JenisMutasi::TinggalKelas->value,
                ], true)),
                'nullable',
                'uuid',
                'exists:rombel,id',
            ],
            'semester_id' => ['nullable', 'uuid', 'exists:semester,id'],
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
            'tipe' => 'jenis mutasi',
            'tanggal' => 'tanggal mutasi',
            'alasan' => 'alasan mutasi',
            'asal_sekolah' => 'asal sekolah',
            'sekolah_tujuan' => 'sekolah tujuan',
            'ke_rombel_id' => 'rombel tujuan',
            'semester_id' => 'semester',
        ];
    }
}
