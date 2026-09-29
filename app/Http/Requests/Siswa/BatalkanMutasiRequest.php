<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BatalkanMutasiRequest extends FormRequest
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
            'alasan_batal' => ['required', 'string', 'min:3', 'max:1000'],
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
            'alasan_batal' => 'alasan pembatalan',
        ];
    }
}
