import { LockedBadge } from '@/components/shared/locked-badge';
import { StatusBadge } from '@/components/shared/status-badge';
import { STAGE_LABELS } from '@/components/templates/template-types';
import { BuilderTab, BuilderTabs, BuilderTabKey } from '@/components/templates/builder-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { VisibilityChecklist } from '@/components/visibility/visibility-checklist';
import { VisibilityPackageStepper } from '@/components/visibility/visibility-package-stepper';
import { VisibilityPackageItem } from '@/components/visibility/visibility-project-types';
import { VisibilityStagePhotos } from '@/components/visibility/visibility-stage-photos';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Camera, ChevronDown, Eye, FolderKanban, Lock } from 'lucide-react';
import * as React from 'react';

const STAGE_ORDER = ['pre_construction', 'construction', 'post_completion'] as const;

const DELIVERED_STATUSES = new Set(['approved', 'completed']);

const ACTIVE_STATUSES = new Set(['draft', 'submitted', 'in_progress', 'under_review', 'in_review']);

function MetricCell({ label, value }: Readonly<{ label: string; value: string }>) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                {label}
            </p>
            <p
                className="mt-0.5 truncate text-xs font-semibold text-foreground"
                title={value || undefined}
            >
                {value || '—'}
            </p>
        </div>
    );
}

function PackageRow({ pkg }: Readonly<{ pkg: VisibilityPackageItem }>) {
    const [detailsOpen, setDetailsOpen] = React.useState(false);
    const [activeTab, setActiveTab] = React.useState<BuilderTabKey>(() => {
        const firstWithPhotos = pkg.stages?.find((s) => (s.photos?.length ?? 0) > 0);

        return (firstWithPhotos?.stage ?? STAGE_ORDER[0]) as BuilderTabKey;
    });

    const revision = `R${pkg.revision_major}.${pkg.revision_minor}`;
    const checklistAnswered = pkg.answered_checklist_responses ?? 0;
    const checklistTotal = pkg.checklist_responses_count ?? 0;
    const stageLabel = pkg.current_stage
        ? (STAGE_LABELS[pkg.current_stage as keyof typeof STAGE_LABELS] ??
          pkg.current_stage.replaceAll('_', ' '))
        : '—';

    const stageTabs: BuilderTab[] = STAGE_ORDER.map((stageKey) => {
        const stage = pkg.stages?.find((s) => s.stage === stageKey);

        return {
            key: stageKey,
            label:
                STAGE_LABELS[stageKey as keyof typeof STAGE_LABELS] ??
                stageKey.replaceAll('_', ' '),
            count: stage?.photos?.length ?? 0,
        };
    });

    const checklistTab: BuilderTab | null =
        (pkg.checklist_responses?.length ?? 0) > 0
            ? { key: 'checklist', label: 'Checklist', count: checklistTotal }
            : null;

    const tabs = checklistTab ? [...stageTabs, checklistTab] : stageTabs;

    const activeStage = pkg.stages?.find((s) => s.stage === activeTab);
    const hasStages = (pkg.stages?.length ?? 0) > 0;

    return (
        <div
            className={cn(
                'px-4 py-4 sm:px-5 transition-colors',
                pkg.locked && 'cop-locked-stripes',
                detailsOpen ? 'bg-muted/20' : 'hover:bg-muted/30'
            )}
        >
            {/* Identity vs actions */}
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div className="flex flex-wrap items-center gap-2 min-w-0">
                    <p className="font-heading text-[13px] font-bold text-foreground">
                        Package #{pkg.hash_id}
                    </p>
                    <Badge variant="secondary" className="text-[10px] font-medium">
                        {revision}
                    </Badge>
                    <StatusBadge status={pkg.status} />
                    {pkg.locked && <LockedBadge />}
                </div>
                <div className="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        onClick={() => setDetailsOpen((prev) => !prev)}
                        aria-expanded={detailsOpen}
                        className="h-7 text-[11px] gap-1 px-2.5"
                    >
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition-transform duration-200',
                                detailsOpen && 'rotate-180'
                            )}
                        />
                        {detailsOpen ? 'Hide Details' : 'Details'}
                    </Button>
                    {pkg.locked && !pkg.can_open ? (
                        <span className="inline-flex h-7 cursor-not-allowed items-center gap-1 rounded-full border border-border bg-muted px-2 text-[11px] font-medium text-muted-foreground">
                            <Lock className="size-3" /> Locked Package
                        </span>
                    ) : (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="h-7 text-[11px] gap-1 px-2"
                        >
                            <Link href={route('packages.show', pkg.hash_id)}>
                                <Eye className="size-3" /> Open Package
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {/* Progress signal */}
            <VisibilityPackageStepper
                stages={pkg.stages}
                checklistAnswered={checklistAnswered}
                checklistTotal={checklistTotal}
                className="mt-3"
            />

            {/* Metrics line */}
            <div className="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-border/60 pt-3 sm:grid-cols-4">
                <MetricCell label="Current Stage" value={stageLabel} />
                <MetricCell label="Template" value={pkg.template?.name ?? '—'} />
                <MetricCell
                    label="Photos"
                    value={typeof pkg.photos_count === 'number' ? String(pkg.photos_count) : '—'}
                />
                <MetricCell
                    label="Checklist"
                    value={
                        checklistTotal > 0
                            ? `${checklistAnswered}/${checklistTotal} answered`
                            : 'No items'
                    }
                />
            </div>

            {/* Drill-in: one tab per stage, opened on demand */}
            {detailsOpen && (
                <div className="mt-4 space-y-3 border-t border-border/60 pt-4">
                    {hasStages ? (
                        <>
                            <BuilderTabs
                                tabs={tabs}
                                active={activeTab}
                                onChange={(tab) => setActiveTab(tab)}
                            />
                            {activeTab === 'checklist' ? (
                                <VisibilityChecklist items={pkg.checklist_responses} />
                            ) : (
                                <div
                                    role="tabpanel"
                                    aria-label={
                                        STAGE_LABELS[activeTab as keyof typeof STAGE_LABELS] ??
                                        activeTab
                                    }
                                >
                                    <VisibilityStagePhotos
                                        stage={activeTab}
                                        photos={activeStage?.photos ?? []}
                                    />
                                </div>
                            )}
                        </>
                    ) : (
                        <VisibilityChecklist items={pkg.checklist_responses} />
                    )}
                </div>
            )}
        </div>
    );
}

