import { FormEventHandler, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', recovery_code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge'), { onFinish: () => reset('code', 'recovery_code') });
    };

    const toggle = () => {
        setUseRecovery((v) => !v);
        reset('code', 'recovery_code');
    };

    return (
        <GuestLayout
            title="Verificación en dos pasos"
            description={
                useRecovery
                    ? 'Ingresa uno de tus códigos de recuperación de emergencia.'
                    : 'Ingresa el código de 6 dígitos de tu aplicación autenticadora.'
            }
        >
            <Head title="Verificación en dos pasos" />

            <form onSubmit={submit} className="space-y-4">
                {useRecovery ? (
                    <FormField label="Código de recuperación" htmlFor="recovery_code" error={errors.recovery_code}>
                        <Input
                            id="recovery_code"
                            value={data.recovery_code}
                            autoComplete="one-time-code"
                            autoFocus
                            onChange={(e) => setData('recovery_code', e.target.value)}
                        />
                    </FormField>
                ) : (
                    <FormField label="Código" htmlFor="code" error={errors.code}>
                        <Input
                            id="code"
                            inputMode="numeric"
                            value={data.code}
                            autoComplete="one-time-code"
                            autoFocus
                            onChange={(e) => setData('code', e.target.value)}
                        />
                    </FormField>
                )}

                <div className="flex items-center justify-between gap-4">
                    <button type="button" onClick={toggle} className="text-left text-sm text-muted-foreground underline hover:text-foreground">
                        {useRecovery ? 'Usar código de la app' : 'Usar código de recuperación'}
                    </button>
                    <div className="flex items-center gap-3">
                        <Link href={route('login')} className="text-sm text-muted-foreground underline hover:text-foreground">
                            Cancelar
                        </Link>
                        <Button type="submit" disabled={processing}>
                            Verificar
                        </Button>
                    </div>
                </div>
            </form>
        </GuestLayout>
    );
}
