<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkExportRequest extends FormRequest
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
            'siswa_ids' => ['nullable', 'array'],
            'siswa_ids.*' => ['uuid'],
            'status' => ['nullable', 'string'],
            'rombel_id' => ['nullable', 'uuid'],
            'tingkat' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ];
    }
}
