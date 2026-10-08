import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { router } from '@inertiajs/react';
import { AlertCircle, ListChecks, Lock, Plus } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { sowChecklistApi } from '../../api/sow-checklist.api';
import { sowErrorMessage } from '../../utils/sow-error';
import { SowChecklistItemDialog } from './sow-checklist-item-dialog';
import { SowChecklistRow } from './sow-checklist-row';
import type { SowChecklistEntry } from '../../types/sow.types';

export interface SowChecklistTabProps {
    reportId: number;
    checklist: SowChecklistEntry[];
    canEdit?: boolean;
    onSaved?: (updated: SowChecklistEntry[]) => void;
    onChange?: (updated: SowChecklistEntry[]) => void;
}

export function SowChecklistTab({
    reportId,
    checklist,
    canEdit = true,
    onSaved,
    onChange,
}: Readonly<SowChecklistTabProps>) {
    const [entries, setEntries] = React.useState<SowChecklistEntry[]>(checklist);
    const [isSaving, setIsSaving] = React.useState(false);
    const [dialogOpen, setDialogOpen] = React.useState(false);
    const [editingItem, setEditingItem] = React.useState<SowChecklistEntry | null>(null);
    const [deleteItem, setDeleteItem] = React.useState<SowChecklistEntry | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    React.useEffect(() => {
        setEntries(checklist);
    }, [checklist]);

    const unanswered = entries.filter((e) => e.is_required && !e.response).length;

    const setResponse = (id: number, response: SowChecklistEntry['response']) => {
        setEntries((prev) => {
            const next = prev.map((entry) => (entry.id === id ? { ...entry, response } : entry));
            onChange?.(next);
            return next;
        });
    };

    const setExplanation = (id: number, explanation: string) => {
        setEntries((prev) => {
            const next = prev.map((entry) => (entry.id === id ? { ...entry, explanation } : entry));
            onChange?.(next);
            return next;
        });
    };

    const refreshChecklist = () => {
        router.reload({ only: ['checklist'] });
    };

    const handleDelete = async () => {
        if (!deleteItem) return;
        setIsDeleting(true);
        try {
            await sowChecklistApi.deleteItem(reportId, deleteItem.id);
            toast.success('Survey question removed.');
            setDeleteItem(null);
            refreshChecklist();
        } catch (err) {
            toast.error(sowErrorMessage(err, 'Failed to remove the question.'));
        } finally {
            setIsDeleting(false);
        }
    };

    const saveResponses = async () => {
        setIsSaving(true);
        try {
            const res = await sowChecklistApi.update(reportId, {
                responses: entries.map((entry) => ({
                    sow_checklist_item_id: entry.id,
                    response: entry.response,
                    explanation:
                        entry.response === 'no' && entry.has_explain_field
                            ? entry.explanation
                            : null,
                })),
            });
            const fresh = res.checklist ?? entries;
            setEntries(fresh);
            onSaved?.(fresh);
            toast.success('Survey checklist answers saved.');
            refreshChecklist();
        } catch (err) {
            toast.error(sowErrorMessage(err, 'Failed to save checklist answers.'));
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <Card className="border-border/80 shadow-sm">
            <CardHeader className="flex-row flex-wrap items-center justify-between gap-y-2 space-y-0 pb-3 border-b border-border/60">
                <div className="flex items-center gap-2">
                    <ListChecks className="size-4 text-primary" />
                    <CardTitle className="text-sm font-semibold">Survey Checklist</CardTitle>
                    <span className="text-xs text-muted-foreground font-normal">
                        ({entries.length} {entries.length === 1 ? 'question' : 'questions'})
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    {unanswered > 0 && (
                        <Badge variant="warning" className="text-[11px] gap-1">
                            <AlertCircle className="size-3" />
                            {unanswered} unanswered
                        </Badge>
                    )}
                    {!canEdit && (
                        <span className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground bg-muted/60 border border-border/80 rounded-md px-2 py-1">
                            <Lock className="size-3 text-amber-500 shrink-0" />
                            Read-only · reopen to change answers
                        </span>
                    )}
                    {canEdit && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setEditingItem(null);
                                setDialogOpen(true);
                            }}
                            className="h-7 text-xs gap-1"
                        >
                            <Plus className="size-3" /> Add Question
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-3 pt-4">
                {entries.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border/80 p-8 text-center space-y-2">
                        <ListChecks className="mx-auto size-8 text-muted-foreground/60" />
                        <p className="text-sm font-medium text-foreground">
                            {canEdit ? 'No survey questions yet' : 'No survey questions recorded'}
                        </p>
                        <p className="text-xs text-muted-foreground max-w-md mx-auto">
                            {canEdit
                                ? 'Add custom questions to check during the site survey. You can choose whether an explanation is required when answered "No".'
                                : 'This report was finalized without any survey checklist questions.'}
                        </p>
                        {canEdit && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    setEditingItem(null);
                                    setDialogOpen(true);
                                }}
                                className="mt-2 text-xs gap-1.5"
                            >
                                <Plus className="size-3.5" /> Add First Question
                            </Button>
                        )}
                    </div>
                ) : (
                    entries.map((entry, index) => (
                        <SowChecklistRow
                            key={entry.id}
                            entry={entry}
                            index={index}
                            canEdit={canEdit}
                            onSetResponse={setResponse}
                            onSetExplanation={setExplanation}
                            onEdit={(item) => {
                                setEditingItem(item);
                                setDialogOpen(true);
                            }}
                            onDelete={setDeleteItem}
                        />
                    ))
                )}

                {entries.length > 0 && canEdit && (
                    <div className="flex justify-end pt-2 border-t border-border/60">
                        <Button
                            size="sm"
                            onClick={saveResponses}
                            disabled={isSaving}
                            className="text-xs h-8"
                        >
                            {isSaving ? 'Saving…' : 'Save Checklist Answers'}
                        </Button>
                    </div>
                )}
            </CardContent>

            <SowChecklistItemDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                reportId={reportId}
                item={editingItem}
                onSuccess={refreshChecklist}
            />

            <ConfirmDialog
                open={deleteItem !== null}
                onOpenChange={(open) => !open && setDeleteItem(null)}
                title="Delete Survey Question?"
                description={`Are you sure you want to remove "${deleteItem?.label}"? Any recorded responses will also be removed.`}
                confirmLabel="Delete Question"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </Card>
    );
}
