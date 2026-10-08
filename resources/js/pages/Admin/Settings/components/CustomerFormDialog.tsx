import { Checkbox } from '@/components/Checkbox';
import { FormField } from '@/components/shared/form-field';
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
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import type { CustomerItem } from './customer-hierarchy-types';

/** Mirrors the backend `max:500` rule on `customers.description`. */
const DESCRIPTION_LIMIT = 500;

interface CustomerForm {
    name: string;
    code: string;
    description: string;
    is_active: boolean;
}

/**
 * The values this dialog should always open with. Applied on mount and again
 * on every open — the dialog stays mounted between openings, so without the
 * re-seed a cancelled edit would linger into the next customer.
 */
function initialCustomerValues(customer: CustomerItem | null): CustomerForm {
    return {
        name: customer?.name ?? '',
        code: customer?.code ?? '',
        description: customer?.description ?? '',
        is_active: customer?.is_active ?? true,
    };
}

export interface CustomerFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer?: CustomerItem | null;
}

export function CustomerFormDialog({
    open,
    onOpenChange,
    customer = null,
}: Readonly<CustomerFormDialogProps>) {
    const isEditing = customer !== null;

    const form = useForm<CustomerForm>(initialCustomerValues(customer));

    useEffect(() => {
        if (!open) return;

        // Deps are ids, not objects: a props refresh for the same customer
        // must not wipe a half-typed form.
        form.setData(initialCustomerValues(customer));
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, customer?.hash_id]);

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (isEditing && customer) {
            form.put(route('admin.settings.customers.update', customer.hash_id), options);
        } else {
            form.post(route('admin.settings.customers.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Customer' : 'New Customer'}</DialogTitle>
                    <DialogDescription>
                        The client organisation the work is carried out for, e.g. Verizon or AT&amp;T.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} noValidate className="space-y-4 py-2">
                    <FormField label="Customer Name" htmlFor="customer-name" required error={form.errors.name}>
                        <Input
                            id="customer-name"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            placeholder="e.g. Verizon"
                            maxLength={100}
                            required
                            error={Boolean(form.errors.name)}
                        />
                    </FormField>

                    <FormField
                        label="Short Code"
                        htmlFor="customer-code"
                        required
                        error={form.errors.code}
                        helper="Used on reports and labels, e.g. VZW. Saved in capitals."
                    >
                        <Input
                            id="customer-code"
                            value={form.data.code}
                            onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                            placeholder="e.g. VZW"
                            maxLength={20}
                            required
                            error={Boolean(form.errors.code)}
                        />
                    </FormField>

                    <FormField
                        label="Description"
                        htmlFor="customer-description"
                        error={form.errors.description}
                        helper="Internal note — not shown to the customer."
                        maxLength={DESCRIPTION_LIMIT}
                        valueLength={form.data.description.length}
                    >
                        <Textarea
                            id="customer-description"
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            placeholder="Optional notes about this customer"
                            maxLength={DESCRIPTION_LIMIT}
                            className="min-h-[70px] text-sm"
                        />
                    </FormField>

                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_active}
                            onChange={(event) => form.setData('is_active', event.target.checked)}
                        />
                        <span>Active — available when creating projects</span>
                    </label>

                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {isEditing ? 'Save Changes' : 'Create Customer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
