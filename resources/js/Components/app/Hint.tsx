import { PropsWithChildren } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

/** Tooltip para controles de solo ícono. El control debe llevar además su propio aria-label. */
export function Hint({
    label,
    children,
}: PropsWithChildren<{ label: string }>) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
