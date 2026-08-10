<?php

namespace App\Http\Requests;

use App\Enums\RiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SodConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el par para que siempre se almacene role_a_id < role_b_id
     * (evita duplicados invertidos). Ver context.md §7.2.
     */
    protected function prepareForValidation(): void
    {
        $a = (int) $this->input('role_a_id');
        $b = (int) $this->input('role_b_id');

        if ($a > 0 && $b > 0 && $a !== $b) {
            $this->merge([
                'role_a_id' => min($a, $b),
                'role_b_id' => max($a, $b),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('matriz')?->id;

        return [
            'role_a_id' => ['required', 'exists:roles,id', 'different:role_b_id'],
            'role_b_id' => [
                'required', 'exists:roles,id',
                Rule::unique('sod_matrix', 'role_b_id')
                    ->where(fn ($q) => $q->where('role_a_id', $this->input('role_a_id')))
                    ->ignore($id),
            ],
            'risk_level' => ['required', new Enum(RiskLevel::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_a_id.different' => 'Un rol no puede entrar en conflicto consigo mismo.',
            'role_b_id.unique' => 'Ese par de roles ya está definido en la matriz.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role_a_id' => 'Rol A',
            'role_b_id' => 'Rol B',
            'risk_level' => 'nivel de riesgo',
        ];
    }
}
