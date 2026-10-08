import { Checkbox } from '@/components/Checkbox';
import { FormField } from '@/components/shared/form-field';
import { PasswordInput } from '@/components/shared/password-input';
import { Input } from '@/components/ui/input';
import { ProfilePhotoInput } from '@/components/users/profile-photo-input';
import { useForm } from '@inertiajs/react';
import * as React from 'react';

export interface UserFormValues {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    is_active: boolean;
    company: string;
    phone: string;
    profile_photo: File | null;
}

export const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    manager: 'Manager',
    field_supervisor: 'Field Technician',
    customer: 'Customer',
};

export interface UserFormFieldsProps {
    form: ReturnType<typeof useForm<UserFormValues>>;
    roles: string[];
    includePassword?: boolean;
    profilePhotoUrl?: string | null;
}

export function UserFormFields({
    form,
    roles,
    includePassword = false,
    profilePhotoUrl = null,
}: Readonly<UserFormFieldsProps>) {
    const setString =
        (key: keyof UserFormValues) =>
            (e: React.ChangeEvent<HTMLInputElement>) =>
                form.setData(key, e.target.value);

    const displayInitial = (form.data.first_name || form.data.last_name || 'U').charAt(0).toUpperCase();

    return (
        <div className="space-y-4">
            <FormField label="Profile Picture" error={form.errors.profile_photo}>
                <ProfilePhotoInput
                    photoUrl={profilePhotoUrl}
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

            {includePassword && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormField label="Password" htmlFor="password" required error={form.errors.password}>
                        <PasswordInput
                            id="password"
                            name="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={setString('password')}
                            minLength={8}
                            required
                            error={Boolean(form.errors.password)}
                        />
                    </FormField>
                    <FormField
                        label="Confirm Password"
                        htmlFor="password_confirmation"
                        required
                        error={form.errors.password_confirmation}
                    >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={setString('password_confirmation')}
                            minLength={8}
                            required
                            error={Boolean(form.errors.password_confirmation)}
                        />
                    </FormField>
                </div>
            )}

            <FormField label="Role" htmlFor="role" required error={form.errors.role}>
                {/* Customers are portal accounts managed under App Settings →
                    Customer Account Settings; a legacy customer user is locked here. */}
                <select
                    id="role"
                    name="role"
                    value={form.data.role}
                    onChange={(e) => form.setData('role', e.target.value)}
                    className="h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                    required
                    disabled={!roles.includes(form.data.role)}
                >
                    {!roles.includes(form.data.role) && (
                        <option value={form.data.role}>{ROLE_LABELS[form.data.role] ?? form.data.role}</option>
                    )}
                    {roles.map((role) => (
                        <option key={role} value={role}>
                            {ROLE_LABELS[role] ?? role}
                        </option>
                    ))}
                </select>
                {!roles.includes(form.data.role) && (
                    <p className="pt-1 text-xs text-muted-foreground">
                        Customer accounts are managed in App Settings under Customer Account Settings.
                    </p>
                )}
            </FormField>

            <FormField label="Active">
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                    <Checkbox
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                    />
                    <span>User can sign in</span>
                </label>
            </FormField>
        </div>
    );
}
