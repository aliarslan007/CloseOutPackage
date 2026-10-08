import { Link } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { StatusBadge } from '@/components/shared/status-badge';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@/components/shared/data-table';
import { SupervisorAvatar } from '@/features/media-library/components/SupervisorFolderCard';
import { formatRoleLabel } from '@/features/media-library/utils/format-uploader';
import { AlertCircle, Building2, FileSpreadsheet, FolderKanban, MoreVertical, Pencil, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

/** An assigned user, as serialized on the project list (User appends photo + role). */
export interface ProjectMember {
    id: number;
    name: string;
    profile_photo_url?: string | null;
    role_name?: string | null;
}

export interface ProjectCustomer {
    id: number;
    name: string;
    email?: string | null;
    profile_photo_url?: string | null;
    company?: string | null;
}

export interface ProjectListItem {
    id: number;
    hash_id?: string;
    name: string;
    ps_number?: string | null;
    status: string;
    progress_percentage: number;
    market?: { id: number; name: string } | null;
    customer?: { id: number; name: string; code?: string } | null;
    customer_engineer?: {
        id: number;
        name: string;
        email?: string | null;
        user?: { id: number; name: string; profile_photo_url?: string | null } | null;
    } | null;
    customer_accesses?: Array<{
        id: number;
        user?: {
            id: number;
            name: string;
            email?: string | null;
            profile_photo_url?: string | null;
        } | null;
    }>;
    manager?: ProjectMember | null;
    field_supervisors?: ProjectMember[];
    manager_id?: number | null;
    /** The project's primary field technician — always one of field_supervisors. */
    primary_supervisor_id?: number | null;
    package_statuses?: Record<string, number>;
    open_issues_count?: number;
    /** Packages on this project holding customer feedback nobody has answered. */
    pending_feedback_packages_count?: number;
    [key: string]: unknown;
}

const NOWRAP = 'whitespace-nowrap';

const PACKAGE_STATUS_META: Record<string, { label: string; chipClass: string; priority: number }> = {
    submitted: {
        label: 'Submitted',
        chipClass: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
        priority: 0,
    },
    under_review: {
        label: 'In Review',
        chipClass: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
        priority: 1,
    },
    rejected: {
        label: 'Rejected',
        chipClass: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30',
        priority: 2,
    },
    draft: {
        label: 'Draft',
        chipClass: 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600',
        priority: 3,
    },
    approved: {
        label: 'Approved',
        chipClass: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
        priority: 4,
    },
    // Customer feedback & revision loop (PackageStatus post-approval cases).
    customer_review: {
        label: 'Customer Review',
        chipClass: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30',
        priority: 5,
    },
    feedback_received: {
        label: 'Feedback Received',
        chipClass: 'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30',
        priority: 6,
    },
    revision_in_progress: {
        label: 'Revision in Progress',
        chipClass: 'bg-violet-500/15 text-violet-700 dark:text-violet-300 border-violet-500/30',
        priority: 7,
    },
};

function PackageStatusChips({ statuses }: Readonly<{ statuses?: Record<string, number> }>) {
    const entries = Object.entries(statuses ?? {})
        .filter(([, count]) => count > 0)
        .sort(
            ([a], [b]) =>
                (PACKAGE_STATUS_META[a]?.priority ?? 99) - (PACKAGE_STATUS_META[b]?.priority ?? 99)
        );

    if (entries.length === 0) {
        return <span className="text-[11px] text-muted-foreground font-mono">—</span>;
    }

    return (
        <div className="flex items-center gap-1.5 whitespace-nowrap">
            {entries.map(([status, count]) => {
                const meta = PACKAGE_STATUS_META[status];
                return (
                    <span
                        key={status}
                        className={cn(
                            'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium border leading-tight shrink-0',
                            meta ? meta.chipClass : 'border-border bg-muted/60 text-muted-foreground'
                        )}
                    >
                        <span>{meta ? meta.label : status}</span>
                        <span className="font-semibold text-[10px] opacity-90 font-mono">
                            ({count})
                        </span>
                    </span>
                );
            })}
        </div>
    );
}

/** Avatars shown before collapsing the rest into a "+N" chip. */
const MAX_VISIBLE_MEMBERS = 4;

interface AssignedMember {
    id: number;
    name: string;
    roleName: string;
    photoUrl?: string | null;
}

/** Manager first, then field supervisors — the order of authority. */
function collectMembers(row: ProjectListItem): AssignedMember[] {
    const members: AssignedMember[] = [];

    if (row.manager?.id) {
        members.push({
            id: row.manager.id,
            name: row.manager.name,
            roleName: formatRoleLabel(row.manager.role_name ?? 'manager'),
            photoUrl: row.manager.profile_photo_url,
        });
    }

    for (const supervisor of row.field_supervisors ?? []) {
        if (!supervisor?.id) continue;
        members.push({
            id: supervisor.id,
            name: supervisor.name,
            roleName: formatRoleLabel(supervisor.role_name ?? 'field_supervisor'),
            photoUrl: supervisor.profile_photo_url,
        });
    }

    return members;
}

/** Collect assigned customer contacts or organization for the project. */
function collectCustomers(row: ProjectListItem): ProjectCustomer[] {
    const customers: ProjectCustomer[] = [];
    const seenIds = new Set<number>();

    // 1. Customer portal access users
    if (row.customer_accesses && Array.isArray(row.customer_accesses)) {
        for (const access of row.customer_accesses) {
            if (access.user?.id && !seenIds.has(access.user.id)) {
                seenIds.add(access.user.id);
                customers.push({
                    id: access.user.id,
                    name: access.user.name,
                    email: access.user.email,
                    profile_photo_url: access.user.profile_photo_url,
                    company: row.customer?.name ?? null,
                });
            }
        }
    }

    // 2. Customer engineer if assigned and not already collected
    if (customers.length === 0 && row.customer_engineer?.name) {
        customers.push({
            id: row.customer_engineer.id,
            name: row.customer_engineer.name,
            email: row.customer_engineer.email,
            profile_photo_url: row.customer_engineer.user?.profile_photo_url ?? null,
            company: row.customer?.name ?? null,
        });
    }

    // 3. Customer organization if no direct contact accounts
    if (customers.length === 0 && row.customer?.name) {
        customers.push({
            id: row.customer.id,
            name: row.customer.name,
            email: null,
            profile_photo_url: null,
            company: row.customer.code ?? null,
        });
    }

    return customers;
}

/**
 * Assigned customer(s) column: circle avatar/profile picture + name.
 */
function AssignedCustomers({ row }: Readonly<{ row: ProjectListItem }>) {
    const customers = collectCustomers(row);

    if (customers.length === 0) {
        return <span className="font-mono text-[10px] text-muted-foreground">—</span>;
    }

    if (customers.length === 1) {
        const customer = customers[0];
        return (
            <div
                className="flex items-center gap-1.5 min-w-0"
                title={customer.email ? `${customer.name} (${customer.email})` : customer.name}
            >
                <span className="relative inline-flex rounded-full ring-1 ring-border/80 shrink-0">
                    <SupervisorAvatar
                        name={customer.name}
                        photoUrl={customer.profile_photo_url}
                        size="xs"
                    />
                </span>
                <span className="text-xs font-medium text-foreground truncate max-w-[120px]">
                    {customer.name}
                </span>
            </div>
        );
    }

    const visible = customers.slice(0, 3);
    const hidden = customers.slice(3);

    return (
        <div className="flex items-center gap-1.5 min-w-0">
            <div className="flex -space-x-1.5 shrink-0">
                {visible.map((cust) => (
                    <span
                        key={cust.id}
                        title={cust.email ? `${cust.name} (${cust.email})` : cust.name}
                        className="relative inline-flex rounded-full ring-2 ring-card transition-transform hover:z-10 hover:-translate-y-px"
                    >
                        <SupervisorAvatar
                            name={cust.name}
                            photoUrl={cust.profile_photo_url}
                            size="xs"
                        />
                    </span>
                ))}
            </div>
            {hidden.length > 0 && (
                <span
                    title={hidden.map((c) => c.name).join('\n')}
                    className="inline-flex h-4 min-w-4 shrink-0 cursor-default items-center justify-center rounded-full border border-border bg-muted px-1 font-mono text-[9px] font-semibold text-muted-foreground tabular-nums"
                >
                    +{hidden.length}
                </span>
            )}
            <span
                className="text-xs font-medium text-foreground truncate max-w-[100px]"
                title={customers.map((c) => c.name).join(', ')}
            >
                {customers[0].name}
            </span>
        </div>
    );
}

/**
 * Overlapping avatar stack of everyone assigned to the project.
 *
 * A lane can carry 20+ assignees, so the stack caps at four faces and folds the
 * remainder into a "+N" chip — the row height never grows. Names surface on
 * hover via native tooltips: the table wrapper clips overflow, so a layered
 * popover would be cut off on the last rows, whereas tooltips never clip.
 */
function AssignedMembers({ row }: Readonly<{ row: ProjectListItem }>) {
    const members = collectMembers(row);

    if (members.length === 0) {
        return <span className="font-mono text-[10px] text-muted-foreground">—</span>;
    }

    const visible = members.slice(0, MAX_VISIBLE_MEMBERS);
    const hidden = members.slice(MAX_VISIBLE_MEMBERS);

    return (
        <div className="flex items-center gap-1.5">
            <div className="flex -space-x-1.5">
                {visible.map((member) => (
                    <span
                        key={member.id}
                        title={`${member.name} — ${member.roleName}`}
                        className="relative inline-flex rounded-full ring-2 ring-card transition-transform hover:z-10 hover:-translate-y-px"
                    >
                        <SupervisorAvatar
                            name={member.name}
                            photoUrl={member.photoUrl}
                            size="xs"
                        />
                    </span>
                ))}
            </div>
            {hidden.length > 0 && (
                <span
                    title={hidden.map((m) => `${m.name} — ${m.roleName}`).join('\n')}
                    aria-label={`${hidden.length} more assigned: ${hidden.map((m) => m.name).join(', ')}`}
                    className="inline-flex h-5 min-w-5 shrink-0 cursor-default items-center justify-center rounded-full border border-border bg-muted px-1 font-mono text-[10px] font-semibold text-muted-foreground tabular-nums"
                >
                    +{hidden.length}
                </span>
            )}
        </div>
    );
}

export interface ProjectTableColumnOptions {
    canEditProject: (project: ProjectListItem) => boolean;
    canDeleteProject?: (project: ProjectListItem) => boolean;
    canManageChangeOrders?: boolean;
    isAdmin?: boolean;
    isManager?: boolean;
    onDelete: (project: ProjectListItem) => void;
    onAssign: (project: ProjectListItem) => void;
}

export function buildProjectColumns({
    canEditProject,
    canDeleteProject,
    canManageChangeOrders,
    isAdmin = false,
    isManager = false,
    onDelete,
    onAssign,
}: Readonly<ProjectTableColumnOptions>): ColumnDef<ProjectListItem>[] {
    const canManageCO = canManageChangeOrders ?? (isAdmin || isManager);
    const canDeleteRow: (project: ProjectListItem) => boolean = canDeleteProject ?? (() => isAdmin);

    return [
        {
            accessorKey: 'name',
            header: 'Project',
            cell: (row) => {
                const hasPendingFeedback =
                    Number(row.pending_feedback_packages_count ?? 0) > 0 ||
                    Boolean(row.package_statuses?.feedback_received && row.package_statuses.feedback_received > 0);

                return (
                    <div className="flex items-start gap-1.5 min-w-0 max-w-[200px] lg:max-w-[240px]">
                        <Building2
                            className={cn(
                                'size-3.5 shrink-0 mt-0.5',
                                hasPendingFeedback ? 'text-amber-500 dark:text-amber-400' : 'text-primary'
                            )}
                        />
                        <div className="min-w-0 flex-1">
                            <p
                                className={cn(
                                    'text-xs leading-snug break-words',
                                    hasPendingFeedback
                                        ? 'font-bold feedback-title-shimmer text-amber-600 dark:text-amber-400'
                                        : 'font-semibold text-foreground'
                                )}
                                title={
                                    hasPendingFeedback
                                        ? `Customer feedback received (${row.pending_feedback_packages_count ?? 1} package(s) awaiting reply)`
                                        : undefined
                                }
                            >
                                {row.name}
                            </p>
                            <p className="text-[10px] text-muted-foreground truncate">
                                {row.market?.name ?? '—'}
                            </p>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'customer',
            header: 'Customer',
            className: NOWRAP,
            cell: (row) => <AssignedCustomers row={row} />,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            className: NOWRAP,
            cell: (row) => <StatusBadge status={row.status} />,
        },
        {
            accessorKey: 'progress_percentage',
            header: 'Progress',
            className: NOWRAP,
            cell: (row) => (
                <div className="flex items-center gap-1.5 w-16 sm:w-20">
                    <Progress value={row.progress_percentage} className="flex-1 h-1.5" />
                    <span className="text-[10px] font-medium text-muted-foreground shrink-0">
                        {row.progress_percentage}%
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'package_statuses',
            header: 'COP Packages',
            className: NOWRAP,
            cell: (row) => <PackageStatusChips statuses={row.package_statuses} />,
        },
        {
            accessorKey: 'field_supervisors',
            header: 'Team Members',
            className: NOWRAP,
            cell: (row) => <AssignedMembers row={row} />,
        },
        {
            accessorKey: 'id',
            header: 'Actions',
            className: `text-right ${NOWRAP}`,
            cell: (row) => (
                <div className="flex items-center justify-end gap-1">
                    {(isAdmin || isManager) && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onAssign(row)}
                            className="h-6.5 text-[10px] gap-1 px-1.5 border-border/80 bg-card text-foreground hover:bg-muted font-medium shrink-0"
                        >
                            Assign Members
                        </Button>
                    )}
                    <Button asChild variant="ghost" size="sm" className="h-6.5 text-[10px] gap-1 px-1.5 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 font-medium shrink-0">
                        <Link
                            href={route('packages.index', row.hash_id || row.id)}
                            title={`View all close-out packages for ${row.name}`}
                        >
                            <FolderKanban className="size-3 text-indigo-600 dark:text-indigo-400" /> COP
                        </Link>
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                aria-label="Project actions"
                                title="Project actions"
                                className="inline-flex items-center justify-center size-6.5 rounded-md border border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 hover:text-primary transition-all duration-150 shadow-2xs cursor-pointer focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0 font-medium"
                            >
                                <MoreVertical className="size-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48 text-xs">
                            {canEditProject(row) && (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route('projects.edit', row.hash_id || row.id)}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Pencil className="size-3.5 mr-2 text-muted-foreground" /> Edit Project
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem asChild>
                                <Link
                                    href={route('projects.issues.index', row.hash_id || row.id)}
                                    className="cursor-pointer gap-2"
                                >
                                    <AlertCircle className={cn('size-3.5 mr-2', Number(row.open_issues_count ?? 0) > 0 ? 'text-red-500' : 'text-amber-500')} />
                                    <span className="flex-1">Issues</span>
                                    {Number(row.open_issues_count ?? 0) > 0 && (
                                        <span className="ml-auto inline-flex items-center justify-center rounded-full bg-red-500/15 px-1.5 py-0.25 text-[10px] font-bold text-red-600 dark:text-red-400">
                                            {row.open_issues_count}
                                        </span>
                                    )}
                                </Link>
                            </DropdownMenuItem>
                            {canManageCO && (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route('projects.change-orders.index', row.hash_id || row.id)}
                                        className="cursor-pointer gap-2"
                                    >
                                        <FileSpreadsheet className="size-3.5 mr-2 text-sky-500" /> Change Orders
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            {canDeleteRow(row) && (
                                <DropdownMenuItem
                                    onClick={() => onDelete(row)}
                                    className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                >
                                    <Trash2 className="size-3.5 mr-2 text-destructive" /> Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];
}
