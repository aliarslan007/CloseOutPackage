import { ProfilePhotoInput } from '@/components/users/profile-photo-input';
import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { splitName } from '@/lib/user-name';
import { PageProps } from '@/types/inertia';
import { useForm, usePage } from '@inertiajs/react';
import * as React from 'react';

export interface ProfileFormValues {
    first_name: string;
    last_name: string;
    email: string;
    company: string;
    phone: string;
    profile_photo: File | null;
}

export function UpdateProfileInformationForm() {
    const user = usePage<PageProps>().props.auth.user;
    const [firstName, lastName] = splitName(user?.name ?? '');

    const form = useForm<ProfileFormValues>({
        first_name: firstName,
        last_name: lastName,
        email: user?.email ?? '',
        company: user?.company ?? '',
        phone: user?.phone ?? '',
        profile_photo: null,
    });

    const setString =
        (key: keyof ProfileFormValues) =>
        (e: React.ChangeEvent<HTMLInputElement>) =>
            form.setData(key, e.target.value);

    const displayInitial = (form.data.first_name || form.data.last_name || 'U').charAt(0).toUpperCase();

    // Method spoofing: PHP only populates $_POST/$_FILES for POST requests,
    // so a PATCH with multipart/form-data (profile photo) would lose every
    // field. Sending _method=patch keeps update semantics while letting PHP
    // read the payload — same pattern as the admin user edit form.
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            _method: 'patch',
            first_name: data.first_name,
            last_name: data.last_name,
            email: data.email,
            company: data.company,
            phone: data.phone,
            profile_photo: data.profile_photo,
        }));
        form.post(route('profile.update'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            <FormField label="Profile Picture" error={form.errors.profile_photo}>
                <ProfilePhotoInput
                    photoUrl={user?.profile_photo_url}
                    fileName="profile_photo"
                    initial={displayInitial}
                    onFileChange={(file) => form.setData('profile_photo', file)}
                />
            </FormField>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="First Name" htmlFor="first_name" required error={form.errors.first_name}>
                    <Input
                        id="first_name"
                        name="first_name"
                        value={form.data.first_name}
                        onChange={setString('first_name')}
                        maxLength={255}
                        required
                        error={Boolean(form.errors.first_name)}
                    />
                </FormField>
                <FormField label="Last Name" htmlFor="last_name" required error={form.errors.last_name}>
                    <Input
                        id="last_name"
                        name="last_name"
                        value={form.data.last_name}
                        onChange={setString('last_name')}
                        maxLength={255}
                        required
                        error={Boolean(form.errors.last_name)}
                    />
                </FormField>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Company" htmlFor="company" error={form.errors.company}>
                    <Input
                        id="company"
                        name="company"
                        value={form.data.company}
                        onChange={setString('company')}
                        placeholder="e.g. Verizon Wireless"
                        maxLength={50}
                        error={Boolean(form.errors.company)}
                    />
                </FormField>
                <FormField label="Phone" htmlFor="phone" error={form.errors.phone}>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        value={form.data.phone}
                        onChange={setString('phone')}
                        placeholder="(123) 456-7890"
                        maxLength={50}
                        pattern="(\+\d{1,2}\s?)?(\(\d{3}\)|\d{3})[\s.\-]?\d{3}[\s.\-]?\d{4}"
                        title="Phone format: (123) 456-7890 or +1 (123) 456-7890"
                        error={Boolean(form.errors.phone)}
                    />
                </FormField>
            </div>

            <FormField label="Email" htmlFor="email" required error={form.errors.email}>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    value={form.data.email}
                    onChange={setString('email')}
                    placeholder="user@company.com"
                    maxLength={255}
                    required
                    error={Boolean(form.errors.email)}
                />
            </FormField>

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" loading={form.processing}>
                    Save Changes
                </Button>
                {form.recentlySuccessful && (
                    <span className="text-sm text-emerald-600 dark:text-emerald-400">Saved.</span>
                )}
            </div>
        </form>
    );
}

export default UpdateProfileInformationForm;
