<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkRombelRequest extends FormRequest
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
            'siswa_ids' => ['required', 'array', 'min:1'],
            'siswa_ids.*' => ['required', 'uuid', 'distinct'],
            'rombel_id' => ['required', 'uuid', 'exists:rombel,id'],
            'alasan' => ['nullable', 'string', 'max:1000'],
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
            'rombel_id' => 'rombel tujuan',
            'alasan' => 'alasan mutasi',
        ];
    }
}
