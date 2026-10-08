import * as React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { UserFormFields, UserFormValues } from '@/components/users/user-form-fields';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { PageProps } from '@/types/inertia';

export type AdminUserCreatePageProps = PageProps<{
    roles: string[];
}>;

export function AdminUserCreate() {
    useFlashToasts();

    const { roles } = usePage<AdminUserCreatePageProps>().props;

    const form = useForm<UserFormValues>({
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles[0] ?? 'manager',
        is_active: true,
        company: '',
        phone: '',
        profile_photo: null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('admin.users.store'));
    };

    return (
            <AppLayout title="Create User" breadcrumb={[{ label: 'Users', href: route('admin.users.index') }, { label: 'Create' }]}>
            <Head title="Create User" />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Create User</CardTitle>
                        <CardDescription>
                            The account is created locally and synced with the SSO provider.
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent>
                            <UserFormFields form={form} roles={roles} includePassword />
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.users.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Create User
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default AdminUserCreate;
