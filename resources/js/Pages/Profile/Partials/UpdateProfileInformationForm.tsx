import { FormEventHandler } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Información del perfil</CardTitle>
                <CardDescription>Actualiza tu nombre y tu correo electrónico.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="max-w-xl space-y-4">
                    <FormField label="Nombre" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} required autoComplete="name" onChange={(e) => setData('name', e.target.value)} />
                    </FormField>

                    <FormField label="Correo electrónico" htmlFor="email" error={errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            required
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div className="text-sm">
                            <p>
                                Tu correo no está verificado.{' '}
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="text-muted-foreground underline hover:text-foreground"
                                >
                                    Reenviar el correo de verificación.
                                </Link>
                            </p>
                            {status === 'verification-link-sent' && (
                                <p className="mt-2 font-medium text-success">Te enviamos un nuevo enlace de verificación.</p>
                            )}
                        </div>
                    )}

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
