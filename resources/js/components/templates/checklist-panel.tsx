import * as React from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { ChecklistFormDialog } from '@/components/templates/checklist-form-dialog';
import {
    TemplateChecklistItem,
    nextSortOrder,
} from '@/components/templates/template-types';
import { ChevronDown, ChevronUp, ListChecks, MessageSquareWarning, Pencil, Plus, Trash2 } from 'lucide-react';

export interface ChecklistPanelProps {
    templateId: number;
    items: TemplateChecklistItem[];
}

export function ChecklistPanel({ templateId, items }: Readonly<ChecklistPanelProps>) {
    const [addOpen, setAddOpen] = React.useState(false);
    const [editItem, setEditItem] = React.useState<TemplateChecklistItem | null>(null);
    const [deleteItem, setDeleteItem] = React.useState<TemplateChecklistItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const handleReorder = (fromIndex: number, toIndex: number) => {
        const reordered = [...items];
        const [moved] = reordered.splice(fromIndex, 1);
        reordered.splice(toIndex, 0, moved);

        router.post(
            route('admin.templates.checklist.reorder', templateId),
            {
                items: reordered.map((item, index) => ({
                    id: item.id,
                    sort_order: index,
                })),
            },
            { preserveScroll: true }
        );
    };

    const handleDelete = () => {
        if (!deleteItem) return;
        setIsDeleting(true);
        router.delete(route('admin.templates.checklist.destroy', [templateId, deleteItem.id]), {
            onSuccess: () => {
                setDeleteItem(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    return (
        <div className="rounded-xl border border-border/80 bg-card overflow-hidden">
            <div className="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-4 py-3">
                <div className="flex items-center gap-2">
                    <ListChecks className="size-4 text-primary" />
                    <h3 className="text-sm font-bold text-foreground">
                        Package Checklist
                    </h3>
                </div>
                <p className="text-[11px] text-muted-foreground">
                    {items.length} items
                </p>
            </div>

            <div className="space-y-2 p-4">
                <div className="flex items-center justify-end">
                    <Button
                        variant="default"
                        size="sm"
                        onClick={() => setAddOpen(true)}
                        className="h-8 text-xs gap-1.5 shadow-sm"
                    >
                        <Plus className="size-3.5" /> Add Checklist Item
                    </Button>
                </div>

                {items.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-4 text-center">
                        <p className="text-xs font-medium text-foreground">No questions yet</p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Add YES/NO questions the field tech answers at sign-off.
                        </p>
                    </div>
                ) : (
                    items.map((item, index) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 p-3"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="text-xs font-medium text-foreground">
                                    {item.label}
                                </p>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {item.has_explain_field && (
                                        <Badge
                                            variant="warning"
                                            className="text-[10px] gap-1 shrink-0"
                                            title="Technicians and managers can add an optional note to any Yes or No answer"
                                        >
                                            <MessageSquareWarning className="size-3" />
                                            Notes enabled
                                        </Badge>
                                    )}
                                    {item.allows_na && (
                                        <Badge
                                            variant="secondary"
                                            className="text-[10px] gap-1 shrink-0"
                                            title="Technicians can mark this question as N/A"
                                        >
                                            Allows N/A
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleReorder(index, index - 1)}
                                    disabled={index === 0}
                                    className="size-7 text-muted-foreground"
                                    aria-label={`Move item ${index + 1} up`}
                                >
                                    <ChevronUp className="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleReorder(index, index + 1)}
                                    disabled={index === items.length - 1}
                                    className="size-7 text-muted-foreground"
                                    aria-label={`Move item ${index + 1} down`}
                                >
                                    <ChevronDown className="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setEditItem(item)}
                                    className="size-7 text-muted-foreground"
                                    aria-label={`Edit ${item.label}`}
                                >
                                    <Pencil className="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setDeleteItem(item)}
                                    className="size-7 text-destructive hover:bg-destructive/10"
                                    aria-label={`Delete ${item.label}`}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {addOpen && (
                <ChecklistFormDialog
                    key="new"
                    open={addOpen}
                    onOpenChange={setAddOpen}
                    templateId={templateId}
                    initialSortOrder={nextSortOrder(items)}
                />
            )}

            {editItem && (
                <ChecklistFormDialog
                    key={editItem.id}
                    open={Boolean(editItem)}
                    onOpenChange={(open) => {
                        if (!open) setEditItem(null);
                    }}
                    templateId={templateId}
                    item={editItem}
                />
            )}

            <ConfirmDialog
                open={Boolean(deleteItem)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteItem(null);
                }}
                title="Delete checklist item"
                description={`Delete "${deleteItem?.label}"? It will be removed from this template.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </div>
    );
}
