import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface TwoFactorState {
    enabled: boolean;
    required: boolean;
    pending: boolean;
    qrCode: string | null;
    secret: string | null;
    recoveryCodes: string[] | null;
}

export default function TwoFactorForm({
    state,
    className = '',
}: {
    state: TwoFactorState;
    className?: string;
}) {
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
        confirm.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirm.reset(),
        });
    };

    const submitDisable: FormEventHandler = (e) => {
        e.preventDefault();
        disable.delete(route('two-factor.disable'), {
            preserveScroll: true,
            onFinish: () => disable.reset(),
        });
    };

    const submitRegenerate: FormEventHandler = (e) => {
        e.preventDefault();
        regenerate.post(route('two-factor.recovery-codes'), {
            preserveScroll: true,
            onFinish: () => regenerate.reset(),
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Autenticación en dos pasos
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Protege tu cuenta con un código temporal (TOTP) generado por
                    una app como Google Authenticator, Authy o 1Password.
                </p>
            </header>

            {state.required && !state.enabled && (
                <div
                    role="alert"
                    className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800"
                >
                    Tu rol requiere autenticación en dos pasos. Debes activarla
                    para usar el resto del sistema.
                </div>
            )}

            {state.recoveryCodes && state.recoveryCodes.length > 0 && (
                <div className="mt-4 rounded-md border border-gray-300 bg-gray-50 p-4">
                    <p className="text-sm font-medium text-gray-900">
                        Guarda estos códigos de recuperación en un lugar seguro.
                        Cada uno sirve una sola vez y no volverán a mostrarse.
                    </p>
                    <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-sm text-gray-800">
                        {state.recoveryCodes.map((c) => (
                            <li key={c}>{c}</li>
                        ))}
                    </ul>
                </div>
            )}

            {!state.enabled && !state.pending && (
                <form onSubmit={submitEnable} className="mt-6">
                    <PrimaryButton disabled={enable.processing}>
                        Activar 2FA
                    </PrimaryButton>
                </form>
            )}

            {state.pending && state.qrCode && (
                <form onSubmit={submitConfirm} className="mt-6 max-w-sm space-y-4">
                    <p className="text-sm text-gray-600">
                        Escanea el código QR con tu app autenticadora e ingresa
                        el código de 6 dígitos para confirmar.
                    </p>
                    <div
                        className="inline-block rounded border bg-white p-2"
                        aria-label="Código QR para configurar 2FA"
                        dangerouslySetInnerHTML={{ __html: state.qrCode }}
                    />
                    {state.secret && (
                        <p className="text-xs text-gray-600">
                            ¿No puedes escanear? Clave manual:{' '}
                            <span className="font-mono">{state.secret}</span>
                        </p>
                    )}
                    <div>
                        <InputLabel htmlFor="two_factor_code" value="Código" />
                        <TextInput
                            id="two_factor_code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={confirm.data.code}
                            className="mt-1 block w-full"
                            onChange={(e) =>
                                confirm.setData('code', e.target.value)
                            }
                        />
                        <InputError
                            message={confirm.errors.code}
                            className="mt-2"
                        />
                    </div>
                    <PrimaryButton disabled={confirm.processing}>
                        Confirmar y activar
                    </PrimaryButton>
                </form>
            )}

            {state.enabled && (
                <div className="mt-6 space-y-6">
                    <p className="text-sm font-medium text-green-700">
                        La autenticación en dos pasos está activa.
                    </p>

                    <form onSubmit={submitRegenerate} className="max-w-sm space-y-2">
                        <InputLabel
                            htmlFor="regen_password"
                            value="Contraseña actual (para regenerar códigos de recuperación)"
                        />
                        <TextInput
                            id="regen_password"
                            type="password"
                            autoComplete="current-password"
                            value={regenerate.data.password}
                            className="block w-full"
                            onChange={(e) =>
                                regenerate.setData('password', e.target.value)
                            }
                        />
                        <InputError
                            message={regenerate.errors.password}
                            className="mt-2"
                        />
                        <SecondaryButton
                            type="submit"
                            disabled={regenerate.processing}
                        >
                            Regenerar códigos de recuperación
                        </SecondaryButton>
                    </form>

                    {!state.required && (
                        <form onSubmit={submitDisable} className="max-w-sm space-y-2">
                            <InputLabel
                                htmlFor="disable_password"
                                value="Contraseña actual (para desactivar 2FA)"
                            />
                            <TextInput
                                id="disable_password"
                                type="password"
                                autoComplete="current-password"
                                value={disable.data.password}
                                className="block w-full"
                                onChange={(e) =>
                                    disable.setData('password', e.target.value)
                                }
                            />
                            <InputError
                                message={disable.errors.password}
                                className="mt-2"
                            />
                            <DangerButton
                                type="submit"
                                disabled={disable.processing}
                            >
                                Desactivar 2FA
                            </DangerButton>
                        </form>
                    )}
                </div>
            )}
        </section>
    );
}
