import { cn } from '@/lib/utils';
import {
    AlertCircle,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    HelpCircle,
    Lock,
    ShieldAlert,
    Wrench,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { ISSUE_TYPE_LABELS, IssueItem, IssueTypeValue } from './issue-types';

export interface ResolvedIssueTableProps {
    issues: IssueItem[];
    onSelectIssue: (issue: IssueItem) => void;
    onViewAuditLog?: () => void;
    statusFilter?: 'all' | 'open' | 'resolved';
    selectedIds?: number[];
    onSelectionChange?: (ids: number[]) => void;
    selectable?: boolean;
}

const ITEMS_PER_PAGE = 10;

function getInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatResolutionDate(isoString?: string | null): string {
    if (!isoString) {
        return '—';
    }
    try {
        const date = new Date(isoString);
        return date.toISOString().split('T')[0] ?? isoString;
    } catch {
        return isoString;
    }
}

function renderTypeBadge(type: IssueTypeValue) {
    const label = ISSUE_TYPE_LABELS[type] ?? 'Issue';

    switch (type) {
        case 'lock':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 whitespace-nowrap">
                    <Lock className="size-3" />
                    <span>{label}</span>
                </span>
            );
        case 'site_access':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-orange-500/15 text-orange-700 dark:text-orange-300 border border-orange-500/30 whitespace-nowrap">
                    <ShieldAlert className="size-3" />
                    <span>{label}</span>
                </span>
            );
        case 'construction':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/30 whitespace-nowrap">
                    <Wrench className="size-3" />
                    <span>{label}</span>
                </span>
            );
        case 'verizon_guidance':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-500/30 whitespace-nowrap">
                    <HelpCircle className="size-3" />
                    <span>{label}</span>
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30 whitespace-nowrap">
                    <AlertCircle className="size-3" />
                    <span>{label}</span>
                </span>
            );
    }
}

function renderResolverAvatar(issue: IssueItem, isResolved: boolean, resolverName: string) {
    if (!isResolved) {
        return <span className="text-muted-foreground text-xs">—</span>;
    }

    if (issue.resolver?.profile_photo_url) {
        return (
            <img
                src={issue.resolver.profile_photo_url}
                alt={resolverName}
                className="size-7 rounded-full object-cover border border-border shadow-2xs"
                title={`Resolved by: ${resolverName}`}
            />
        );
    }

    return (
        <div
            className="size-7 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center border border-border/80 shadow-2xs"
            title={`Resolved by: ${resolverName}`}
        >
            {getInitials(resolverName)}
        </div>
    );
}

