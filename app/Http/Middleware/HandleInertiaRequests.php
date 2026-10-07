<?php

namespace App\Http\Middleware;

use App\Services\MenuBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // Permisos del agente para condicionar la UI (context.md §4.1).
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()
                    : [],
                'roles' => $user
                    ? $user->getRoleNames()->values()
                    : [],
            ],
            // Menú lateral jerárquico filtrado por permiso `ver` (context.md §8.6).
            // Sólo se construye para usuarios autenticados; los invitados no lo necesitan.
            'menu' => $user
                ? fn () => app(MenuBuilder::class)->forUser($user)
                : [],
            // Campana de notificaciones: contador y últimas 10 (carga diferida).
            'notifications' => $user
                ? [
                    'unread' => fn () => $user->unreadNotifications()->count(),
                    'items' => fn () => $user->notifications()->limit(10)->get()->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->data['title'] ?? '',
                        'body' => $n->data['body'] ?? '',
                        'kind' => $n->data['kind'] ?? '',
                        'read' => $n->read_at !== null,
                        'created_at' => $n->created_at?->diffForHumans(),
                    ])->values(),
                ]
                : ['unread' => 0, 'items' => []],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
