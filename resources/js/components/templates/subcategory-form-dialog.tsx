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
import * as React from 'react';

/**
 * A subcategory row as stored in the global library.
 */
export interface SubcategoryItem {
    id: number;
    name: string;
    description?: string | null;
    default_picture_count: number;
    is_required: boolean;
    is_active: boolean;
}

type Mode = 'library' | 'stage';

interface SubcategoryForm {
    name: string;
    description: string;
    default_picture_count: number;
    is_required: boolean;
    is_active: boolean;
    /** Stage-mode only; preset endpoints ignore the extra key. */
    sort_order: number;
}

export interface SubcategoryFormDialogProps {
    /** 'library' = global library CRUD (adds Active toggle). 'stage' = one template stage. */
    mode: Mode;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Library row being edited (library mode). */
    preset?: SubcategoryItem | null;
    /** Existing stage category being edited (stage mode). */
    category?: (Omit<SubcategoryItem, 'is_active'> & { sort_order?: number }) | null;
    templateId?: number;
    sectionId?: number;
    initialSortOrder?: number;
}

/**
 * The ONE dialog for creating/editing a subcategory, everywhere. The library
 * and the builder previously used two dialogs with different field names —
 * this unifies labels, defaults, and behavior.
 */
export function SubcategoryFormDialog({
    mode,
    open,
    onOpenChange,
    preset = null,
    category = null,
    templateId,
    sectionId,
    initialSortOrder = 0,
}: Readonly<SubcategoryFormDialogProps>) {
    const existing = mode === 'library' ? preset : category;
    const isEditing = existing !== null;

    const form = useForm<SubcategoryForm>({
        name: existing?.name ?? '',
        description: existing?.description ?? '',
        default_picture_count: existing?.default_picture_count ?? 1,
        // Library entries default to must-have; per-stage manual ones did not.
        is_required: existing?.is_required ?? mode === 'library',
        is_active: (mode === 'library' ? preset?.is_active : true) ?? true,
        sort_order: category?.sort_order ?? initialSortOrder,
    });

    const { setData, clearErrors } = form;

    React.useEffect(() => {
        if (open) {
            setData({
                name: existing?.name ?? '',
                description: existing?.description ?? '',
                default_picture_count: existing?.default_picture_count ?? 1,
                is_required: existing?.is_required ?? mode === 'library',
                is_active: (mode === 'library' ? preset?.is_active : true) ?? true,
                sort_order: category?.sort_order ?? initialSortOrder,
            });
            clearErrors();
        }
    }, [open, existing, mode, preset?.is_active, category?.sort_order, initialSortOrder, setData, clearErrors]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { onSuccess: () => onOpenChange(false) };

        if (mode === 'library') {
            if (isEditing && preset) {
                form.put(route('admin.templates.category-presets.update', preset.id), options);
            } else {
                form.post(route('admin.templates.category-presets.store'), options);
            }
            return;
        }

        // Stage mode
        if (isEditing && category && templateId && sectionId) {
            form.put(
                route('admin.templates.categories.update', [templateId, sectionId, category.id]),
                options
            );
        } else if (templateId && sectionId) {
            form.post(route('admin.templates.categories.store', [templateId, sectionId]), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Subcategory' : 'Add Subcategory'}</DialogTitle>
                    <DialogDescription>
                        {mode === 'library'
                            ? 'Saved to your library — reuse it in any template stage later.'
                            : 'Added to this stage only. Field users capture exactly what you define here.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} noValidate className="space-y-4 py-2">
                    <FormField
                        label="Subcategory Name"
                        htmlFor="subcategory-name"
                        required
                        helper={`${form.data.name.length}/100 characters`}
                        error={form.errors.name}
                    >
                        <Input
                            id="subcategory-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="e.g. Tower Foundation & Pier Detail"
                            maxLength={100}
                            required
                            error={Boolean(form.errors.name)}
                        />
                    </FormField>

                    <FormField
                        label="Photo Guidance"
                        htmlFor="subcategory-description"
                        helper={`What should the photo show? (${form.data.description.length}/500)`}
                        error={form.errors.description}
                    >
                        <Textarea
                            id="subcategory-description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="e.g. Close-up of the pier base before backfill..."
                            maxLength={500}
                            error={Boolean(form.errors.description)}
                        />
                    </FormField>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <FormField
                            label="How many photos"
                            htmlFor="subcategory-count"
                            required
                            helper="Field users capture this many by default."
                            error={form.errors.default_picture_count}
                        >
                            <Input
                                id="subcategory-count"
                                type="number"
                                min={1}
                                max={99}
                                value={form.data.default_picture_count}
                                onChange={(e) =>
                                    form.setData('default_picture_count', Number(e.target.value))
                                }
                                required
                                error={Boolean(form.errors.default_picture_count)}
                            />
                        </FormField>
                    </div>

                    <label className="flex items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-3 cursor-pointer">
                        <Checkbox
                            className="mt-0.5"
                            checked={form.data.is_required}
                            onChange={(e) => form.setData('is_required', e.target.checked)}
                        />
                        <span>
                            <span className="block text-sm font-medium">
                                Make this field mandatory
                            </span>
                            <span className="mt-0.5 block text-xs text-muted-foreground">
                                When checked, field technicians must capture photos for this
                                subcategory before they can complete the stage.
                            </span>
                        </span>
                    </label>

                    {mode === 'library' && (
                        <FormField label="Status">
                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                <Checkbox
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                />
                                <span>Active</span>
                            </label>
                            {!form.data.is_active && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Inactive stays saved in your library but hidden when building
                                    templates.
                                </p>
                            )}
                        </FormField>
                    )}

                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {isEditing ? 'Save Changes' : 'Add Subcategory'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
