<?php

namespace App\Http\Requests\Impor;

use App\Models\Siswa;
use App\Services\Impor\ColumnMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SaveMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Siswa::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
            'template_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Validate that all mandatory system fields have been assigned.
     */
    public function validateMandatoryFields(): void
    {
        $mapping = $this->input('mapping', []);
        $mappedSystemFields = array_filter(array_values($mapping));

        $missing = [];
        foreach (ColumnMapper::SYSTEM_FIELDS as $systemField => $config) {
            if ($config['required'] && ! in_array($systemField, $mappedSystemFields, true)) {
                $missing[] = $config['label'];
            }
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'mapping' => 'Kolom wajib berikut belum dipetakan: '.implode(', ', $missing).'.',
            ]);
        }
    }
}
