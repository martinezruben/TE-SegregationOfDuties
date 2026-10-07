import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';

import { FormField } from '@/Components/app/FormField';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

export interface TwoFactorState {
    enabled: boolean;
    required: boolean;
    pending: boolean;
    qrCode: string | null;
    secret: string | null;
    recoveryCodes: string[] | null;
}

export default function TwoFactorForm({ state }: { state: TwoFactorState }) {
    const enable = useForm({});
    const confirm = useForm({ code: '' });
    const disable = useForm({ password: '' });
    const regenerate = useForm({ password: '' });

    const submitEnable: FormEventHandler = (e) => {
        e.preventDefault();
        enable.post(route('two-factor.enable'), { preserveScroll: true });
    };

    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirm.post(route('two-factor.confirm'), { preserveScroll: true, onSuccess: () => confirm.reset() });
    };

    const submitDisable: FormEventHandler = (e) => {
        e.preventDefault();
        disable.delete(route('two-factor.disable'), { preserveScroll: true, onFinish: () => disable.reset() });
    };

    const submitRegenerate: FormEventHandler = (e) => {
        e.preventDefault();
        regenerate.post(route('two-factor.recovery-codes'), { preserveScroll: true, onFinish: () => regenerate.reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Autenticación en dos pasos
                    {state.enabled && <Badge variant="success">Activa</Badge>}
                </CardTitle>
                <CardDescription>
                    Protege tu cuenta con un código temporal (TOTP) de una app como Google Authenticator, Authy o 1Password.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {state.required && !state.enabled && (
                    <Alert variant="warning">
                        <AlertDescription>
                            Tu rol requiere autenticación en dos pasos. Debes activarla para usar el resto del sistema.
                        </AlertDescription>
                    </Alert>
                )}

                {state.recoveryCodes && state.recoveryCodes.length > 0 && (
                    <div className="rounded-md border bg-muted/40 p-4">
                        <p className="text-sm font-medium">
                            Guarda estos códigos de recuperación en un lugar seguro. Cada uno sirve una sola vez y no volverán a
                            mostrarse.
                        </p>
                        <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">
                            {state.recoveryCodes.map((c) => (
                                <li key={c}>{c}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {!state.enabled && !state.pending && (
                    <form onSubmit={submitEnable}>
                        <Button type="submit" disabled={enable.processing}>
                            Activar 2FA
                        </Button>
                    </form>
                )}

                {state.pending && state.qrCode && (
                    <form onSubmit={submitConfirm} className="max-w-sm space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Escanea el código QR con tu app autenticadora e ingresa el código de 6 dígitos para confirmar.
                        </p>
                        {/* El QR siempre es oscuro sobre fondo blanco para que lo lea cualquier cámara, también en tema oscuro. */}
                        <div
                            className="inline-block rounded border bg-white p-2"
                            aria-label="Código QR para configurar 2FA"
                            dangerouslySetInnerHTML={{ __html: state.qrCode }}
                        />
                        {state.secret && (
                            <p className="text-xs text-muted-foreground">
                                ¿No puedes escanear? Clave manual: <span className="font-mono">{state.secret}</span>
                            </p>
                        )}
                        <FormField label="Código" htmlFor="two_factor_code" error={confirm.errors.code}>
                            <Input
                                id="two_factor_code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                value={confirm.data.code}
                                onChange={(e) => confirm.setData('code', e.target.value)}
                            />
                        </FormField>
                        <Button type="submit" disabled={confirm.processing}>
                            Confirmar y activar
                        </Button>
                    </form>
                )}

                {state.enabled && (
                    <div className="space-y-6">
                        <form onSubmit={submitRegenerate} className="max-w-sm space-y-3">
                            <FormField
                                label="Contraseña actual (para regenerar los códigos de recuperación)"
                                htmlFor="regen_password"
                                error={regenerate.errors.password}
                            >
                                <Input
                                    id="regen_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={regenerate.data.password}
                                    onChange={(e) => regenerate.setData('password', e.target.value)}
                                />
                            </FormField>
                            <Button type="submit" variant="outline" disabled={regenerate.processing}>
                                Regenerar códigos de recuperación
                            </Button>
                        </form>

                        {!state.required && (
                            <form onSubmit={submitDisable} className="max-w-sm space-y-3">
                                <FormField
                                    label="Contraseña actual (para desactivar 2FA)"
                                    htmlFor="disable_password"
                                    error={disable.errors.password}
                                >
                                    <Input
                                        id="disable_password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={disable.data.password}
                                        onChange={(e) => disable.setData('password', e.target.value)}
                                    />
                                </FormField>
                                <Button type="submit" variant="destructive" disabled={disable.processing}>
                                    Desactivar 2FA
                                </Button>
                            </form>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
