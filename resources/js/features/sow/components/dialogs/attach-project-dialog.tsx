import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Building2, Link2, Loader2 } from 'lucide-react';
import { router } from '@inertiajs/react';
import * as React from 'react';

export interface AttachProjectDialogProps {
    isOpen: boolean;
    onClose: () => void;
    reportId: number;
    projects: Array<{ id: number; name: string; ps_number?: string | null }>;
}

/** §9.13 — link a standalone SOW to a same-market project (DRAFT only). */
export function AttachProjectDialog({
    isOpen,
    onClose,
    reportId,
    projects,
}: Readonly<AttachProjectDialogProps>) {
    const [projectId, setProjectId] = React.useState<number | ''>('');
    const [submitting, setSubmitting] = React.useState(false);

    React.useEffect(() => {
        if (isOpen) setProjectId('');
    }, [isOpen]);

    const attach = () => {
        if (!projectId) return;
        setSubmitting(true);
        // Inertia navigates on success (unmounting the dialog); keep the
        // spinner until then instead of resetting it synchronously.
        router.post(
            route('sow.reports.attach-project', { report: reportId }),
            {
                project_id: Number(projectId),
            },
            {
                // Success navigates away (unmounting the dialog); failure stays
                // open with flash errors and re-arms the button.
                onFinish: () => setSubmitting(false),
            }
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Attach to project</DialogTitle>
                    <DialogDescription>
                        Link this standalone SOW to a project in the same market. Site address,
                        city, and county are auto-filled from the project when the SOW fields are
                        blank.
                    </DialogDescription>
                </DialogHeader>

                {projects.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        No other projects exist in this market yet.
                    </p>
                ) : (
                    <div className="max-h-[40vh] space-y-2 overflow-y-auto pr-1">
                        {projects.map((project) => (
                            <button
                                key={project.id}
                                type="button"
                                onClick={() => setProjectId(project.id)}
                                className={`flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors ${
                                    projectId === project.id
                                        ? 'border-2 border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50'
                                }`}
                            >
                                <div className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Building2 className="size-4" />
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{project.name}</p>
                                    {project.ps_number && (
                                        <p className="text-xs text-muted-foreground">
                                            PS# {project.ps_number}
                                        </p>
                                    )}
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        onClick={attach}
                        disabled={!projectId || submitting}
                        className="gap-1.5"
                    >
                        {submitting ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <Link2 className="size-3.5" />
                        )}
                        Attach Project
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
