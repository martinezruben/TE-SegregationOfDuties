import { Monitor, Moon, Sun } from 'lucide-react';

import { Hint } from '@/Components/app/Hint';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Theme, useTheme } from '@/lib/theme';

const options: { value: Theme; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
    { value: 'system', label: 'Sistema', icon: Monitor },
];

export function ThemeToggle() {
    const { theme, dark, setTheme } = useTheme();
    const Icon = dark ? Moon : Sun;

    return (
        <DropdownMenu>
            <Hint label="Cambiar tema">
                <DropdownMenuTrigger
                    className="rounded-md p-2 hover:bg-accent"
                    aria-label="Cambiar tema"
                >
                    <Icon className="size-5" />
                </DropdownMenuTrigger>
            </Hint>
            <DropdownMenuContent align="end">
                {options.map(({ value, label, icon: I }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => setTheme(value)}
                        className="cursor-pointer gap-2"
                    >
                        <I className="size-4" /> {label}
                        {theme === value && (
                            <span className="ml-auto text-xs text-muted-foreground">
                                ✓
                            </span>
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
