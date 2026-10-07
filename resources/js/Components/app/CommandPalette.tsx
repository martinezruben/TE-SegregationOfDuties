import { router, usePage } from '@inertiajs/react';
import {
    CornerDownLeft,
    LogOut,
    Monitor,
    Moon,
    Search,
    Sun,
    User as UserIcon,
} from 'lucide-react';
import { ReactNode, useEffect, useMemo, useState } from 'react';

import { Hint } from '@/Components/app/Hint';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/Components/ui/command';
import { menuIcon } from '@/lib/icons';
import { useTheme } from '@/lib/theme';
import { MenuItem, PageProps } from '@/types';

interface Entry {
    key: string;
    label: string;
    trail: string; // ruta de carpetas: "Segregación (SoD)"
    route: string;
    icon: string | null;
}

function routeExists(name: string | null): name is string {
    try {
        return !!name && route().has(name);
    } catch {
        return false;
    }
}

/** Aplana el menú (ya filtrado por permisos en el servidor) en las páginas a las que se puede ir. */
function flatten(items: MenuItem[], trail: string[] = []): Entry[] {
    return items.flatMap((i) => [
        ...(routeExists(i.route)
            ? [
                  {
                      key: `${i.id}`,
                      label: i.label,
                      trail: trail.join(' › '),
                      route: i.route,
                      icon: i.icon,
                  },
              ]
            : []),
        ...flatten(i.children, [...trail, i.label]),
    ]);
}

const isMac = () =>
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/i.test(navigator.platform);

/** Paleta de comandos (⌘K / Ctrl+K): ir a cualquier página permitida y acciones rápidas. */
export function CommandPalette() {
    const { menu } = usePage<PageProps>().props;
    const { setTheme } = useTheme();
    const [open, setOpen] = useState(false);
    const entries = useMemo(() => flatten(menu ?? []), [menu]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const run = (fn: () => void) => {
        setOpen(false);
        fn();
    };

    const action = (
        key: string,
        label: string,
        icon: ReactNode,
        fn: () => void,
    ) => (
        <CommandItem
            key={key}
            value={key}
            keywords={[label]}
            onSelect={() => run(fn)}
        >
            {icon}
            {label}
        </CommandItem>
    );

    return (
        <>
            <Hint label={`Buscar (${isMac() ? '⌘K' : 'Ctrl K'})`}>
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    aria-label="Buscar páginas y acciones"
                    className="flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-sm text-muted-foreground hover:bg-accent"
                >
                    <Search className="size-4" />
                    <span className="hidden md:inline">Buscar…</span>
                    <kbd className="hidden rounded border bg-muted px-1.5 text-[10px] font-medium md:inline">
                        {isMac() ? '⌘K' : 'Ctrl K'}
                    </kbd>
                </button>
            </Hint>

            <CommandDialog
                open={open}
                onOpenChange={setOpen}
                title="Buscar"
                description="Busca páginas y acciones. Usa las flechas y Enter."
            >
                <CommandInput placeholder="Ir a una página o ejecutar una acción…" />
                <CommandList>
                    <CommandEmpty>Sin resultados.</CommandEmpty>
                    <CommandGroup heading="Ir a">
                        {entries.map((e) => {
                            const Icon = menuIcon(e.icon);
                            return (
                                <CommandItem
                                    key={e.key}
                                    value={`ir-${e.key}`}
                                    keywords={[e.label, e.trail]}
                                    onSelect={() =>
                                        run(() => router.visit(route(e.route)))
                                    }
                                >
                                    <Icon />
                                    <span>{e.label}</span>
                                    {e.trail && (
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {e.trail}
                                        </span>
                                    )}
                                </CommandItem>
                            );
                        })}
                    </CommandGroup>
                    <CommandSeparator />
                    <CommandGroup heading="Acciones">
                        {action('perfil', 'Mi perfil', <UserIcon />, () =>
                            router.visit(route('profile.edit')),
                        )}
                        {action('tema-claro', 'Tema claro', <Sun />, () =>
                            setTheme('light'),
                        )}
                        {action('tema-oscuro', 'Tema oscuro', <Moon />, () =>
                            setTheme('dark'),
                        )}
                        {action(
                            'tema-sistema',
                            'Tema del sistema',
                            <Monitor />,
                            () => setTheme('system'),
                        )}
                        {action('salir', 'Cerrar sesión', <LogOut />, () =>
                            router.post(route('logout')),
                        )}
                    </CommandGroup>
                </CommandList>
                <div className="flex items-center justify-end gap-1 border-t px-3 py-2 text-xs text-muted-foreground">
                    <CornerDownLeft className="size-3" /> seleccionar · ↑↓
                    navegar · esc cerrar
                </div>
            </CommandDialog>
        </>
    );
}
