<?php

namespace App\Http\Controllers;

use App\Http\Requests\OptionRequest;
use App\Models\Option;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OptionController extends Controller
{
    public function store(OptionRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('create', Option::class);

        $role->options()->create($request->validated());

        return back()->with('success', 'Opción creada correctamente.');
    }

    public function update(OptionRequest $request, Option $option): RedirectResponse
    {
        $this->authorize('update', $option);

        $option->update($request->validated());

        return back()->with('success', 'Opción actualizada correctamente.');
    }

    public function destroy(Option $option): RedirectResponse
    {
        $this->authorize('delete', $option);

        $option->delete();

        return back()->with('success', 'Opción eliminada correctamente.');
    }
}
