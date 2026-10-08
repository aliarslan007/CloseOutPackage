import * as React from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { SubcategoryFormDialog } from '@/components/templates/subcategory-form-dialog';
import { TemplateCategoryItem } from '@/components/templates/template-types';
import { ChevronDown, ChevronUp, GripVertical, Pencil, Trash2 } from 'lucide-react';

export interface CategoryItemProps {
    category: TemplateCategoryItem;
    templateId: number;
    sectionId: number;
    canMoveUp: boolean;
    canMoveDown: boolean;
    onMoveUp: () => void;
    onMoveDown: () => void;
}

export function CategoryItem({
    category,
    templateId,
    sectionId,
    canMoveUp,
    canMoveDown,
    onMoveUp,
    onMoveDown,
}: Readonly<CategoryItemProps>) {
    const [editOpen, setEditOpen] = React.useState(false);
    const [deleteOpen, setDeleteOpen] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(
            route('admin.templates.categories.destroy', [templateId, sectionId, category.id]),
            {
                onSuccess: () => {
                    setDeleteOpen(false);
                    setIsDeleting(false);
                },
                onError: () => setIsDeleting(false),
            }
        );
    };

    return (
        <div className="flex items-start justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 p-3">
            <div className="flex items-start gap-3 min-w-0">
                <GripVertical className="size-4 text-muted-foreground shrink-0 mt-0.5" />
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <p className="text-xs font-bold text-foreground">{category.name}</p>
                        {category.is_required && (
                            <Badge variant="warning" className="text-[10px]">
                                Required
                            </Badge>
                        )}
                    </div>
                    {category.description && (
                        <p className="text-[11px] text-muted-foreground">
                            {category.description}
                        </p>
                    )}
                    <p className="text-[11px] text-muted-foreground">
                        {category.default_picture_count} photo
                        {category.default_picture_count === 1 ? '' : 's'} default
                    </p>
                </div>
            </div>

            <div className="flex items-center gap-1 shrink-0">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onMoveUp}
                    disabled={!canMoveUp}
                    className="size-7 text-muted-foreground"
                    aria-label={`Move ${category.name} up`}
                >
                    <ChevronUp className="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onMoveDown}
                    disabled={!canMoveDown}
                    className="size-7 text-muted-foreground"
                    aria-label={`Move ${category.name} down`}
                >
                    <ChevronDown className="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setEditOpen(true)}
                    className="size-7 text-muted-foreground"
                    aria-label={`Edit ${category.name}`}
                >
                    <Pencil className="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setDeleteOpen(true)}
                    className="size-7 text-destructive hover:bg-destructive/10"
                    aria-label={`Delete ${category.name}`}
                >
                    <Trash2 className="size-3.5" />
                </Button>
            </div>

            {editOpen && (
                <SubcategoryFormDialog
                    key={category.id}
                    mode="stage"
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    templateId={templateId}
                    sectionId={sectionId}
                    category={category}
                />
            )}

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteOpen(false);
                }}
                title="Delete subcategory"
                description={`Delete "${category.name}"? Field users will no longer see it.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </div>
    );
}