export interface VisibilityPackagesCardProps {
    packages: VisibilityPackageItem[];
}

export function VisibilityPackagesCard({ packages }: Readonly<VisibilityPackagesCardProps>) {
    const total = packages.length;
    const delivered = packages.filter((pkg) => DELIVERED_STATUSES.has(pkg.status)).length;
    const inProgress = packages.filter((pkg) => ACTIVE_STATUSES.has(pkg.status)).length;
    const photos = packages.reduce((sum, pkg) => sum + (pkg.photos_count ?? 0), 0);

    return (
        <Card className="shadow-2xs overflow-hidden">
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
                    <CardTitle className="flex items-center gap-2 text-sm font-bold">
                        <FolderKanban className="size-4 text-primary" />
                        Site Progress
                        <span className="font-normal text-xs text-muted-foreground">
                            {total} close-out package{total === 1 ? '' : 's'}
                        </span>
                    </CardTitle>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                            {delivered} delivered
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-sky-500" />
                            {inProgress} in progress
                        </span>
                        <span className="flex items-center gap-1.5">
                            <Camera className="size-3.5" />
                            {photos} photo{photos === 1 ? '' : 's'}
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {total === 0 ? (
                    <p className="border-t border-border/70 px-5 py-5 text-sm text-muted-foreground">
                        No close-out packages created for this site yet. They appear here as field
                        crews build them.
                    </p>
                ) : (
                    <div className="divide-y divide-border/70 border-t border-border/70">
                        {packages.map((pkg) => (
                            <PackageRow key={pkg.id} pkg={pkg} />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
