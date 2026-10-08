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
import * as React from 'react';
import { toast } from 'sonner';
import { sowChecklistApi } from '../../api/sow-checklist.api';
import type { SowChecklistEntry } from '../../types/sow.types';
import { sowErrorMessage } from '../../utils/sow-error';

export interface SowChecklistItemDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    reportId: number;
    item?: SowChecklistEntry | null;
    onSuccess: () => void;
}

export function SowChecklistItemDialog({
    open,
    onOpenChange,
    reportId,
    item = null,
    onSuccess,
}: Readonly<SowChecklistItemDialogProps>) {
    const isEditing = item !== null;
    const [label, setLabel] = React.useState('');
    const [hasExplainField, setHasExplainField] = React.useState(false);
    const [isRequired, setIsRequired] = React.useState(true);
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        if (open) {
            setLabel(item?.label ?? '');
            setHasExplainField(item?.has_explain_field ?? false);
            setIsRequired(item?.is_required ?? true);
            setError(null);
        }
    }, [open, item]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const trimmed = label.trim();
        if (!trimmed) {
            setError('Question text is required.');
            return;
        }

        setIsSubmitting(true);
        setError(null);

        try {
            if (isEditing && item) {
                await sowChecklistApi.updateItem(reportId, item.id, {
                    label: trimmed,
                    has_explain_field: hasExplainField,
                    is_required: isRequired,
                });
                toast.success('Survey question updated.');
            } else {
                await sowChecklistApi.createItem(reportId, {
                    label: trimmed,
                    has_explain_field: hasExplainField,
                    is_required: isRequired,
                });
                toast.success('Survey question added.');
            }

            onOpenChange(false);
            onSuccess();
        } catch (err) {
            const message = sowErrorMessage(err, 'Failed to save the survey question.');
            setError(message);
            toast.error(message);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit Survey Question' : 'Add Survey Question'}
                    </DialogTitle>
                    <DialogDescription>
                        Define a question to verify during the field survey.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} noValidate className="space-y-4 py-2">
                    <FormField
                        label="Question / Prompt"
                        htmlFor="sow-checklist-label"
                        required
                        error={error ?? undefined}
                    >
                        <Input
                            id="sow-checklist-label"
                            value={label}
                            onChange={(e) => {
                                setLabel(e.target.value);
                                if (error) setError(null);
                            }}
                            placeholder="e.g. Was the fiber demarcation clearly identified and labeled?"
                            maxLength={1000}
                            required
                            autoFocus
                        />
                    </FormField>

                    <div className="space-y-2 rounded-lg border border-border/70 bg-muted/20 p-3">
                        <label className="flex items-center gap-2 text-xs font-medium cursor-pointer text-foreground">
                            <Checkbox
                                checked={hasExplainField}
                                onChange={(e) => setHasExplainField(e.target.checked)}
                            />
                            <span>Require explanation when answered &quot;No&quot;</span>
                        </label>
                        <p className="text-[11px] text-muted-foreground ml-6">
                            If checked, field techs must write an explanation if they answer No.
                        </p>

                        <label className="flex items-center gap-2 text-xs font-medium cursor-pointer text-foreground pt-1">
                            <Checkbox
                                checked={isRequired}
                                onChange={(e) => setIsRequired(e.target.checked)}
                            />
                            <span>Mandatory question (required before approval)</span>
                        </label>
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" loading={isSubmitting}>
                            {isEditing ? 'Save Changes' : 'Add Question'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
