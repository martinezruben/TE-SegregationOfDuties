<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Gestión de Agentes/Operadores (usuarios con login + RBAC). context.md §8.5.
 */
class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = (string) $request->string('search');

        $agentes = User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roles->pluck('name'),
            ]);

        return Inertia::render('Agentes/Index', [
            'agentes' => $agentes,
            'roles' => Role::orderBy('name')->pluck('name'),
            'filters' => ['search' => $search],
            'currentUserId' => $request->user()->id,
            'can' => [
                'create' => Gate::allows('create', User::class),
                'update' => Gate::allows('update', $request->user()),
                'delete' => $request->user()->can('usuarios.eliminar'),
            ],
        ]);
    }

    public function store(AgentRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return back()->with('success', 'Agente creado correctamente.');
    }

    public function update(AgentRequest $request, User $agente): RedirectResponse
    {
        $this->authorize('update', $agente);

        $data = $request->validated();
        $agente->name = $data['name'];
        $agente->email = $data['email'];
        if (! empty($data['password'])) {
            $agente->password = Hash::make($data['password']);
        }
        $agente->save();
        $agente->syncRoles($data['roles'] ?? []);

        return back()->with('success', 'Agente actualizado correctamente.');
    }

    public function destroy(Request $request, User $agente): RedirectResponse
    {
        $this->authorize('delete', $agente);

        $agente->delete();

        return back()->with('success', 'Agente eliminado correctamente.');
    }
}
