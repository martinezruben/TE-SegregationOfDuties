import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';
import { DayPicker, getDefaultClassNames } from 'react-day-picker';
import { es } from 'react-day-picker/locale';

import { buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

export type CalendarProps = React.ComponentProps<typeof DayPicker>;

/** Calendario en español, semana desde el lunes, con los tokens del tema (claro/oscuro). */
function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    captionLayout = 'label',
    ...props
}: CalendarProps) {
    const d = getDefaultClassNames();

    return (
        <DayPicker
            locale={es}
            weekStartsOn={1}
            showOutsideDays={showOutsideDays}
            captionLayout={captionLayout}
            className={cn('p-3', className)}
            classNames={{
                root: cn('w-fit', d.root),
                months: cn(
                    'relative flex flex-col gap-4 sm:flex-row',
                    d.months,
                ),
                month: cn('flex w-full flex-col gap-3', d.month),
                nav: cn(
                    'absolute inset-x-0 top-0 flex w-full items-center justify-between',
                    d.nav,
                ),
                button_previous: cn(
                    buttonVariants({ variant: 'outline' }),
                    'size-7 bg-transparent p-0 opacity-70 hover:opacity-100 aria-disabled:opacity-30',
                    d.button_previous,
                ),
                button_next: cn(
                    buttonVariants({ variant: 'outline' }),
                    'size-7 bg-transparent p-0 opacity-70 hover:opacity-100 aria-disabled:opacity-30',
                    d.button_next,
                ),
                month_caption: cn(
                    'flex h-7 w-full items-center justify-center px-8',
                    d.month_caption,
                ),
                dropdowns: cn(
                    'flex h-7 w-full items-center justify-center gap-1.5 text-sm font-medium',
                    d.dropdowns,
                ),
                dropdown_root: cn(
                    'relative rounded-md border border-input has-[:focus-visible]:ring-1 has-[:focus-visible]:ring-ring',
                    d.dropdown_root,
                ),
                dropdown: cn(
                    'absolute inset-0 cursor-pointer opacity-0',
                    d.dropdown,
                ),
                caption_label: cn(
                    'select-none text-sm font-medium',
                    captionLayout !== 'label' &&
                        'flex h-7 items-center gap-1 rounded-md pl-2 pr-1 [&>svg]:size-3.5 [&>svg]:text-muted-foreground',
                    d.caption_label,
                ),
                month_grid: 'w-full border-collapse',
                weekdays: cn('flex', d.weekdays),
                weekday: cn(
                    'w-8 flex-1 select-none text-[0.8rem] font-normal capitalize text-muted-foreground',
                    d.weekday,
                ),
                week: cn('mt-1 flex w-full', d.week),
                day: cn(
                    'group/day relative size-8 flex-1 select-none p-0 text-center text-sm',
                    d.day,
                ),
                day_button: cn(
                    buttonVariants({ variant: 'ghost' }),
                    'size-8 p-0 font-normal',
                    'group-data-[today=true]/day:border group-data-[today=true]/day:border-primary/40',
                    'group-data-[selected=true]/day:bg-primary group-data-[selected=true]/day:text-primary-foreground group-data-[selected=true]/day:hover:bg-primary',
                    'group-data-[outside=true]/day:text-muted-foreground group-data-[outside=true]/day:opacity-50',
                    'group-data-[disabled=true]/day:pointer-events-none group-data-[disabled=true]/day:opacity-30',
                ),
                hidden: cn('invisible', d.hidden),
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation, className: c, ...rest }) => {
                    const Icon =
                        orientation === 'left'
                            ? ChevronLeft
                            : orientation === 'right'
                              ? ChevronRight
                              : ChevronDown;
                    return <Icon className={cn('size-4', c)} {...rest} />;
                },
            }}
            {...props}
        />
    );
}
Calendar.displayName = 'Calendar';

export { Calendar };
