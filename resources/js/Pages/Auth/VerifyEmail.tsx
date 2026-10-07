import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="Verifica tu correo"
            description="Antes de continuar, confirma tu correo con el enlace que te enviamos. Si no lo recibiste, podemos enviarte otro."
        >
            <Head title="Verificación de correo" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-success">Te enviamos un nuevo enlace de verificación.</div>
            )}

            <form onSubmit={submit} className="flex items-center justify-between gap-4">
                <Button type="submit" disabled={processing}>
                    Reenviar correo
                </Button>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-sm text-muted-foreground underline hover:text-foreground"
                >
                    Cerrar sesión
                </Link>
            </form>
        </GuestLayout>
    );
}
