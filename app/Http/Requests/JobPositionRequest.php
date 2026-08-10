<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Un cargo genérico no puede apuntar a otro genérico.
        if ($this->boolean('is_generic')) {
            $this->merge(['generic_position_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('cargo')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_generic' => ['boolean'],
            'is_active' => ['boolean'],
            'generic_position_id' => [
                'nullable',
                Rule::exists('job_positions', 'id')->where(fn ($q) => $q->where('is_generic', true)),
                // No puede ser su propio genérico.
                Rule::notIn($id !== null ? [$id] : []),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'generic_position_id' => 'cargo genérico',
        ];
    }
}
