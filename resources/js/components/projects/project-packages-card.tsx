import { PackageLockButton } from '@/components/packages/package-lock-button';
import { PackageLockMeta } from '@/components/packages/package-lock-notice';
import { LockedBadge } from '@/components/shared/locked-badge';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Calendar, ChevronLeft, ChevronRight, Eye, FileText, FolderKanban, Lock, Plus } from 'lucide-react';
import * as React from 'react';

export interface PackageItem {
    id: number;
    hash_id?: string;
    title: string;
    revision_number: string;
    status: string;
    created_at?: string;
    template?: { id: number; name: string } | null;
    locked?: boolean;
    locked_by?: { id: number; name: string } | null;
    locked_at?: string | null;
    /** Backend-computed via CloseoutPackagePolicy::view — the source of truth. */
    can_open?: boolean;
    can_lock?: boolean;
    can_unlock?: boolean;
}

export interface ProjectPackagesCardProps {
    projectId: number | string;
    packages?: PackageItem[];
    canCreatePackage?: boolean;
    isCustomer?: boolean;
}

const PACKAGES_PER_PAGE = 3;

export function ProjectPackagesCard({
    projectId,
    packages = [],
    canCreatePackage = false,
    isCustomer = false,
}: Readonly<ProjectPackagesCardProps>) {
    // Show 3 packages at a time so the card height stays constant no matter
    // how many packages the project has; extra packages page forward/back.
    //
    // Newest first: the most recently created package is always on page 1,
    // regardless of the order the server returned them in.
    const orderedPackages = React.useMemo(
        () => [...packages].sort((a, b) => b.id - a.id),
        [packages]
    );

    const [page, setPage] = React.useState(0);
    const pageCount = Math.max(1, Math.ceil(orderedPackages.length / PACKAGES_PER_PAGE));
    const safePage = Math.min(page, pageCount - 1);
    const visiblePackages = orderedPackages.slice(
        safePage * PACKAGES_PER_PAGE,
        safePage * PACKAGES_PER_PAGE + PACKAGES_PER_PAGE
    );
    const rangeStart = safePage * PACKAGES_PER_PAGE + 1;
    const rangeEnd = Math.min(
        safePage * PACKAGES_PER_PAGE + PACKAGES_PER_PAGE,
        orderedPackages.length
    );

    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2 text-sm font-bold">
                        <FolderKanban className="size-4 text-primary shrink-0" /> Close-Out Packages
                    </CardTitle>
                    <CardDescription className="text-xs">
                        The photo documentation for this site — one package per construction phase,
                        with its revision history.
                    </CardDescription>
                </div>
                {pageCount > 1 && (
                    <div className="flex shrink-0 items-center gap-1.5 self-end sm:self-auto">
                        <span className="text-[11px] font-bold text-foreground tabular-nums">
                            {rangeStart}–{rangeEnd} of {orderedPackages.length}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Previous packages"
                            title="Previous packages"
                            disabled={safePage === 0}
                            className="size-7 shrink-0 font-bold text-foreground shadow-2xs"
                            onClick={() => setPage((p) => Math.max(0, p - 1))}
                        >
                            <ChevronLeft className="size-4" strokeWidth={2.75} />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Next packages"
                            title="Next packages"
                            disabled={safePage >= pageCount - 1}
                            className="size-7 shrink-0 font-bold text-foreground shadow-2xs"
                            onClick={() => setPage((p) => Math.min(pageCount - 1, p + 1))}
                        >
                            <ChevronRight className="size-4" strokeWidth={2.75} />
                        </Button>
                    </div>
                )}
            </CardHeader>
            <CardContent className="flex flex-1 flex-col">
                {orderedPackages.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center rounded-lg border border-dashed border-border/80 bg-muted/20 p-4 sm:p-6 text-center">
                        <FileText className="mx-auto size-8 text-muted-foreground/60 mb-2" />
                        <h4 className="font-heading text-xs font-bold text-foreground">No Close-Out Package Yet</h4>
                        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto mt-1 mb-4">
                            Start cell-site construction photo capture by selecting an admin master template.
                        </p>
                        {canCreatePackage && (
                            <Button asChild size="sm" className="h-8 text-xs gap-1">
                                <Link href={route('packages.create', projectId)}>
                                    <Plus className="size-3.5" /> Create Close-Out Package
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-1 flex-col justify-evenly divide-y divide-border/60 rounded-lg border border-border/80 overflow-hidden bg-card">
                        {visiblePackages.map((pkg) => {
                            const openPackageRoute = isCustomer
                                ? route('portal.projects.show', { project: projectId, package: pkg.hash_id || pkg.id })
                                : route('packages.show', pkg.hash_id || pkg.id);

                            const isLocked = Boolean(pkg.locked);
                            const isLockedForUser = isLocked && !pkg.can_open;

                            return (
                                <div
                                    key={pkg.id}
                                    className={cn(
                                        'flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 p-3 sm:p-3.5 transition-colors',
                                        isLocked ? 'cop-locked-stripes' : 'hover:bg-muted/10',
                                        isLockedForUser && 'cursor-not-allowed'
                                    )}
                                >
                                    <div className="space-y-1.5 min-w-0 flex-1 w-full sm:w-auto">
                                        <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                            <span className="font-heading text-xs font-bold text-foreground break-words">
                                                {pkg.title || `Package #${pkg.hash_id || pkg.id}`}
                                            </span>
                                            <span className="font-mono text-[10px] font-bold text-muted-foreground bg-muted/60 px-1.5 py-0.5 rounded">
                                                Rev {pkg.revision_number}
                                            </span>
                                            <StatusBadge status={pkg.status} />
                                            {pkg.locked && <LockedBadge />}
                                        </div>
                                        <p className="text-[11px] text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1">
                                            {pkg.template && (
                                                <span>Template: <strong>{pkg.template.name}</strong></span>
                                            )}
                                            {pkg.created_at && (
                                                <span className="flex items-center gap-1">
                                                    <Calendar className="size-3" /> {pkg.created_at.slice(0, 10)}
                                                </span>
                                            )}
                                        </p>
                                        {isLocked && (
                                            <PackageLockMeta
                                                locked_by={pkg.locked_by}
                                                locked_at={pkg.locked_at}
                                                className="flex pt-0.5 flex-wrap"
                                            />
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1.5 shrink-0 self-end sm:self-center w-full sm:w-auto justify-end pt-2 sm:pt-0 border-t sm:border-t-0 border-border/40 sm:border-transparent">
                                        {isLockedForUser ? (
                                            <span className="inline-flex h-8 cursor-not-allowed items-center gap-1 rounded-md border border-border bg-muted px-2.5 text-[11px] font-medium text-muted-foreground">
                                                <Lock className="size-3" /> Locked Package
                                            </span>
                                        ) : (
                                            <Button
                                                asChild
                                                variant="default"
                                                size="sm"
                                                className="h-8 px-3 text-[11px] gap-1"
                                            >
                                                <Link href={openPackageRoute}>
                                                    <Eye className="size-3" /> Open Package
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
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default ProjectPackagesCard;
