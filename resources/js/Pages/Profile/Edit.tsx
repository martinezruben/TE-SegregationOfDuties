import { Head } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import DeleteUserForm from './Partials/DeleteUserForm';
import TwoFactorForm, { TwoFactorState } from './Partials/TwoFactorForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    twoFactor,
}: PageProps<{
    mustVerifyEmail: boolean;
    status?: string;
    twoFactor: TwoFactorState;
}>) {
    return (
        <AppLayout title="Mi perfil">
            <Head title="Mi perfil" />

            <div className="mx-auto max-w-3xl space-y-6">
                <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                <UpdatePasswordForm />
                <TwoFactorForm state={twoFactor} />
                <DeleteUserForm />
            </div>
        </AppLayout>
    );
}
