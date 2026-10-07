import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout
            title="Recuperar contraseña"
            description="Indica tu correo y te enviaremos un enlace para elegir una contraseña nueva."
        >
            <Head title="Recuperar contraseña" />

            {status && <div className="mb-4 text-sm font-medium text-success">{status}</div>}

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <div className="flex items-center justify-between gap-4">
                    <Link href={route('login')} className="text-sm text-muted-foreground underline hover:text-foreground">
                        Volver a iniciar sesión
                    </Link>
                    <Button type="submit" disabled={processing}>
                        Enviar enlace
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
