import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormField } from '@/components/shared/form-field';
import { PasswordInput } from '@/components/shared/password-input';

export interface ChangePasswordDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    userId: number;
    userName: string;
}

interface PasswordForm {
    password: string;
    password_confirmation: string;
}

export function ChangePasswordDialog({
    open,
    onOpenChange,
    userId,
    userName,
}: Readonly<ChangePasswordDialogProps>) {
    const form = useForm<PasswordForm>({
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('admin.users.change-password', userId), {
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Change Password</DialogTitle>
                    <DialogDescription>
                        Set a new password for {userName}. The change syncs with the SSO provider.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} noValidate className="space-y-4 py-2">
                    <FormField
                        label="New Password"
                        htmlFor="password"
                        required
                        error={form.errors.password}
                    >
                        <PasswordInput
                            id="password"
                            name="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
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
                            onChange={(e) =>
                                form.setData('password_confirmation', e.target.value)
                            }
                            minLength={8}
                            required
                            error={Boolean(form.errors.password_confirmation)}
                        />
                    </FormField>

                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            Update Password
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
