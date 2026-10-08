import { PackageStatusFilter } from '@/components/packages/package-status-filter';
import { PendingFeedbackFlag } from '@/components/packages/pending-feedback-flag';
import { ColumnDef, DataTable } from '@/components/shared/data-table';
import { EmptyCard } from '@/components/shared/empty-card';
import { LockedBadge } from '@/components/shared/locked-badge';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { packageStatusLabel } from '@/lib/package-statuses';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, FolderKanban } from 'lucide-react';
import * as React from 'react';

export interface GlobalPackageItem {
    id: number;
    hash_id?: string;
    title: string;
    status: string;
    submitted_at?: string | null;
    created_at?: string;
    project?: { id: number; hash_id?: string; name: string } | null;
    template?: { id: number; name: string } | null;
    locked?: boolean;
    /** Backend-computed via CloseoutPackagePolicy::view — the source of truth. */
    can_open?: boolean;
    can_lock?: boolean;
    can_unlock?: boolean;
    /** Customer-submitted feedback rounds still awaiting a reply (backend aggregate). */
    pending_feedback_count?: number;
    [key: string]: unknown;
}

export interface PaginatedPackages {
    data: GlobalPackageItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export type DashboardPackagesPageProps = PageProps<{
    packages: PaginatedPackages;
    status: string | null;
}>;

function submittedLabel(row: GlobalPackageItem): string {
    if (row.submitted_at) {
        return new Date(String(row.submitted_at)).toLocaleDateString();
    }
    if (row.created_at) {
        return `Created ${new Date(String(row.created_at)).toLocaleDateString()}`;
    }
    return '—';
}

const columns: ColumnDef<GlobalPackageItem>[] = [
    {
        accessorKey: 'title',
        header: 'Package',
        cell: (row) => (
            <div className="space-y-0.5">
                <span className="font-bold text-foreground">
                    {row.title || `Package #${row.hash_id || row.id}`}
                </span>
                {row.template && (
                    <div className="text-[11px] text-muted-foreground">
                        Template: {row.template.name}
                    </div>
                )}
                <PendingFeedbackFlag count={row.pending_feedback_count} />
            </div>
        ),
    },
    {
        accessorKey: 'project',
        header: 'Project',
        cell: (row) => (
            <div className="space-y-0.5">
                <span className="font-medium text-muted-foreground">
                    {row.project?.name ?? '—'}
                </span>
                {/* Under the name, never beside it: the column stays its own width. */}
                <PendingFeedbackFlag count={row.pending_feedback_count} />
            </div>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: (row) => (
            <div className="flex items-center gap-2">
                <StatusBadge status={row.status} />
                {row.locked && <LockedBadge />}
            </div>
        ),
    },
    {
        accessorKey: 'submitted_at',
        header: 'Submitted',
        cell: (row) => <span className="text-muted-foreground">{submittedLabel(row)}</span>,
    },
];

export function DashboardPackages() {
    useFlashToasts();

    const { packages, status } = usePage<DashboardPackagesPageProps>().props;

    const handleStatusChange = (value: string) => {
        router.get(
            route('dashboard.packages'),
            value ? { status: value } : {},
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const activeLabel = packageStatusLabel(status);

    return (
        <AppLayout
            title="All Packages"
            breadcrumb={[{ label: 'Dashboard', href: route('dashboard') }, { label: 'All Packages' }]}
            headerActions={
                <>
                    <Button asChild variant="ghost" size="sm" className="gap-1.5 text-xs">
                        <Link href={route('dashboard')}>
                            <ArrowLeft className="size-4" /> Back to Dashboard
                        </Link>
                    </Button>
                    <PackageStatusFilter value={status} onChange={handleStatusChange} />
                </>
            }
        >
            <Head title="All Packages" />

            <div className="space-y-2">
                {packages.total === 0 ? (
                    <EmptyCard
                        title="No packages here"
                        description={`No packages match the "${activeLabel}" filter in your scope.`}
                        icon={<FolderKanban className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={packages.data}
                        meta={{
                            current_page: packages.current_page,
                            last_page: packages.last_page,
                            per_page: packages.per_page,
                            total: packages.total,
                            from: packages.from,
                            to: packages.to,
                        }}
                        emptyTitle="No packages found"
                        emptyDescription="Try a different status filter."
                        rowClassName={(row) =>
                            row.locked
                                ? cn('cop-locked-stripes', !row.can_open && 'cursor-not-allowed')
                                : undefined
                        }
                        onRowClick={(row) => {
                            // A locked package stays listed but cannot be opened by
                            // anyone other than an admin or the user who locked it.
                            if (row.locked && !row.can_open) return;
                            router.visit(route('packages.show', row.hash_id || row.id));
                        }}
                    />
                )}
            </div>
        </AppLayout>
    );
}

export default DashboardPackages;
