import { PackageCreatorTag, type PackageCreator } from '@/components/packages/package-creator-tag';
import { PendingFeedbackFlag } from '@/components/packages/pending-feedback-flag';
import { PackageLockButton } from '@/components/packages/package-lock-button';
import { PackageLockMeta } from '@/components/packages/package-lock-notice';
import { PackageStatusFilter } from '@/components/packages/package-status-filter';
import { EmptyCard } from '@/components/shared/empty-card';
import { LockedBadge } from '@/components/shared/locked-badge';
import { StatusBadge } from '@/components/shared/status-badge';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { UseFavoriteDialog } from '@/components/templates/use-favorite-dialog';
import type { FavoriteOption } from '@/components/templates/favorite-types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { packageStatusLabel } from '@/lib/package-statuses';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Bookmark,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Eye,
    FolderKanban,
    Lock,
    Plus,
    Trash2,
} from 'lucide-react';
import * as React from 'react';

export interface PackageListItem {
    id: number;
    hash_id?: string;
    title: string;
    revision_number: string;
    status: string;
    created_at?: string;
    current_stage?: string;
    template?: { id: number; name: string } | null;
    can_delete?: boolean;
    locked?: boolean;
    locked_by?: { id: number; name: string; profile_photo_url?: string | null } | null;
    /** Who created the package — managers and technicians can both create. */
    creator?: PackageCreator | null;
    locked_at?: string | null;
    /** Backend-computed via CloseoutPackagePolicy::view — the source of truth. */
    can_open?: boolean;
    can_lock?: boolean;
    can_unlock?: boolean;
    /** Customer-submitted feedback rounds still awaiting a reply (backend aggregate). */
    pending_feedback_count?: number;
}

