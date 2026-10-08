import {
    getStoredIssueTab,
    IssueItem,
    IssueList,
    IssueStatusFilter,
    ProjectStakeholder,
    setStoredIssueTab,
} from '@/components/projects/issue-list';
import {
    ForwardCustomerOption,
    ForwardIssuesToCustomerDialog,
} from '@/components/projects/forward-issues-dialog';
import { ReportIssueDialog } from '@/components/projects/report-issue-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Mail, Plus } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface IssuesProjectProp {
    id: number;
    hash_id?: string;
    name: string;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    vzw_engineer_phone?: string | null;
    manager?: ProjectStakeholder | null;
    field_supervisors?: ProjectStakeholder[];
}

export type ProjectsIssuesPageProps = PageProps<{
    project: IssuesProjectProp;
    issues: IssueItem[];
    canComment: boolean;
    customers?: ForwardCustomerOption[];
    canForward?: boolean;
    allowMediaEditWhenResolved?: boolean;
}>;

const ISSUE_HISTORY_LABEL = 'Issue History';
const PROJECT_SHOW_ROUTE = 'projects.show';

export function ProjectsIssues() {
    useFlashToasts();

    // Live sync polling every 3.5s
    usePoll(3500, { only: ['issues'] }, { autoStart: true });

    const { project, issues, auth, canComment, customers = [], canForward = false } =
        usePage<ProjectsIssuesPageProps>().props;
    const isCustomer = auth.user.roles.includes('customer');
    const canResolve = auth.user.roles.includes('admin') || auth.user.roles.includes('manager');
    const canReport =
        auth.user.roles.includes('admin') ||
        auth.user.roles.includes('manager') ||
        auth.user.roles.includes('field_supervisor');
    const mayComment = !isCustomer && (canComment || canReport);

    const [statusFilter, setStatusFilter] = React.useState<IssueStatusFilter>(() =>
        getStoredIssueTab()
    );
    const [selectedIssueId, setSelectedIssueId] = React.useState<number | null>(null);
    const [reportDialogOpen, setReportDialogOpen] = React.useState(false);
    const [selectedIds, setSelectedIds] = React.useState<number[]>([]);
    const [forwardDialogOpen, setForwardDialogOpen] = React.useState(false);

    const handleSelectionChange = React.useCallback((ids: number[]) => {
        setSelectedIds(ids);
    }, []);

    const handleForwardClick = () => {
        if (selectedIds.length === 0) {
            toast.error('Select at least one issue to forward.');
            return;
        }
        if (customers.length === 0) {
            toast.error('No Verizon customers have access to this project yet.');
            return;
        }
        setForwardDialogOpen(true);
    };

    const handleStatusFilterChange = (filter: IssueStatusFilter) => {
        setStatusFilter(filter);
        setSelectedIssueId(null);
        setStoredIssueTab(filter);
    };

    const breadcrumb = isCustomer
        ? [
              { label: 'Customer Portal', href: route('portal.index') },
              {
                  label: project.name,
                  href: route(PROJECT_SHOW_ROUTE, project.hash_id || project.id),
              },
              { label: ISSUE_HISTORY_LABEL },
          ]
        : [
              { label: 'Projects', href: route('projects.index') },
              {
                  label: project.name,
                  href: route(PROJECT_SHOW_ROUTE, project.hash_id || project.id),
              },
              { label: ISSUE_HISTORY_LABEL },
          ];

    if (!issues) {
        return (
            <AppLayout title="Issues" breadcrumb={breadcrumb}>
                <Head title="Issues" />
                <EmptyCard
                    title="Issues unavailable"
                    description="The issue history could not be loaded."
                    icon={<AlertTriangle className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    const openIssuesCount = issues.filter((i) => !i.resolved_at).length;
    const resolvedCount = issues.filter((i) => Boolean(i.resolved_at)).length;

    return (
        <AppLayout
            title="Issue History & Collaboration"
            breadcrumb={breadcrumb}
        >
            <Head title={`Issues — ${project.name}`} />

            <div className="space-y-6 w-full">
                {/* Top Navigation Row: Context-Aware Back Button on Left, Filter Tabs & Report New on Right */}
                <div className="flex items-center justify-between gap-3 flex-wrap">
                    {/* Context-Aware Back Button */}
                    {selectedIssueId === null ? (
                        <Link
                            href={route(PROJECT_SHOW_ROUTE, project.hash_id || project.id)}
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full border border-border/80 bg-card text-xs font-semibold text-foreground hover:bg-muted transition-colors shadow-2xs"
                        >
                            <ArrowLeft className="size-3.5" /> Back to Site
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setSelectedIssueId(null)}
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full border border-border/80 bg-card text-xs font-semibold text-foreground hover:bg-muted transition-colors shadow-2xs cursor-pointer"
                        >
                            <ArrowLeft className="size-3.5" /> Back to Issues
                        </button>
                    )}

                    <div className="flex items-center gap-2 flex-wrap">
                        {/* Filter Pills Group */}
                        <div className="inline-flex items-center gap-1 p-1 rounded-full border border-border/70 bg-card shadow-2xs">
                            {([
                                { key: 'open' as const, label: 'Open', count: openIssuesCount, activeBg: 'bg-emerald-600 text-white font-bold shadow-xs' },
                                { key: 'all' as const, label: 'All', count: issues.length, activeBg: 'bg-foreground text-background font-bold shadow-xs' },
                                { key: 'resolved' as const, label: 'Resolved', count: resolvedCount, activeBg: 'bg-emerald-700 text-white font-bold shadow-xs' },
                            ] as const).map((tab) => {
                                const active = statusFilter === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => handleStatusFilterChange(tab.key)}
                                        className={cn(
                                            'px-3 py-1 rounded-full text-xs transition-all flex items-center gap-1.5',
                                            active
                                                ? tab.activeBg
                                                : 'text-muted-foreground hover:text-foreground font-medium'
                                        )}
                                    >
                                        <span>{tab.label}</span>
                                        <span
                                            className={cn(
                                                'min-w-[18px] h-[18px] px-1 flex items-center justify-center rounded-full text-[10px] font-bold leading-none shrink-0',
                                                active
                                                    ? 'bg-black/20 text-white dark:bg-white/20'
                                                    : 'bg-muted text-muted-foreground'
                                            )}
                                        >
                                            {tab.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Forward to Customer — persistent for admin/manager */}
                        {canForward && selectedIssueId === null && (
                            <button
                                type="button"
                                onClick={handleForwardClick}
                                className={cn(
                                    'inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-semibold transition-colors shadow-2xs cursor-pointer border',
                                    selectedIds.length > 0
                                        ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                                        : 'bg-card text-muted-foreground border-border/70 hover:text-foreground hover:bg-muted'
                                )}
                            >
                                <Mail className="size-3.5" />
                                <span>Forward to Customer</span>
                                {selectedIds.length > 0 && (
                                    <span className="min-w-[18px] h-[18px] px-1 flex items-center justify-center rounded-full text-[10px] font-bold bg-white/20 text-white">
                                        {selectedIds.length}
                                    </span>
                                )}
                            </button>
                        )}

                        {/* Top Bar Report New Issue Button */}
                        {canReport && (
                            <button
                                type="button"
                                onClick={() => setReportDialogOpen(true)}
                                className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-foreground text-background text-xs font-semibold hover:bg-foreground/90 transition-colors shadow-2xs cursor-pointer"
                            >
                                <Plus className="size-3.5" /> Report New Issue
                            </button>
                        )}
                    </div>
                </div>

                {/* Main Issues Card Stream / Table */}
                <IssueList
                    projectId={project.id}
                    projectLocation={{
                        address: project.address,
                        city: project.city,
                        state: project.state,
                        zip: project.zip,
                        vzw_engineer_phone: project.vzw_engineer_phone,
                    }}
                    issues={issues}
                    canResolve={canResolve}
                    canComment={mayComment}
                    canReport={canReport}
                    onOpenReportDialog={() => setReportDialogOpen(true)}
                    statusFilter={statusFilter}
                    manager={project.manager}
                    fieldSupervisors={project.field_supervisors}
                    selectedIssueId={selectedIssueId}
                    onSelectIssue={(issue) => setSelectedIssueId(issue ? issue.id : null)}
                    selectable={Boolean(canForward && selectedIssueId === null)}
                    selectedIds={selectedIds}
                    onSelectionChange={handleSelectionChange}
                />

                {/* Report New Issue Modal Dialog */}
                {canReport && (
                    <ReportIssueDialog
                        open={reportDialogOpen}
                        onOpenChange={setReportDialogOpen}
                        projectId={project.hash_id || project.id}
                    />
                )}

                {/* Forward Issues to Customer Dialog */}
                {canForward && (
                    <ForwardIssuesToCustomerDialog
                        open={forwardDialogOpen}
                        onOpenChange={setForwardDialogOpen}
                        projectId={project.hash_id || project.id}
                        selectedIssueIds={selectedIds}
                        customers={customers}
                        onSuccess={() => setSelectedIds([])}
                    />
                )}
            </div>
        </AppLayout>
    );
}

export default ProjectsIssues;
