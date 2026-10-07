import { FormEventHandler, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';

export default function DeleteUserForm() {
    const [open, setOpen] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

    const close = () => {
        setOpen(false);
        clearErrors();
        reset();
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: close,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <Card className="border-destructive/40">
            <CardHeader>
                <CardTitle>Eliminar cuenta</CardTitle>
                <CardDescription>
                    Al eliminar tu cuenta se borran todos sus recursos y datos de forma permanente. Descarga antes
                    cualquier información que quieras conservar.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button variant="destructive" onClick={() => setOpen(true)}>
                    Eliminar cuenta
                </Button>
            </CardContent>

            <Dialog open={open} onOpenChange={(o) => (o ? setOpen(true) : close())}>
                <DialogContent>
                    <form onSubmit={deleteUser} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>¿Seguro que quieres eliminar tu cuenta?</DialogTitle>
                            <DialogDescription>
                                Esta acción es permanente. Ingresa tu contraseña para confirmar.
                            </DialogDescription>
                        </DialogHeader>

                        <FormField label="Contraseña" htmlFor="delete_password" error={errors.password}>
                            <Input
                                id="delete_password"
                                ref={passwordInput}
                                type="password"
                                value={data.password}
                                autoComplete="current-password"
                                autoFocus
                                onChange={(e) => setData('password', e.target.value)}
                            />
                        </FormField>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={close}>
                                Cancelar
                            </Button>
                            <Button type="submit" variant="destructive" disabled={processing}>
                                Eliminar cuenta
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
