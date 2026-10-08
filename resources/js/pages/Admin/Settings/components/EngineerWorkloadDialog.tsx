import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FolderKanban } from 'lucide-react';
import type { EngineerItem } from './customer-hierarchy-types';

/** "in_progress" → "In progress" — the payload ships raw enum values. */
function humanStatus(status: string): string {
    const text = status.replace(/_/g, ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * One engineer's current workload — the active projects assigned to them.
 * Managers read it here; the engineer sees the same list in the Customer Portal.
 */
export function EngineerWorkloadDialog({
    engineer,
    onOpenChange,
}: Readonly<{ engineer: EngineerItem | null; onOpenChange: (open: boolean) => void }>) {
    const projects = engineer?.assigned_projects ?? [];

    return (
        <Dialog open={engineer !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{engineer?.name}&apos;s projects</DialogTitle>
                    <DialogDescription>
                        Active projects assigned to this engineer — also visible to them in the Customer Portal.
                    </DialogDescription>
                </DialogHeader>

                {projects.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No projects assigned yet.
                    </p>
                ) : (
                    <ul className="max-h-[50vh] divide-y divide-border/60 overflow-y-auto">
                        {projects.map((project) => (
                            <li key={project.id} className="flex items-center gap-3 py-2.5">
                                <FolderKanban className="size-4 shrink-0 text-primary" />
                                <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                                    {project.name}
                                </span>
                                {project.status && (
                                    <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                        {humanStatus(project.status)}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}
