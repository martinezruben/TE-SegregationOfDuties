import { Toaster as Sonner } from 'sonner';

import { useTheme } from '@/lib/theme';

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
    const { dark } = useTheme();

    return (
        <Sonner
            theme={dark ? 'dark' : 'light'}
            className="toaster group"
            toastOptions={{
                classNames: {
                    toast: 'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg',
                    description: 'group-[.toast]:text-muted-foreground',
                    actionButton: 'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
                    cancelButton: 'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
                    error: 'group-[.toaster]:!bg-destructive group-[.toaster]:!text-destructive-foreground',
                    success: 'group-[.toaster]:!bg-success group-[.toaster]:!text-success-foreground',
                },
            }}
            {...props}
        />
    );
};

export { Toaster };
