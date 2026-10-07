import { Link, usePage } from '@inertiajs/react';
import { Fragment } from 'react';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { MenuItem, PageProps } from '@/types';

interface Crumb {
    label: string;
    href?: string;
}

const routeOk = (name: string | null): name is string => {
    try {
        return !!name && route().has(name);
    } catch {
        return false;
    }
};

/** Camino (grupos + hoja) del menú hasta la página actual; null si la página no está en el menú. */
function findTrail(
    items: MenuItem[],
    current: string,
    trail: MenuItem[] = [],
): { path: MenuItem[]; exact: boolean } | null {
    const leaves: { path: MenuItem[]; item: MenuItem }[] = [];
    const walk = (list: MenuItem[], t: MenuItem[]) =>
        list.forEach((i) => {
            if (routeOk(i.route)) leaves.push({ path: [...t, i], item: i });
            walk(i.children, [...t, i]);
        });
    walk(items, trail);

    const exact = leaves.find((l) => l.item.route === current);
    if (exact) return { path: exact.path, exact: true };

    // Páginas de detalle (solicitudes.show -> solicitudes.index): mismo recurso, si es único.
    const prefix = current.split('.')[0];
    const same = leaves.filter((l) => l.item.route!.split('.')[0] === prefix);
    return same.length === 1 ? { path: same[0].path, exact: false } : null;
}

/** Migas de pan del topbar, calculadas desde el menú que el usuario tiene permiso de ver. */
export function Breadcrumbs({ title }: { title?: string }) {
    const { menu } = usePage<PageProps>().props;

    let current = '';
    try {
        current = String(route().current() ?? '');
    } catch {
        // sin ziggy en este contexto
    }
    if (!current || current === 'dashboard') return null;

    const found = findTrail(menu ?? [], current);
    const crumbs: Crumb[] = [{ label: 'Inicio', href: route('dashboard') }];

    if (found) {
        found.path.forEach((m, idx) => {
            const isLeaf = idx === found.path.length - 1;
            // Los grupos no tienen página propia: texto sin enlace.
            crumbs.push({
                label: m.label,
                href: isLeaf && !found.exact ? route(m.route!) : undefined,
            });
        });
        // Página de detalle: la última miga es el título de la página.
        if (!found.exact && title) crumbs.push({ label: title });
    } else if (title) {
        crumbs.push({ label: title });
    }

    if (crumbs.length < 2) return null;

    return (
        <Breadcrumb>
            <BreadcrumbList>
                {crumbs.map((c, i) => {
                    const last = i === crumbs.length - 1;
                    // En pantallas angostas solo se ven las dos últimas migas.
                    const hide =
                        i < crumbs.length - 2 ? 'hidden sm:inline-flex' : '';
                    return (
                        <Fragment key={`${c.label}-${i}`}>
                            <BreadcrumbItem className={hide}>
                                {last ? (
                                    <BreadcrumbPage>{c.label}</BreadcrumbPage>
                                ) : c.href ? (
                                    <BreadcrumbLink asChild>
                                        <Link href={c.href}>{c.label}</Link>
                                    </BreadcrumbLink>
                                ) : (
                                    <span>{c.label}</span>
                                )}
                            </BreadcrumbItem>
                            {!last && <BreadcrumbSeparator className={hide} />}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
