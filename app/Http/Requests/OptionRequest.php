<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $option = $this->route('option');
        $roleId = $option?->role_id ?? $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'action_key' => [
                'required', 'string', 'max:255',
                Rule::unique('options', 'action_key')
                    ->where(fn ($q) => $q->where('role_id', $roleId))
                    ->ignore($option?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'action_key' => 'clave de acción'];
    }
}
