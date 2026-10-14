import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { PageProps } from '@/types';

/** Muestra como toast los mensajes flash del servidor (éxito / error). Sirve con y sin sesión. */
export function useFlashToasts() {
    const { flash } = usePage<PageProps>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);
}
