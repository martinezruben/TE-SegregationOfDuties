import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { PropsWithChildren, ReactNode } from 'react';

import { ThemeToggle } from '@/Components/app/ThemeToggle';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Toaster } from '@/Components/ui/sonner';
import { useFlashToasts } from '@/hooks/useFlashToasts';

/** Shell de las pantallas sin sesión (login, recuperación, verificación). */
export default function GuestLayout({
    title,
    description,
    children,
}: PropsWithChildren<{ title?: string; description?: ReactNode }>) {
    useFlashToasts();

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-muted/30 px-4 py-10">
            <div className="absolute right-4 top-4">
                <ThemeToggle />
            </div>

            <Link
                href="/"
                className="mb-6 flex items-center gap-2 text-foreground"
            >
                <ShieldCheck className="size-8" />
                <span className="text-2xl font-semibold">Sistema SoD</span>
            </Link>

            <Card className="w-full max-w-md">
                {(title || description) && (
                    <CardHeader>
                        {title && (
                            <CardTitle className="text-xl">{title}</CardTitle>
                        )}
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </CardHeader>
                )}
                <CardContent className={title || description ? '' : 'pt-6'}>
                    {children}
                </CardContent>
            </Card>
            <Toaster richColors position="bottom-right" />
        </div>
    );
}
