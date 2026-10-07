import { FormEventHandler, useRef } from 'react';
import { useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errs) => {
                if (errs.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errs.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Cambiar contraseña</CardTitle>
                <CardDescription>Usa una contraseña larga y aleatoria para mantener tu cuenta segura.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={updatePassword} className="max-w-xl space-y-4">
                    <FormField label="Contraseña actual" htmlFor="current_password" error={errors.current_password}>
                        <Input
                            id="current_password"
                            ref={currentPasswordInput}
                            type="password"
                            value={data.current_password}
                            autoComplete="current-password"
                            onChange={(e) => setData('current_password', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Contraseña nueva" htmlFor="password" error={errors.password}>
                        <Input
                            id="password"
                            ref={passwordInput}
                            type="password"
                            value={data.password}
                            autoComplete="new-password"
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

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            Guardar
                        </Button>
                        {recentlySuccessful && <span className="text-sm text-muted-foreground">Guardado.</span>}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
