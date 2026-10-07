import { PropsWithChildren } from 'react';

import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

/** Etiqueta + control + mensaje de error, con espaciado uniforme. */
export function FormField({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: PropsWithChildren<{ label: string; htmlFor: string; error?: string; hint?: string; className?: string }>) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
