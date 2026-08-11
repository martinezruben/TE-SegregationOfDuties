<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagedUserRequest extends FormRequest
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
        $id = $this->route('gestionado')?->id;

        return [
            'external_id' => ['required', 'string', 'max:100', Rule::unique('managed_users', 'external_id')->ignore($id)],
            'igg' => ['required', 'string', 'max:100', Rule::unique('managed_users', 'igg')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'area_id' => ['required', 'exists:areas,id'],
            'job_position_id' => ['required', 'exists:job_positions,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'external_id' => 'ID',
            'igg' => 'IGG',
            'name' => 'nombre',
            'area_id' => 'área',
            'job_position_id' => 'cargo',
        ];
    }
}