export interface PaginatedPackages {
    data: PackageListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export type PackagesIndexPageProps = PageProps<{
    project: {
        id: number;
        hash_id?: string;
        name: string;
        ps_number?: string | null;
    };
    packages: PaginatedPackages | PackageListItem[];
    status?: string | null;
    favorites?: FavoriteOption[];
    /** Backend-computed via CloseoutPackagePolicy::create — the source of truth. */
    can_create?: boolean;
}>;

export function PackagesIndex() {
    useFlashToasts();

    const { project, packages: rawPackages, can_create, favorites, status } =
        usePage<PackagesIndexPageProps>().props;
    const packagesList = Array.isArray(rawPackages) ? rawPackages : (rawPackages?.data ?? []);
    const pagination = Array.isArray(rawPackages) ? null : rawPackages;
    // Driven by the backend create() policy: only the project's assigned
    // manager or field technician may create — admin is intentionally excluded.
    const canCreate = can_create === true;
    const [deleteTarget, setDeleteTarget] = React.useState<PackageListItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const [favoritesOpen, setFavoritesOpen] = React.useState(false);

    const handleDelete = () => {
        if (!deleteTarget) return;
        setIsDeleting(true);
        router.delete(route('packages.destroy', deleteTarget.hash_id || deleteTarget.id), {
            onFinish: () => {
                setIsDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const handleStatusChange = (value: string) => {
        router.get(
            route('packages.index', project.hash_id || project.id),
            value ? { status: value } : {},
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const goToPage = (page: number) => {
        router.get(
            route('packages.index', project.hash_id || project.id),
            { ...(status ? { status } : {}), page },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    return (
        <AppLayout
            title={`Close-Out Packages - ${project.name}`}
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', project.hash_id || project.id) },
                { label: 'Close-Out Packages' },
            ]}
        >
            <Head title={`Close-Out Packages - ${project.name}`} />

            <div className="space-y-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button asChild variant="ghost" size="sm" className="gap-1.5 text-xs">
                        <Link href={route('projects.show', project.hash_id || project.id)}>
                            <ArrowLeft className="size-4" /> Back to Project Details
                        </Link>
                    </Button>
                    <div className="flex items-center gap-2 flex-wrap">
                        <PackageStatusFilter value={status ?? null} onChange={handleStatusChange} />
                        {canCreate && (
                            <>
                                {(favorites?.length ?? 0) > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-2 text-xs"
                                        onClick={() => setFavoritesOpen(true)}
                                    >
                                        <Bookmark className="size-4" /> Use Favorite
                                    </Button>
                                )}
                                <Button asChild size="sm" className="gap-2 text-xs">
                                    <Link
                                        href={route('packages.create', project.hash_id || project.id)}
                                    >
                                        <Plus className="size-4" /> Create Package
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {packagesList.length === 0 ? (
                    <EmptyCard
                        title={
                            status ? 'No packages match this filter' : 'No Close-Out Packages'
                        }
                        description={
                            status
                                ? `No "${packageStatusLabel(status)}" packages exist for this site yet.`
                                : `No close-out package has been created for site "${project.name}" yet.`
                        }
                        icon={<FolderKanban className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <>
                        <Card className="shadow-2xs overflow-hidden">
                            <CardContent className="p-0">
                                <div className="divide-y divide-border/60 text-xs">
                                    {packagesList.map((pkg) => (
                                        <div
                                            key={pkg.id}
                                            className={cn(
                                                'flex flex-col sm:flex-row sm:items-center justify-between p-4 gap-3 transition-colors',
                                                pkg.locked ? 'cop-locked-stripes' : 'hover:bg-muted/20',
                                                pkg.locked && !pkg.can_open && 'cursor-not-allowed'
                                            )}
                                        >
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="font-bold text-foreground text-sm">
                                                        {pkg.title || `Package #${pkg.id}`}
                                                    </span>
                                                    <span className="font-bold text-muted-foreground bg-muted/60 px-2 py-0.5 rounded text-[10px]">
                                                        Rev {pkg.revision_number}
                                                    </span>
                                                    <StatusBadge status={pkg.status} />
                                                    {pkg.locked && <LockedBadge />}
                                                    <PendingFeedbackFlag
                                                        count={pkg.pending_feedback_count}
                                                    />
                                                </div>
                                                <div className="flex items-center gap-4 text-muted-foreground text-[11px] flex-wrap">
                                                    {pkg.template && (
                                                        <span>
                                                            Template:{' '}
                                                            <strong>{pkg.template.name}</strong>
                                                        </span>
                                                    )}
                                                    {pkg.current_stage && (
                                                        <span>
                                                            Stage:{' '}
                                                            <strong className="uppercase">
                                                                {pkg.current_stage.replace('_', ' ')}
                                                            </strong>
                                                        </span>
                                                    )}
                                                    {pkg.created_at && (
                                                        <span className="flex items-center gap-1">
                                                            <Calendar className="size-3" />{' '}
                                                            {pkg.created_at.slice(0, 10)}
                                                        </span>
                                                    )}
                                                    <PackageCreatorTag creator={pkg.creator} />
                                                </div>
                                                {pkg.locked && (
                                                    <PackageLockMeta
                                                        locked_by={pkg.locked_by}
                                                        locked_at={pkg.locked_at}
                                                        className="flex pt-0.5"
                                                    />
                                                )}
                                            </div>

                                            <div className="flex items-center gap-2 shrink-0">
                                                {pkg.locked && !pkg.can_open ? (
                                                    <button
                                                        type="button"
                                                        disabled
                                                        aria-disabled="true"
                                                        className="inline-flex h-8 cursor-not-allowed items-center gap-1.5 rounded-full border border-border bg-muted px-3 text-xs font-medium text-muted-foreground"
                                                    >
                                                        <Lock className="size-3.5" /> Locked Package
                                                    </button>
                                                ) : (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8 text-xs gap-1.5"
                                                    >
                                                        <Link
                                                            href={route(
                                                                'packages.show',
                                                                pkg.hash_id || pkg.id
                                                            )}
                                                        >
                                                            <Eye className="size-3.5" /> Open Package
                                                        </Link>
                                                    </Button>
                                                )}
                                                {(pkg.can_lock || pkg.can_unlock) && (
                                                    <PackageLockButton
                                                        packageId={pkg.hash_id || pkg.id}
                                                        locked={Boolean(pkg.locked)}
                                                        canLock={Boolean(pkg.can_lock)}
                                                        canUnlock={Boolean(pkg.can_unlock)}
                                                    />
                                                )}
                                                {pkg.can_delete && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setDeleteTarget(pkg)}
                                                        className="h-8 text-xs gap-1.5 text-destructive hover:bg-destructive/10"
                                                        aria-label={`Delete ${pkg.title || 'Package #' + pkg.id}`}
                                                    >
                                                        <Trash2 className="size-3.5" /> Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {pagination && pagination.last_page > 1 && (
                            <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground flex-wrap">
                                <span>
                                    Showing {pagination.from ?? 0}–{pagination.to ?? 0} of{' '}
                                    {pagination.total}
                                </span>
                                <div className="flex items-center gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 gap-1 text-xs"
                                        disabled={pagination.current_page <= 1}
                                        onClick={() => goToPage(pagination.current_page - 1)}
                                    >
                                        <ChevronLeft className="size-3.5" /> Previous
                                    </Button>
                                    <span className="px-1 font-medium">
                                        Page {pagination.current_page} of {pagination.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 gap-1 text-xs"
                                        disabled={pagination.current_page >= pagination.last_page}
                                        onClick={() => goToPage(pagination.current_page + 1)}
                                    >
                                        Next <ChevronRight className="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            <UseFavoriteDialog
                open={favoritesOpen}
                onOpenChange={setFavoritesOpen}
                favorites={favorites || []}
                projectId={project.id}
            />

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteTarget(null);
                }}
                title="Delete package"
                description={`Delete "${deleteTarget?.title || (deleteTarget ? 'Package #' + deleteTarget.id : '')}"? It will be moved to the deleted packages list.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default PackagesIndex;
