import { format, isValid, parse } from 'date-fns';
import { es } from 'date-fns/locale';
import { CalendarIcon, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Components/ui/button';
import { Calendar } from '@/Components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { cn } from '@/lib/utils';

const ISO = 'yyyy-MM-dd';

/** 'AAAA-MM-DD' -> Date local (sin saltos de zona horaria); undefined si vacío o inválido. */
const toDate = (v?: string | null) => {
    if (!v) return undefined;
    const d = parse(v, ISO, new Date());
    return isValid(d) ? d : undefined;
};

/**
 * Selector de fecha. El valor es 'AAAA-MM-DD' (o '' si no hay), igual que `<input type="date">`,
 * así que reemplaza a ese input sin cambiar lo que viaja al servidor.
 */
export function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Selecciona una fecha',
    min,
    max,
    clearable = true,
    disabled,
    className,
}: {
    id?: string;
    value?: string | null;
    onChange: (value: string) => void;
    placeholder?: string;
    /** Fechas 'AAAA-MM-DD'; los días fuera del rango no se pueden elegir. */
    min?: string;
    max?: string;
    clearable?: boolean;
    disabled?: boolean;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = toDate(value);
    const minDate = toDate(min);
    const maxDate = toDate(max);

    const today = new Date();
    const todayAllowed =
        (!minDate || today >= minDate) && (!maxDate || today <= maxDate);

    const pick = (d?: Date) => {
        onChange(d ? format(d, ISO) : '');
        setOpen(false);
    };

    return (
        <div className={cn('relative', className)}>
            {/* modal: dentro de un Dialog el calendario debe recibir foco y teclado. */}
            <Popover open={open} onOpenChange={setOpen} modal>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-label={selected ? undefined : placeholder}
                        className={cn(
                            'w-full justify-start font-normal',
                            !selected && 'text-muted-foreground',
                            selected && clearable && 'pr-9',
                        )}
                    >
                        <CalendarIcon className="size-4 shrink-0 opacity-70" />
                        <span className="truncate">
                            {selected
                                ? format(selected, 'd MMM yyyy', { locale: es })
                                : placeholder}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={selected}
                        onSelect={pick}
                        defaultMonth={selected ?? minDate ?? undefined}
                        captionLayout="dropdown"
                        startMonth={
                            minDate ?? new Date(today.getFullYear() - 10, 0)
                        }
                        endMonth={
                            maxDate ?? new Date(today.getFullYear() + 5, 11)
                        }
                        disabled={[
                            ...(minDate ? [{ before: minDate }] : []),
                            ...(maxDate ? [{ after: maxDate }] : []),
                        ]}
                    />
                    {todayAllowed && (
                        <div className="border-t p-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-full"
                                onClick={() => pick(today)}
                            >
                                Hoy
                            </Button>
                        </div>
                    )}
                </PopoverContent>
            </Popover>

            {selected && clearable && !disabled && (
                <button
                    type="button"
                    aria-label="Limpiar fecha"
                    onClick={() => onChange('')}
                    className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                >
                    <X className="size-3.5" />
                </button>
            )}
        </div>
    );
}
