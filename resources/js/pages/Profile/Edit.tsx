import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export function Edit() {
    useFlashToasts();

    return (
        <AppLayout title="My Profile" breadcrumb="Profile">
            <Head title="Profile" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-bold">
                                Profile Information
                            </CardTitle>
                            <CardDescription>
                                Update your personal details, company, phone and
                                email address.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <UpdateProfileInformationForm />
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-bold">
                                Update Password
                            </CardTitle>
                            <CardDescription>
                                Keep your account secure with a strong password.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <UpdatePasswordForm />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0 py-4">
                            <div className="space-y-1">
                                <CardTitle className="text-sm font-bold text-destructive">
                                    Delete Account
                                </CardTitle>
                                <CardDescription>
                                    Permanently delete your account and all of its data.
                                </CardDescription>
                            </div>
                            <DeleteUserForm className="shrink-0" />
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

export default Edit;
