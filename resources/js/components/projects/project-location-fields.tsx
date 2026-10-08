import { ProjectFormValues } from '@/components/projects/project-form-types';
import { FormField } from '@/components/shared/form-field';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/react';
import * as React from 'react';

export interface ProjectLocationFieldsProps {
    form: ReturnType<typeof useForm<ProjectFormValues>>;
}

export function ProjectLocationFields({ form }: Readonly<ProjectLocationFieldsProps>) {
    const setString = (key: keyof ProjectFormValues) => (e: React.ChangeEvent<HTMLInputElement>) =>
        form.setData(key, e.target.value);

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2">
                <FormField label="Address" htmlFor="address" error={form.errors.address} required>
                    <Input
                        id="address"
                        name="address"
                        value={form.data.address}
                        onChange={setString('address')}
                        maxLength={500}
                        required
                        error={Boolean(form.errors.address)}
                    />
                </FormField>
            </div>
            <FormField label="City" htmlFor="city" error={form.errors.city}>
                <Input
                    id="city"
                    name="city"
                    value={form.data.city}
                    onChange={setString('city')}
                    maxLength={120}
                    pattern="[A-Za-z\s\-]+"
                    title="City should contain only letters, spaces, and hyphens"
                    error={Boolean(form.errors.city)}
                />
            </FormField>
            <FormField label="State" htmlFor="state" error={form.errors.state}>
                <Input
                    id="state"
                    name="state"
                    value={form.data.state}
                    onChange={setString('state')}
                    maxLength={80}
                    pattern="[A-Za-z\s\-]+"
                    title="State should contain only letters, spaces, and hyphens"
                    error={Boolean(form.errors.state)}
                />
            </FormField>
            <FormField label="ZIP" htmlFor="zip" error={form.errors.zip}>
                <Input
                    id="zip"
                    name="zip"
                    value={form.data.zip}
                    onChange={setString('zip')}
                    maxLength={20}
                    pattern="\d{5}(-\d{4})?"
                    title="ZIP code should be 5 digits or 5+4 format (e.g. 12345 or 12345-6789)"
                    error={Boolean(form.errors.zip)}
                />
            </FormField>
        </div>
    );
}
