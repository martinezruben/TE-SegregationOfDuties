import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Iniciar sesión" description="Ingresa con tu cuenta de agente.">
            <Head title="Iniciar sesión" />

            {status && <div className="mb-4 text-sm font-medium text-success">{status}</div>}

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField label="Contraseña" htmlFor="password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="remember"
                        checked={data.remember}
                        onCheckedChange={(v) => setData('remember', v === true)}
                    />
                    <Label htmlFor="remember" className="font-normal text-muted-foreground">
                        Recordarme
                    </Label>
                </div>

                <div className="flex items-center justify-between gap-4">
                    {canResetPassword ? (
                        <Link href={route('password.request')} className="text-sm text-muted-foreground underline hover:text-foreground">
                            ¿Olvidaste tu contraseña?
                        </Link>
                    ) : (
                        <span />
                    )}
                    <Button type="submit" disabled={processing}>
                        Entrar
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