function renderStatusBadge(isResolved: boolean) {
    if (isResolved) {
        return (
            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider bg-emerald-600 dark:bg-emerald-700 text-white shadow-2xs">
                <span>RESOLVED</span>
                <Check className="size-3 stroke-[2.5]" />
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-400 shadow-2xs">
            <span className="size-1.5 rounded-full bg-amber-500 animate-pulse" />
            <span>OPEN</span>
        </span>
    );
}

export function ResolvedIssueTable({
    issues,
    onSelectIssue,
    onViewAuditLog,
    statusFilter = 'all',
    selectedIds: controlledIds,
    onSelectionChange,
    selectable = true,
}: Readonly<ResolvedIssueTableProps>) {
    const [internalSelectedIds, setInternalSelectedIds] = React.useState<number[]>([]);
    const isControlled = controlledIds !== undefined;
    const selectedIds = isControlled ? controlledIds! : internalSelectedIds;
    const setSelectedIds = React.useCallback(
        (updater: number[] | ((prev: number[]) => number[])) => {
            const next =
                typeof updater === 'function'
                    ? (updater as (prev: number[]) => number[])(selectedIds)
                    : updater;
            if (isControlled) {
                onSelectionChange?.(next);
            } else {
                setInternalSelectedIds(next);
            }
        },
        [isControlled, onSelectionChange, selectedIds]
    );
    const [currentPage, setCurrentPage] = React.useState(1);

    const totalPages = Math.max(1, Math.ceil(issues.length / ITEMS_PER_PAGE));

    React.useEffect(() => {
        if (currentPage > totalPages) {
            setCurrentPage(totalPages);
        }
    }, [currentPage, totalPages]);

    const paginatedIssues = React.useMemo(() => {
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        return issues.slice(start, start + ITEMS_PER_PAGE);
    }, [issues, currentPage]);

    const allPageSelected =
        paginatedIssues.length > 0 &&
        paginatedIssues.every((issue) => selectedIds.includes(issue.id));

    const toggleSelectAll = React.useCallback(() => {
        if (allPageSelected) {
            const pageIds = new Set(paginatedIssues.map((i) => i.id));
            setSelectedIds((prev) => prev.filter((id) => !pageIds.has(id)));
        } else {
            const pageIds = paginatedIssues.map((i) => i.id);
            const hasResolved = paginatedIssues.some((i) => Boolean(i.resolved_at));
            if (hasResolved) {
                toast.warning('Resolved issues are included in this selection.');
            }
            setSelectedIds((prev) => Array.from(new Set([...prev, ...pageIds])));
        }
    }, [allPageSelected, paginatedIssues, setSelectedIds]);

    const toggleSelectRow = React.useCallback(
        (id: number, e: React.MouseEvent) => {
            e.stopPropagation();
            const target = issues.find((i) => i.id === id);
            const isResolving = Boolean(target?.resolved_at);
            setSelectedIds((prev) => {
                const already = prev.includes(id);
                if (!already && isResolving) {
                    toast.warning('You selected a resolved issue — it will still be forwarded.');
                }
                return already ? prev.filter((item) => item !== id) : [...prev, id];
            });
        },
        [issues, setSelectedIds]
    );

    return (
        <div className="w-full space-y-4">
            <div className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr className="border-b border-border/70 bg-muted/40 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                {selectable && (
                                    <th className="w-10 px-4 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            aria-label="Select all on page"
                                            checked={allPageSelected}
                                            onChange={toggleSelectAll}
                                            className="size-4 rounded border-border text-primary focus:ring-primary/20 cursor-pointer"
                                        />
                                    </th>
                                )}
                                <th className="px-4 py-3 font-bold text-foreground whitespace-nowrap">ID</th>
                                <th className="px-4 py-3 font-bold text-foreground whitespace-nowrap">Type</th>
                                <th className="px-4 py-3 font-bold text-foreground whitespace-nowrap">Subject</th>
                                <th className="px-4 py-3 font-bold text-foreground whitespace-nowrap">Reported By</th>
                                <th className="px-4 py-3 font-bold text-foreground text-center whitespace-nowrap">Resolved By</th>
                                <th className="px-4 py-3 font-bold text-foreground whitespace-nowrap">Resolution Date</th>
                                <th className="px-4 py-3 font-bold text-foreground text-center whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/60">
                            {paginatedIssues.map((issue) => {
                                const isChecked = selectedIds.includes(issue.id);
                                const isResolved = Boolean(issue.resolved_at);
                                const resolverName = issue.resolver?.name ?? 'Project Manager';
                                const reporterName = issue.reporter?.name ?? 'Team Member';
                                const formattedId = isResolved ? `#RES-${issue.id}` : `#ISS-${issue.id}`;
                                const dateStr = isResolved
                                    ? formatResolutionDate(issue.resolved_at)
                                    : '—';

                                return (
                                    <tr
                                        key={issue.id}
                                        onClick={() => onSelectIssue(issue)}
                                        className={cn(
                                            'group cursor-pointer transition-colors hover:bg-muted/50',
                                            isChecked && 'bg-primary/5 dark:bg-primary/10'
                                        )}
                                    >
                                        {/* Checkbox column */}
                                        {selectable && (
                                            <td
                                                className="px-4 py-3.5 text-center"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Select issue ${formattedId}`}
                                                    checked={isChecked}
                                                    onChange={(e) =>
                                                        toggleSelectRow(issue.id, e as unknown as React.MouseEvent)
                                                    }
                                                    className="size-4 rounded border-border text-primary focus:ring-primary/20 cursor-pointer"
                                                />
                                            </td>
                                        )}

                                        {/* ID */}
                                        <td className="px-4 py-3.5 font-semibold text-foreground whitespace-nowrap">
                                            {formattedId}
                                        </td>

                                        {/* Type Name Badge */}
                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                            {renderTypeBadge(issue.type)}
                                        </td>

                                        {/* Subject / Description */}
                                        <td className="px-4 py-3.5">
                                            <span
                                                className="block max-w-[180px] sm:max-w-[220px] md:max-w-[260px] lg:max-w-[300px] truncate font-medium text-foreground group-hover:text-primary transition-colors"
                                                title={issue.description}
                                            >
                                                {issue.description}
                                            </span>
                                        </td>

                                        {/* Reported By */}
                                        <td className="px-4 py-3.5 font-medium text-foreground whitespace-nowrap">
                                            {reporterName}
                                        </td>

                                        {/* Resolved By - Avatar Only */}
                                        <td className="px-4 py-3.5 text-center">
                                            <div className="flex items-center justify-center">
                                                {renderResolverAvatar(issue, isResolved, resolverName)}
                                            </div>
                                        </td>

                                        {/* Resolution Date */}
                                        <td className="px-4 py-3.5 font-mono text-muted-foreground whitespace-nowrap">
                                            {dateStr}
                                        </td>

                                        {/* Status Badge */}
                                        <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                            {renderStatusBadge(isResolved)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Footer Controls / Pagination */}
                <div className="flex items-center justify-between gap-4 flex-wrap border-t border-border/70 bg-muted/20 px-4 py-3 text-xs">
                    {/* Left: Summary Count */}
                    <div className="text-muted-foreground text-xs">
                        Showing{' '}
                        <span className="font-semibold text-foreground">
                            {issues.length === 0 ? 0 : (currentPage - 1) * ITEMS_PER_PAGE + 1}
                        </span>{' '}
                        to{' '}
                        <span className="font-semibold text-foreground">
                            {Math.min(currentPage * ITEMS_PER_PAGE, issues.length)}
                        </span>{' '}
                        of <span className="font-semibold text-foreground">{issues.length}</span>{' '}
                        {statusFilter === 'all'
                            ? 'issues'
                            : `${statusFilter} issues`}
                    </div>

                    {/* Center: Pagination Buttons */}
                    {totalPages > 1 && (
                        <div className="flex items-center gap-1 mx-auto sm:mx-0">
                            {/* First Page */}
                            <button
                                type="button"
                                aria-label="First page"
                                disabled={currentPage === 1}
                                onClick={() => setCurrentPage(1)}
                                className="flex size-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                            >
                                <ChevronsLeft className="size-3.5" />
                            </button>

                            {/* Prev Page */}
                            <button
                                type="button"
                                aria-label="Previous page"
                                disabled={currentPage === 1}
                                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                className="flex size-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                            >
                                <ChevronLeft className="size-3.5" />
                            </button>

                            {/* Page Numbers */}
                            {Array.from({ length: totalPages }, (_, i) => i + 1)
                                .filter((page) => {
                                    return (
                                        page === 1 ||
                                        page === totalPages ||
                                        Math.abs(page - currentPage) <= 1
                                    );
                                })
                                .map((page) => (
                                    <button
                                        key={page}
                                        type="button"
                                        onClick={() => setCurrentPage(page)}
                                        className={cn(
                                            'min-w-7 h-7 px-2 rounded-md text-xs font-semibold transition-colors flex items-center justify-center cursor-pointer',
                                            currentPage === page
                                                ? 'bg-foreground text-background font-bold shadow-xs'
                                                : 'border border-border bg-card text-muted-foreground hover:text-foreground'
                                        )}
                                    >
                                        {page}
                                    </button>
                                ))}

                            {/* Next Page */}
                            <button
                                type="button"
                                aria-label="Next page"
                                disabled={currentPage === totalPages}
                                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                className="flex size-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                            >
                                <ChevronRight className="size-3.5" />
                            </button>

                            {/* Last Page */}
                            <button
                                type="button"
                                aria-label="Last page"
                                disabled={currentPage === totalPages}
                                onClick={() => setCurrentPage(totalPages)}
                                className="flex size-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                            >
                                <ChevronsRight className="size-3.5" />
                            </button>
                        </div>
                    )}

                    {/* Right: View Full Audit Log */}
                    {onViewAuditLog && (
                        <button
                            type="button"
                            onClick={onViewAuditLog}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-foreground/90 text-background text-xs font-semibold hover:bg-foreground transition-colors shadow-2xs cursor-pointer"
                        >
                            View Full Audit Log
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
