import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Confirma tu contraseña" description="Es un área segura: confirma tu contraseña para continuar.">
            <Head title="Confirmar contraseña" />

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Contraseña" htmlFor="password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Confirmar
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
