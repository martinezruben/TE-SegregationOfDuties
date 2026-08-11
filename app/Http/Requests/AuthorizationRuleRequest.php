<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthorizationRuleRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'trigger_type' => ['required', Rule::in(['sod_risk', 'application', 'cargo_misalignment'])],
            // Requerido solo cuando el disparador es por criticidad de la matriz.
            'risk_level' => [
                Rule::requiredIf(fn () => $this->input('trigger_type') === 'sod_risk'),
                'nullable', Rule::in(['alto', 'medio', 'bajo']),
            ],
            // Requerido cuando el disparador es por aplicación; opcional (scope) en el resto.
            'application_id' => [
                Rule::requiredIf(fn () => $this->input('trigger_type') === 'application'),
                'nullable', 'exists:applications,id',
            ],
            'authorizer_type' => ['required', Rule::in(['fixed', 'supervisor'])],
            // La persona fija es obligatoria cuando no se usa "su supervisor".
            'authorizer_id' => [
                Rule::requiredIf(fn () => $this->input('authorizer_type') === 'fixed'),
                'nullable', 'exists:users,id',
            ],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliza los campos que no aplican al disparador/autorizador elegido.
        $this->merge([
            'risk_level' => $this->input('trigger_type') === 'sod_risk' ? $this->input('risk_level') : null,
            'application_id' => $this->input('trigger_type') === 'application'
                ? $this->input('application_id')
                : $this->input('application_id'), // se permite como scope opcional
            'authorizer_id' => $this->input('authorizer_type') === 'supervisor' ? null : $this->input('authorizer_id'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'trigger_type' => 'disparador',
            'risk_level' => 'nivel de riesgo',
            'application_id' => 'aplicación',
            'authorizer_type' => 'tipo de autorizador',
            'authorizer_id' => 'autorizador',
        ];
    }
}
