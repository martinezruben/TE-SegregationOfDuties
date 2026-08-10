<?php

namespace App\Http\Requests;

use App\Enums\CriticalityLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ApplicationRequest extends FormRequest
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
        $id = $this->route('aplicacion')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('applications', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:255'],
            'criticality_level' => ['required', new Enum(CriticalityLevel::class)],
            'owner_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'criticality_level' => 'criticidad',
            'owner_id' => 'responsable',
        ];
    }
}
