import { usePage } from '@inertiajs/react';
import { Menu as MenuIcon } from 'lucide-react';
import { PropsWithChildren, ReactNode, useEffect } from 'react';
import { toast } from 'sonner';

import { Breadcrumbs } from '@/Components/app/Breadcrumbs';
import { CommandPalette } from '@/Components/app/CommandPalette';
import { NotificationBell } from '@/Components/app/NotificationBell';
import { SidebarBrand, SidebarNav } from '@/Components/app/Sidebar';
import { ThemeToggle } from '@/Components/app/ThemeToggle';
import { UserMenu } from '@/Components/app/UserMenu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { Toaster } from '@/Components/ui/sonner';
import { PageProps } from '@/types';

export default function AppLayout({
    title,
    header,
    children,
}: PropsWithChildren<{ title?: string; header?: ReactNode }>) {
    const { flash } = usePage<PageProps>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    return (
        <>
            <div className="min-h-screen bg-muted/30">
                {/* Sidebar fijo (desktop) */}
                <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-sidebar lg:flex">
                    <SidebarBrand />
                    <SidebarNav />
                </aside>

                <div className="lg:pl-64">
                    {/* Topbar */}
                    <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b bg-background px-4">
                        {/* Menú móvil */}
                        <Sheet>
                            <SheetTrigger
                                className="lg:hidden"
                                aria-label="Abrir menú"
                            >
                                <MenuIcon className="size-5" />
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="w-64 bg-sidebar p-0"
                            >
                                <SheetTitle className="sr-only">
                                    Menú
                                </SheetTitle>
                                <SidebarBrand />
                                <SidebarNav />
                            </SheetContent>
                        </Sheet>

                        <div className="min-w-0 flex-1">
                            <Breadcrumbs title={title} />
                            {header ??
                                (title ? (
                                    <h1 className="truncate text-lg font-semibold leading-tight">
                                        {title}
                                    </h1>
                                ) : null)}
                        </div>
                        <CommandPalette />
                        <ThemeToggle />
                        <NotificationBell />
                        <UserMenu />
                    </header>

                    <main className="mx-auto w-full max-w-[1800px] px-4 py-4 sm:px-6 sm:py-6 lg:px-10 xl:px-12">
                        {children}
                    </main>
                </div>

                <Toaster richColors position="bottom-right" />
            </div>
        </>
    );
}
