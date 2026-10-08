import { Checkbox } from '@/components/Checkbox';
import { FormField } from '@/components/shared/form-field';
import { PasswordInput } from '@/components/shared/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ProfilePhotoInput } from '@/components/users/profile-photo-input';
import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import type { CustomerItem, EngineerItem, RegionItem } from './customer-hierarchy-types';
import { initialEngineerValues, type EngineerForm } from './engineer-form-values';

const SELECT_CLASS =
    'h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

export interface EngineerFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: CustomerItem | null;
    regions: RegionItem[];
    engineer?: EngineerItem | null;
    defaultMarketId?: number | null;
}

export function EngineerFormDialog({
    open,
    onOpenChange,
    customer,
    regions,
    engineer = null,
    defaultMarketId = null,
}: Readonly<EngineerFormDialogProps>) {
    const isEditing = engineer !== null;

    const form = useForm<EngineerForm>(initialEngineerValues(customer, engineer, defaultMarketId));

    useEffect(() => {
        if (!open) return;

        // Deps are ids, not objects: a props refresh that targets the same
        // customer/region must not wipe a half-typed engineer.
        form.setData(initialEngineerValues(customer, engineer, defaultMarketId));
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, customer?.id, engineer?.hash_id, defaultMarketId]);

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        // Always re-register: transform lingers across dialog open/close.
        // Edit needs POST + _method=put (PHP only fills $_POST/$_FILES for POST);
        // create clears _method so a prior edit transform can't leak.
        form.transform((data) => ({ ...data, _method: isEditing ? 'put' : undefined }));
        form.post(
            isEditing && engineer
                ? route('admin.settings.customer-engineers.update', engineer.hash_id)
                : route('admin.settings.customer-engineers.store'),
            options
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto no-scrollbar sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Customer Engineer' : 'Add Customer Engineer'}</DialogTitle>
                    <DialogDescription>
                        {customer ? `${customer.name} — ` : ''}a region is required so the engineer is anchored to
                        their territory. Their email and password become their Customer Portal login.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} noValidate className="space-y-4 py-2">
                    <FormField label="Profile Picture" error={form.errors.profile_photo}>
                        <ProfilePhotoInput
                            photoUrl={engineer?.profile_photo_url ?? null}
                            fileName="profile_photo"
                            initial={
                                (form.data.first_name || form.data.last_name || 'E')
                                    .charAt(0)
                                    .toUpperCase()
                            }
                            onFileChange={(file) => form.setData('profile_photo', file)}
                        />
                    </FormField>

                    <FormField
                        label="Region / Market"
                        htmlFor="engineer-market"
                        required
                        error={form.errors.market_id}
                    >
                        <select
                            id="engineer-market"
                            value={form.data.market_id}
                            onChange={(event) =>
                                form.setData(
                                    'market_id',
                                    event.target.value === '' ? '' : Number(event.target.value)
                                )
                            }
                            className={SELECT_CLASS}
                            required
                        >
                            <option value="">Select a region…</option>
                            {regions.map((region) => (
                                <option key={region.id} value={region.id}>
                                    {region.name}
                                </option>
                            ))}
                        </select>
                    </FormField>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="First Name" htmlFor="engineer-first-name" required error={form.errors.first_name}>
                            <Input
                                id="engineer-first-name"
                                value={form.data.first_name}
                                onChange={(event) => form.setData('first_name', event.target.value)}
                                placeholder="e.g. Jane"
                                maxLength={255}
                                required
                                error={Boolean(form.errors.first_name)}
                            />
                        </FormField>

                        <FormField label="Last Name" htmlFor="engineer-last-name" required error={form.errors.last_name}>
                            <Input
                                id="engineer-last-name"
                                value={form.data.last_name}
                                onChange={(event) => form.setData('last_name', event.target.value)}
                                placeholder="e.g. Smith"
                                maxLength={255}
                                required
                                error={Boolean(form.errors.last_name)}
                            />
                        </FormField>

                        <FormField label="Company" htmlFor="engineer-company" error={form.errors.company}>
                            <Input
                                id="engineer-company"
                                value={form.data.company}
                                onChange={(event) => form.setData('company', event.target.value)}
                                placeholder={customer?.name ?? 'e.g. Verizon'}
                                maxLength={255}
                                error={Boolean(form.errors.company)}
                            />
                        </FormField>

                        <FormField label="Job Title" htmlFor="engineer-title" error={form.errors.title}>
                            <Input
                                id="engineer-title"
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                placeholder="e.g. Lead RF Engineer"
                                maxLength={100}
                                error={Boolean(form.errors.title)}
                            />
                        </FormField>

                        <FormField label="Email" htmlFor="engineer-email" required error={form.errors.email}>
                            <Input
                                id="engineer-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                placeholder="e.g. jane.smith@verizon.com"
                                maxLength={255}
                                required
                                error={Boolean(form.errors.email)}
                            />
                        </FormField>

                        <FormField label="Phone" htmlFor="engineer-phone" error={form.errors.phone}>
                            <Input
                                id="engineer-phone"
                                type="tel"
                                value={form.data.phone}
                                onChange={(event) => form.setData('phone', event.target.value)}
                                placeholder="(555) 123-4567"
                                maxLength={50}
                                error={Boolean(form.errors.phone)}
                            />
                        </FormField>
                    </div>

                    <FormField
                        label={isEditing ? 'New Password' : 'Password'}
                        htmlFor="engineer-password"
                        required={!isEditing}
                        error={form.errors.password}
                        helper={isEditing ? 'Leave blank to keep the current password.' : undefined}
                    >
                        <PasswordInput
                            id="engineer-password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            placeholder={isEditing ? '••••••••' : 'Min. 8 characters'}
                            minLength={isEditing ? undefined : 8}
                            required={!isEditing}
                            error={Boolean(form.errors.password)}
                        />
                    </FormField>

                    <FormField
                        label="Confirm Password"
                        htmlFor="engineer-password-confirmation"
                        required={!isEditing}
                        error={form.errors.password_confirmation}
                    >
                        <PasswordInput
                            id="engineer-password-confirmation"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            required={!isEditing}
                            error={Boolean(form.errors.password_confirmation)}
                        />
                    </FormField>

                    <FormField label="Role">
                        <select value="customer" disabled className={`${SELECT_CLASS} cursor-not-allowed opacity-70`}>
                            <option value="customer">Customer — Customer Portal access</option>
                        </select>
                    </FormField>

                    <div className="space-y-2">
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_active}
                                onChange={(event) => form.setData('is_active', event.target.checked)}
                            />
                            <span>Active — can sign in to the Customer Portal</span>
                        </label>
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_default}
                                onChange={(event) => form.setData('is_default', event.target.checked)}
                            />
                            <span>Set as the default engineer for this region</span>
                        </label>
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {isEditing ? 'Save Changes' : 'Add Engineer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
