import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { cn } from '@/lib/utils';

export interface ComboboxOption {
    value: string;
    label: string;
    /** Texto secundario (p. ej. IGG o aplicación); también se busca. */
    description?: string;
}

const normalize = (s: string) =>
    s
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

/**
 * Selector con búsqueda para listas largas (usuarios, roles, cargos…).
 * Misma forma de valor que `Select` (string; '' = sin selección) para poder intercambiarlos.
 * Busca sin distinguir mayúsculas ni acentos, en etiqueta y descripción.
 */
export function Combobox({
    id,
    value,
    onChange,
    options,
    placeholder = 'Selecciona…',
    searchPlaceholder = 'Buscar…',
    emptyText = 'Sin resultados.',
    clearLabel,
    disabled,
    className,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    options: ComboboxOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    /** Si se indica, agrega una primera opción que limpia la selección (valor ''). */
    clearLabel?: string;
    disabled?: boolean;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((o) => o.value === value);

    const pick = (v: string) => {
        onChange(v);
        setOpen(false);
    };

    return (
        // modal: dentro de un Dialog, la lista debe poder desplazarse con la rueda y recibir foco.
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">
                        {selected
                            ? selected.label
                            : clearLabel && value === ''
                              ? clearLabel
                              : placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] min-w-56 p-0"
                align="start"
            >
                <Command
                    filter={(_value, search, keywords) =>
                        normalize((keywords ?? []).join(' ')).includes(
                            normalize(search),
                        )
                            ? 1
                            : 0
                    }
                >
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {clearLabel && (
                                <CommandItem
                                    value="__clear__"
                                    keywords={[clearLabel]}
                                    onSelect={() => pick('')}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value === ''
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="text-muted-foreground">
                                        {clearLabel}
                                    </span>
                                </CommandItem>
                            )}
                            {options.map((o) => (
                                <CommandItem
                                    key={o.value}
                                    value={o.value}
                                    keywords={[o.label, o.description ?? '']}
                                    onSelect={() => pick(o.value)}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value === o.value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="truncate">{o.label}</span>
                                    {o.description && (
                                        <span className="ml-auto truncate text-xs text-muted-foreground">
                                            {o.description}
                                        </span>
                                    )}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
