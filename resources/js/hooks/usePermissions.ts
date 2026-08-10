import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

/**
 * Acceso a los permisos/roles del agente autenticado (context.md §4.1).
 * La UI se condiciona con `can(...)`, pero el backend siempre revalida.
 */
export function usePermissions() {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];

    const can = (permission: string) => permissions.includes(permission);
    const canAny = (list: string[]) => list.some((p) => permissions.includes(p));
    const hasRole = (role: string) => roles.includes(role);

    return { permissions, roles, can, canAny, hasRole };
}
