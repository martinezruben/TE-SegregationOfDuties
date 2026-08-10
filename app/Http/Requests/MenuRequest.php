<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
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
        $menu = $this->route('menu');

        return [
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:255'],
            'permission_name' => ['nullable', 'string', Rule::exists('platform_permissions', 'name')],
            'parent_id' => [
                'nullable',
                Rule::exists('menus', 'id'),
                // No puede ser su propio padre ni un descendiente (evita ciclos).
                function ($attr, $value, $fail) use ($menu) {
                    if ($menu && $value && $this->createsCycle($menu, (int) $value)) {
                        $fail('El padre seleccionado generaría un ciclo.');
                    }
                },
            ],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    private function createsCycle(Menu $menu, int $parentId): bool
    {
        if ($parentId === $menu->id) {
            return true;
        }
        $ancestor = Menu::find($parentId);
        while ($ancestor) {
            if ($ancestor->id === $menu->id) {
                return true;
            }
            $ancestor = $ancestor->parent_id ? Menu::find($ancestor->parent_id) : null;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['label' => 'etiqueta', 'route' => 'ruta', 'permission_name' => 'permiso', 'parent_id' => 'padre'];
    }
}
