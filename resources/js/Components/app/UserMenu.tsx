import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, User as UserIcon } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { PageProps } from '@/types';

export function UserMenu() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;
    const initials = user.name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent">
                <span className="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                    {initials}
                </span>
                <span className="hidden text-left sm:block">
                    <span className="block font-medium leading-tight">{user.name}</span>
                    <span className="block text-xs text-muted-foreground">{auth.roles[0] ?? 'Agente'}</span>
                </span>
                <ChevronDown className="size-4 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{user.email}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('profile.edit')} className="w-full cursor-pointer">
                        <UserIcon className="size-4" /> Perfil
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={route('logout')} method="post" as="button" className="w-full cursor-pointer">
                        <LogOut className="size-4" /> Cerrar sesión
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
