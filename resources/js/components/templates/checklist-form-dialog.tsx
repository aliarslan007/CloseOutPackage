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
import { Checkbox } from '@/components/Checkbox';
import { FormField } from '@/components/shared/form-field';
import { Input } from '@/components/ui/input';
import { TemplateChecklistItem } from '@/components/templates/template-types';

interface ChecklistForm {
    label: string;
    has_explain_field: boolean;
    allows_na: boolean;
    sort_order: number;
}

export interface ChecklistFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    templateId: number;
    item?: TemplateChecklistItem | null;
    initialSortOrder?: number;
}

export function ChecklistFormDialog({
    open,
    onOpenChange,
    templateId,
    item = null,
    initialSortOrder = 0,
}: Readonly<ChecklistFormDialogProps>) {
    const isEditing = item !== null;

    const form = useForm<ChecklistForm>({
        label: item?.label ?? '',
        // New questions ship with notes enabled; uncheck to hide the field.
        has_explain_field: item?.has_explain_field ?? true,
        allows_na: item?.allows_na ?? false,
        sort_order: item?.sort_order ?? initialSortOrder,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing && item) {
            form.put(route('admin.templates.checklist.update', [templateId, item.id]), options);
        } else {
            form.post(route('admin.templates.checklist.store', templateId), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Question' : 'Add Question'}</DialogTitle>
                    <DialogDescription>
                        Questions appear as YES/NO checks the field tech answers before sign-off.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} noValidate className="space-y-4 py-2">
                    <FormField label="Question" htmlFor="checklist-label" required error={form.errors.label}>
                        <Input
                            id="checklist-label"
                            value={form.data.label}
                            onChange={(e) => form.setData('label', e.target.value)}
                            placeholder="e.g. Were all penetrations properly sealed?"
                            maxLength={1000}
                            required
                            error={Boolean(form.errors.label)}
                        />
                    </FormField>

                    <FormField
                        label="Answer notes"
                        helper="When enabled, technicians and managers can add an optional note to any Yes or No answer."
                    >
                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                            <Checkbox
                                checked={form.data.has_explain_field}
                                onChange={(e) => form.setData('has_explain_field', e.target.checked)}
                            />
                            <span>Enable notes field for this question</span>
                        </label>
                    </FormField>

                    <FormField label="Answer Options">
                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                            <Checkbox
                                checked={form.data.allows_na}
                                onChange={(e) => form.setData('allows_na', e.target.checked)}
                            />
                            <span>Allow &quot;N/A&quot; (Not Applicable) option</span>
                        </label>
                    </FormField>

                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {isEditing ? 'Save Changes' : 'Add Item'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
