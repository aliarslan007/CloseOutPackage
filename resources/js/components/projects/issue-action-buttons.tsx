import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { CheckCircle2, Plus, Trash2 } from 'lucide-react';
import * as React from 'react';
import { IssueItem } from './issue-types';

export interface IssueActionButtonsProps {
    projectId: number | string;
    issue: IssueItem;
    isResolved: boolean;
    canResolve: boolean;
    canDelete: boolean;
    canReport?: boolean;
    onOpenReportDialog?: () => void;
}

export function IssueActionButtons({
    projectId,
    issue,
    isResolved,
    canResolve,
    canDelete,
    canReport = false,
    onOpenReportDialog,
}: Readonly<IssueActionButtonsProps>) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleting, setDeleting] = React.useState(false);
    const [resolveDialogOpen, setResolveDialogOpen] = React.useState(false);
    const [resolving, setResolving] = React.useState(false);

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('projects.issues.destroy', [projectId, issue.hash_id || issue.id]), {
            preserveScroll: true,
            onSuccess: () => setDeleteDialogOpen(false),
            onFinish: () => setDeleting(false),
        });
    };

    const handleResolve = () => {
        setResolving(true);
        router.put(
            route('projects.issues.resolve', [projectId, issue.hash_id || issue.id]),
            undefined,
            {
                preserveScroll: true,
                onSuccess: () => setResolveDialogOpen(false),
                onFinish: () => setResolving(false),
            }
        );
    };

    return (
        <>
            <div className="pt-2 border-t border-border/60 flex items-center justify-between gap-2 flex-wrap">
                <div className="flex items-center gap-2">
                    {!isResolved && canResolve && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setResolveDialogOpen(true)}
                            className="h-8 rounded-lg text-xs font-semibold gap-1 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-50 dark:hover:bg-emerald-950/20"
                        >
                            <CheckCircle2 className="size-3.5" /> Mark Resolved
                        </Button>
                    )}

                    {canDelete && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteDialogOpen(true)}
                            className="h-8 rounded-lg text-xs text-muted-foreground hover:text-destructive hover:bg-destructive/10 gap-1"
                        >
                            <Trash2 className="size-3.5" />
                            <span>Delete</span>
                        </Button>
                    )}
                </div>

                {canReport && onOpenReportDialog && (
                    <button
                        type="button"
                        onClick={onOpenReportDialog}
                        className="h-8 px-3 rounded-lg bg-foreground text-background text-xs font-semibold hover:bg-foreground/90 transition-colors shadow-xs inline-flex items-center gap-1 ml-auto cursor-pointer"
                    >
                        <Plus className="size-3.5" />
                        <span>Report New</span>
                    </button>
                )}
            </div>

            {/* Resolve Confirmation Dialog */}
            {canResolve && !isResolved && (
                <ConfirmDialog
                    open={resolveDialogOpen}
                    onOpenChange={setResolveDialogOpen}
                    title="Mark Issue as Resolved"
                    description="Are you sure you want to mark this issue as resolved? Once resolved, the issue will be closed and further comments will be disabled."
                    confirmLabel="Mark as Resolved"
                    loading={resolving}
                    onConfirm={handleResolve}
                />
            )}

            {/* Delete Confirmation Dialog */}
            {canDelete && (
                <ConfirmDialog
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                    title="Delete Issue"
                    description="Are you sure you want to delete this issue? This will permanently remove the issue report and all discussion comments."
                    confirmLabel="Delete Issue"
                    variant="destructive"
                    loading={deleting}
                    onConfirm={handleDelete}
                />
            )}
        </>
    );
}
