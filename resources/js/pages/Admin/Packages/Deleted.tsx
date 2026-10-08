import * as React from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { PageProps } from '@/types/inertia';
import { Activity, FileText, RotateCcw, Trash2 } from 'lucide-react';

export interface DeletedPackageItem {
    id: number;
    status: string;
    revision_major: number;
    revision_minor: number;
    current_stage?: string | null;
    deleted_at?: string | null;
    project?: { id: number; name: string } | null;
    template?: { id: number; name: string } | null;
    [key: string]: unknown;
}

export interface DeletedPackagesMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export type AdminDeletedPackagesPageProps = PageProps<{
    packages: {
        data: DeletedPackageItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
}>;

const NOWRAP = 'whitespace-nowrap';

export function AdminDeletedPackages() {
    useFlashToasts();

    const { packages } = usePage<AdminDeletedPackagesPageProps>().props;
    const [purgeTarget, setPurgeTarget] = React.useState<DeletedPackageItem | null>(null);
    const [isPurging, setIsPurging] = React.useState(false);
    const [restoreTarget, setRestoreTarget] = React.useState<DeletedPackageItem | null>(null);
    const [isRestoring, setIsRestoring] = React.useState(false);

    const handlePurge = () => {
        if (!purgeTarget) return;
        setIsPurging(true);
        router.delete(route('admin.packages.purge', purgeTarget.id), {
            onSuccess: () => {
                setPurgeTarget(null);
                setIsPurging(false);
            },
            onError: () => setIsPurging(false),
        });
    };

    const handleRestore = () => {
        if (!restoreTarget) return;
        setIsRestoring(true);
        router.post(route('admin.packages.restore', restoreTarget.id), {}, {
            onSuccess: () => {
                setRestoreTarget(null);
                setIsRestoring(false);
            },
            onError: () => setIsRestoring(false),
        });
    };

    if (!packages) {
        return (
            <AppLayout title="Deleted Packages" breadcrumb={[{ label: 'Deleted Packages' }]}>
                <Head title="Deleted Packages" />
                <EmptyCard
                    title="Deleted packages unavailable"
                    description="The deleted package list could not be loaded."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    const columns: ColumnDef<DeletedPackageItem>[] = [
        {
            accessorKey: 'project',
            header: 'Package',
            cell: (row) => (
                <div className="flex items-center gap-2 min-w-0">
                    <FileText className="size-4 text-primary shrink-0" />
                    <span className="font-bold text-foreground truncate" title={row.project?.name ?? undefined}>
                        {row.project?.name ?? `Package #${row.hash_id || row.id}`}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'template',
            header: 'Template',
            cell: (row) => (
                <span
                    className="text-muted-foreground truncate inline-block max-w-[200px] align-middle"
                    title={row.template?.name ?? undefined}
                >
                    {row.template?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            className: NOWRAP,
            cell: (row) => (
                <Badge variant="secondary" className="capitalize text-[10px]">
                    {String(row.status).replace(/_/g, ' ')}
                </Badge>
            ),
        },
        {
            accessorKey: 'revision_major',
            header: 'Revision',
            className: NOWRAP,
            cell: (row) => (
                <span className="text-muted-foreground">
                    R{row.revision_major}.{row.revision_minor}
                </span>
            ),
        },
        {
            accessorKey: 'deleted_at',
            header: 'Deleted At',
            className: NOWRAP,
            cell: (row) => (
                <span className={`text-muted-foreground ${NOWRAP}`}>
                    {row.deleted_at ? new Date(String(row.deleted_at)).toLocaleString() : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'id',
            header: 'Actions',
            className: `text-right ${NOWRAP}`,
            cell: (row) => (
                <div className="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setRestoreTarget(row)}
                        className="h-7 text-[11px] gap-1 text-emerald-600 hover:bg-emerald-500/10"
                    >
                        <RotateCcw className="size-3" /> Restore
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setPurgeTarget(row)}
                        className="h-7 text-[11px] gap-1 text-destructive hover:bg-destructive/10"
                    >
                        <Trash2 className="size-3" /> Purge
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Deleted Packages" breadcrumb={[{ label: 'Deleted Packages' }]}>
            <Head title="Deleted Packages" />
            <div className="space-y-2">
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
                    emptyTitle="No deleted packages"
                    emptyDescription="Soft-deleted packages will appear here for restore or permanent purge."
                />
            </div>

            <ConfirmDialog
                open={Boolean(purgeTarget)}
                onOpenChange={(open) => {
                    if (!open && !isPurging) setPurgeTarget(null);
                }}
                title="Purge package permanently"
                description={`Permanently delete the package for "${purgeTarget?.project?.name}"? This cannot be undone.`}
                confirmLabel="Purge"
                variant="destructive"
                loading={isPurging}
                onConfirm={handlePurge}
            />

            <ConfirmDialog
                open={Boolean(restoreTarget)}
                onOpenChange={(open) => {
                    if (!open && !isRestoring) setRestoreTarget(null);
                }}
                title="Restore package"
                description={`Restore the package for "${restoreTarget?.project?.name}"? It will return to its previous state.`}
                confirmLabel="Restore"
                loading={isRestoring}
                onConfirm={handleRestore}
            />
        </AppLayout>
    );
}

export default AdminDeletedPackages;
