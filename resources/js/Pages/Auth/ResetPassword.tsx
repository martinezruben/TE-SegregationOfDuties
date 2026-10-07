import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout title="Nueva contraseña">
            <Head title="Nueva contraseña" />

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField label="Contraseña nueva" htmlFor="password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <FormField label="Confirmar contraseña" htmlFor="password_confirmation" error={errors.password_confirmation}>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </FormField>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Restablecer contraseña
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
