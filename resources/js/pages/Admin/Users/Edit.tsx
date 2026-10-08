import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { UserFormFields, UserFormValues } from '@/components/users/user-form-fields';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { splitName } from '@/lib/user-name';
import { PageProps } from '@/types/inertia';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import * as React from 'react';

export interface EditUserProp {
    id: number;
    hash_id?: string;
    name: string;
    email: string;
    company?: string | null;
    phone?: string | null;
    profile_photo_url?: string | null;
    is_active?: boolean;
    roles?: Array<{ id: number; name: string }>;
    markets?: Array<{ id: number; name: string }>;
}

export type AdminUserEditPageProps = PageProps<{
    user: EditUserProp;
    roles: string[];
    markets: Array<{ id: number; name: string }>;
}>;

export function AdminUserEdit() {
    useFlashToasts();

    const { user, roles } = usePage<AdminUserEditPageProps>().props;
    const [firstName, lastName] = splitName(user.name);
    const currentRole = user.roles?.[0]?.name ?? roles[0] ?? 'manager';

    const form = useForm<UserFormValues>({
        first_name: firstName,
        last_name: lastName,
        email: user.email,
        password: '',
        password_confirmation: '',
        role: currentRole,
        is_active: user.is_active ?? true,
        company: user.company ?? '',
        phone: user.phone ?? '',
        profile_photo: null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            // Method spoofing: PHP only populates $_POST/$_FILES for POST requests,
            // so a PUT with multipart/form-data (profile photo) would lose every
            // field. Sending _method=put keeps update semantics while letting PHP
            // read the payload.
            _method: 'put',
            first_name: data.first_name,
            last_name: data.last_name,
            email: data.email,
            role: data.role,
            is_active: data.is_active,
            company: data.company,
            phone: data.phone,
            profile_photo: data.profile_photo,
        }));
        form.post(route('admin.users.update', user.hash_id || user.id));
    };

    return (
        <AppLayout
            title="Edit User"
            breadcrumb={[{ label: 'Users', href: route('admin.users.index') }, { label: 'Edit' }]}
        >
            <Head title={`Edit ${user.name}`} />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Edit User Details</CardTitle>
                        <CardDescription>
                            Update profile details and role. Password changes are managed from the
                            user list.
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent>
                            <UserFormFields
                                form={form}
                                roles={roles}
                                profilePhotoUrl={user.profile_photo_url}
                            />
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.users.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Save Changes
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default AdminUserEdit;
