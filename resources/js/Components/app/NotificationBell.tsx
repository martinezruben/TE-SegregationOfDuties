import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

import { Hint } from '@/Components/app/Hint';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { PageProps } from '@/types';

export function NotificationBell() {
    const { notifications } = usePage<PageProps>().props;
    const unread = notifications?.unread ?? 0;
    const items = notifications?.items ?? [];

    return (
        <DropdownMenu>
            <Hint label="Notificaciones">
                <DropdownMenuTrigger
                    className="relative rounded-md p-2 hover:bg-accent"
                    aria-label={
                        unread > 0
                            ? `Notificaciones: ${unread} sin leer`
                            : 'Notificaciones'
                    }
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute right-0.5 top-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-4 text-destructive-foreground">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </DropdownMenuTrigger>
            </Hint>
            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between pr-2">
                    <DropdownMenuLabel>Notificaciones</DropdownMenuLabel>
                    {unread > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    route('notifications.read-all'),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <CheckCheck className="size-3.5" /> Marcar todas
                        </button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {items.length === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        Sin notificaciones
                    </p>
                ) : (
                    items.map((n) => (
                        <DropdownMenuItem
                            key={n.id}
                            onSelect={() =>
                                router.post(route('notifications.read', n.id))
                            }
                            className="flex cursor-pointer flex-col items-start gap-0.5 whitespace-normal"
                        >
                            <span className="flex w-full items-center gap-2 text-sm font-medium">
                                {!n.read && (
                                    <span
                                        className="size-2 shrink-0 rounded-full bg-primary"
                                        aria-hidden
                                    />
                                )}
                                {n.title}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {n.body}
                            </span>
                            <span className="text-[11px] text-muted-foreground/70">
                                {n.created_at}
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
