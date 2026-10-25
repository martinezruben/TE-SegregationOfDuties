import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Value = string | number | null;

/**
 * Filtros de un listado paginado del servidor. Los selectores aplican al instante
 * (`apply`); el texto (`search`) se envía solo tras una pausa al escribir.
 * Los filtros viajan en la URL, así que sobreviven a paginar y recargar.
 */
export function useListFilters<T extends { search: string } & Record<string, Value>>(
    routeName: string,
    initial: T,
    empty: T,
) {
    const [f, setF] = useState<T>(initial);
    const last = useRef(initial.search ?? '');

    const go = (next: T) => {
        const params: Record<string, string> = {};
        Object.entries(next).forEach(([k, v]) => {
            if (v !== null && v !== '') params[k] = String(v);
        });
        router.get(route(routeName), params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const apply = (over: Partial<T>) => {
        const next = { ...f, ...over };
        setF(next);
        go(next);
    };
    const setSearch = (search: string) => setF((p) => ({ ...p, search }));
    const reset = () => {
        setF(empty);
        go(empty);
    };

    useEffect(() => {
        if (f.search === last.current) return;
        const t = setTimeout(() => {
            last.current = f.search;
            go(f);
        }, 300);
        return () => clearTimeout(t);
    }, [f.search]); // eslint-disable-line react-hooks/exhaustive-deps

    const hasFilters = Object.values(f).some((v) => v !== null && v !== '');

    return { f, apply, setSearch, reset, hasFilters };
}
