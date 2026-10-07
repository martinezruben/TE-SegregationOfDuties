import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('two-factor.challenge'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    const toggle = () => {
        setUseRecovery((v) => !v);
        reset('code', 'recovery_code');
    };

    return (
        <GuestLayout>
            <Head title="Verificación en dos pasos" />

            <div className="mb-4 text-sm text-gray-600">
                {useRecovery
                    ? 'Ingresa uno de tus códigos de recuperación de emergencia.'
                    : 'Ingresa el código de 6 dígitos de tu aplicación autenticadora.'}
            </div>

            <form onSubmit={submit}>
                {useRecovery ? (
                    <div>
                        <InputLabel
                            htmlFor="recovery_code"
                            value="Código de recuperación"
                        />
                        <TextInput
                            id="recovery_code"
                            value={data.recovery_code}
                            className="mt-1 block w-full"
                            autoComplete="one-time-code"
                            isFocused={true}
                            onChange={(e) =>
                                setData('recovery_code', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.recovery_code}
                            className="mt-2"
                        />
                    </div>
                ) : (
                    <div>
                        <InputLabel htmlFor="code" value="Código" />
                        <TextInput
                            id="code"
                            inputMode="numeric"
                            value={data.code}
                            className="mt-1 block w-full"
                            autoComplete="one-time-code"
                            isFocused={true}
                            onChange={(e) => setData('code', e.target.value)}
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>
                )}

                <div className="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={toggle}
                        className="text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        {useRecovery
                            ? 'Usar código de la app'
                            : 'Usar código de recuperación'}
                    </button>

                    <div className="flex items-center gap-4">
                        <Link
                            href={route('login')}
                            className="text-sm text-gray-600 underline hover:text-gray-900"
                        >
                            Cancelar
                        </Link>
                        <PrimaryButton disabled={processing}>
                            Verificar
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </GuestLayout>
    );
}
