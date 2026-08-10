import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ShieldCheck } from 'lucide-react';

import { cn } from '@/lib/utils';
import { menuIcon } from '@/lib/icons';
import { MenuItem, PageProps } from '@/types';

function routeExists(name: string | null): boolean {
    try {
        return !!name && route().has(name);
    } catch {
        return false;
    }
}

function isActive(name: string | null): boolean {
    try {
        return !!name && route().current(name);
    } catch {
        return false;
    }
}

function hasActiveDescendant(item: MenuItem): boolean {
    if (isActive(item.route)) return true;
    return item.children.some(hasActiveDescendant);
}

function Leaf({ item, depth }: { item: MenuItem; depth: number }) {
    const Icon = menuIcon(item.icon);
    const active = isActive(item.route);
    const exists = routeExists(item.route);

    const classes = cn(
        'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
        depth > 0 && 'pl-9',
        active
            ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
            : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
        !exists && 'cursor-not-allowed opacity-40 hover:bg-transparent',
    );

    const content = (
        <>
            <Icon className="size-4 shrink-0" />
            <span className="truncate">{item.label}</span>
        </>
    );

    if (!exists || !item.route) {
        return (
            <div className={classes} title="Disponible próximamente">
                {content}
            </div>
        );
    }

    return (
        <Link href={route(item.route)} className={classes}>
            {content}
        </Link>
    );
}

function Group({ item }: { item: MenuItem }) {
    const Icon = menuIcon(item.icon);
    const [open, setOpen] = useState(() => hasActiveDescendant(item));

    return (
        <div>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-sidebar-foreground/90 transition-colors hover:bg-sidebar-accent/60"
            >
                <Icon className="size-4 shrink-0" />
                <span className="flex-1 truncate text-left">{item.label}</span>
                <ChevronDown className={cn('size-4 transition-transform', open ? 'rotate-180' : '')} />
            </button>
            {open && (
                <div className="mt-1 space-y-1">
                    {item.children.map((child) =>
                        child.children.length > 0 ? (
                            <Group key={child.id} item={child} />
                        ) : (
                            <Leaf key={child.id} item={child} depth={1} />
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

export function SidebarNav() {
    const { menu } = usePage<PageProps>().props;

    return (
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
            {menu.map((item) =>
                item.children.length > 0 ? (
                    <Group key={item.id} item={item} />
                ) : (
                    <Leaf key={item.id} item={item} depth={0} />
                ),
            )}
        </nav>
    );
}

export function SidebarBrand() {
    return (
        <div className="flex h-14 items-center gap-2 border-b border-sidebar-border px-4">
            <ShieldCheck className="size-6 text-sidebar-foreground" />
            <span className="font-semibold text-sidebar-foreground">SoD</span>
        </div>
    );
}
