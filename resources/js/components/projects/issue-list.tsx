import { CheckCircle2, Plus } from 'lucide-react';
import * as React from 'react';
import { IssueDetailCard, ProjectLocationInfo } from './issue-detail-card';
import type {
    IssueItem,
    IssueStatusFilter,
    ProjectStakeholder,
} from './issue-types';
import { ResolvedIssueTable } from './resolved-issue-table';

export {
    getStoredIssueTab,
    ISSUE_TAB_STORAGE_KEY,
    ISSUE_TYPE_LABELS,
    ISSUE_TYPES,
    QUICK_REPLY_CHIPS,
    setStoredIssueTab,
    type IssueComment,
    type IssueItem,
    type IssueStatusFilter,
    type IssueTypeValue,
    type ProjectStakeholder,
} from './issue-types';

export interface IssueListProps {
    projectId: number | string;
    projectLocation?: ProjectLocationInfo | null;
    issues: IssueItem[];
    canResolve: boolean;
    canComment: boolean;
    canReport?: boolean;
    onOpenReportDialog?: () => void;
    statusFilter?: IssueStatusFilter;
    manager?: ProjectStakeholder | null;
    fieldSupervisors?: ProjectStakeholder[];
    selectedIssueId?: number | null;
    onSelectIssue?: (issue: IssueItem | null) => void;
    selectable?: boolean;
    selectedIds?: number[];
    onSelectionChange?: (ids: number[]) => void;
}

export function IssueList({
    projectId,
    projectLocation,
    issues,
    canResolve,
    canComment,
    canReport = false,
    onOpenReportDialog,
    statusFilter = 'open',
    manager,
    fieldSupervisors = [],
    selectedIssueId = null,
    onSelectIssue,
    selectable = false,
    selectedIds,
    onSelectionChange,
}: Readonly<IssueListProps>) {
    const [internalSelectedId, setInternalSelectedId] = React.useState<number | null>(null);

    const activeSelectedId = selectedIssueId ?? internalSelectedId;

    const handleSelect = React.useCallback(
        (issue: IssueItem | null) => {
            if (onSelectIssue) {
                onSelectIssue(issue);
            } else {
                setInternalSelectedId(issue ? issue.id : null);
            }
        },
        [onSelectIssue]
    );

    const filteredIssues = React.useMemo(() => {
        if (statusFilter === 'open') {
            return issues.filter((i) => !i.resolved_at);
        }
        if (statusFilter === 'resolved') {
            return issues.filter((i) => Boolean(i.resolved_at));
        }
        return issues;
    }, [issues, statusFilter]);

    const selectedIssue = React.useMemo(() => {
        if (activeSelectedId === null) {
            return null;
        }
        return issues.find((i) => i.id === activeSelectedId) ?? null;
    }, [issues, activeSelectedId]);

    if (issues.length === 0) {
        return (
            <div className="w-full rounded-md border border-dashed border-border/80 bg-card p-12 text-center space-y-4 shadow-2xs">
                <div className="mx-auto flex size-12 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="size-6" />
                </div>
                <div className="space-y-1">
                    <h3 className="text-base font-bold text-foreground">No Issues Recorded</h3>
                    <p className="text-xs text-muted-foreground max-w-md mx-auto">
                        All clear for this site! If field conditions change or an access blocker
                        arises, tap Report New Issue.
                    </p>
                </div>
                {canReport && onOpenReportDialog && (
                    <button
                        type="button"
                        onClick={onOpenReportDialog}
                        className="inline-flex items-center justify-center px-4 py-2 rounded-full bg-foreground text-background text-xs font-semibold hover:bg-foreground/90 transition-colors shadow-xs cursor-pointer"
                    >
                        Report New Issue
                    </button>
                )}
            </div>
        );
    }

    if (filteredIssues.length === 0) {
        return (
            <div className="w-full rounded-md border border-dashed border-border/80 bg-card p-10 text-center space-y-4 shadow-2xs">
                <div className="mx-auto flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground">
                    <CheckCircle2 className="size-5" />
                </div>
                <div className="space-y-1">
                    <h3 className="text-sm font-bold text-foreground">
                        No {statusFilter} issues found
                    </h3>
                    <p className="text-xs text-muted-foreground max-w-sm mx-auto">
                        {statusFilter === 'open'
                            ? 'There are currently no active open blockers or issues on this site.'
                            : `There are no ${statusFilter} issues recorded.`}
                    </p>
                </div>
                {canReport && onOpenReportDialog && (
                    <button
                        type="button"
                        onClick={onOpenReportDialog}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full bg-foreground text-background text-xs font-semibold hover:bg-foreground/90 transition-colors shadow-xs cursor-pointer"
                    >
                        <Plus className="size-3.5" /> Report New Issue
                    </button>
                )}
            </div>
        );
    }

    // If an issue is selected, display that specific issue's detail and discussion feed
    if (selectedIssue) {
        const issueIndex = filteredIssues.findIndex((i) => i.id === selectedIssue.id);

        return (
            <div className="space-y-6 w-full animate-in fade-in duration-150">
                <IssueDetailCard
                    projectId={projectId}
                    projectLocation={projectLocation}
                    issue={selectedIssue}
                    issueNumber={issueIndex >= 0 ? issueIndex + 1 : 1}
                    canResolve={canResolve && !selectedIssue.resolved_at}
                    canComment={canComment}
                    canReport={canReport}
                    onOpenReportDialog={onOpenReportDialog}
                    manager={manager}
                    fieldSupervisors={fieldSupervisors}
                />
            </div>
        );
    }

    // Default: Display the unified issues table
    return (
        <div className="space-y-6 w-full animate-in fade-in duration-150">
            <ResolvedIssueTable
                issues={filteredIssues}
                onSelectIssue={handleSelect}
                statusFilter={statusFilter}
                selectable={selectable}
                selectedIds={selectedIds}
                onSelectionChange={onSelectionChange}
            />
        </div>
    );
}
