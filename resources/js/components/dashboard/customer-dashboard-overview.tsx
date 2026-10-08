import { DashboardStats } from '@/components/dashboard/dashboard-overview';
import { LightboxPhoto, PhotoLightbox } from '@/components/shared/photo-lightbox';
import { StatsCard } from '@/components/shared/stats-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Camera,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Clock3,
    FolderKanban,
    LayoutDashboard,
    PackageCheck,
} from 'lucide-react';
import * as React from 'react';

interface CustomerDashboardOverviewProps {
    stats: DashboardStats;
    userName?: string;
}

// Shared polish applied to the section panels: a hairline accent rule along the
// top edge, a softer corner, and a calm hover lift. One hue (chart-2) keeps the
// palette coherent instead of introducing a rainbow of gradients.
const CARD_ACCENT =
    'relative overflow-hidden rounded-2xl transition-shadow duration-300 hover:shadow-md ' +
    'before:pointer-events-none before:absolute before:inset-x-0 before:top-0 before:z-10 before:h-0.5 ' +
    'before:bg-gradient-to-r before:from-chart-2 before:to-transparent';

function getGreeting(hour: number): string {
    if (hour < 12) {
        return 'Good morning';
    }
    if (hour < 17) {
        return 'Good afternoon';
    }
    return 'Good evening';
}

function calculateOverviewMetrics(stats: DashboardStats) {
    const photoCoverage =
        stats.photosRequired > 0
            ? Math.min(100, Math.round((stats.photosUploaded / stats.photosRequired) * 100))
            : 0;
    const approvalRate =
        stats.totalPackages > 0
            ? Math.round((stats.packagesApproved / stats.totalPackages) * 100)
            : 0;
    const activeProjects = Math.max(0, stats.totalProjects - stats.completedProjects);

    return { photoCoverage, approvalRate, activeProjects };
}

export function CustomerDashboardOverview({
    stats,
    userName,
}: Readonly<CustomerDashboardOverviewProps>) {
    const firstName = userName?.split(' ')[0] ?? '';
    const { photoCoverage, approvalRate, activeProjects } = calculateOverviewMetrics(stats);
    const activeLabel = activeProjects === 1 ? 'Project in progress' : 'Projects in progress';

    return (
        <div className="space-y-5">
            <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-stone-900 via-stone-950 to-black px-6 py-8 text-white shadow-lg ring-1 ring-white/10 sm:px-9">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 1px 1px, white 1px, transparent 0)',
                        backgroundSize: '22px 22px',
                    }}
                />
                <div
                    aria-hidden
                    className="absolute -right-24 -top-32 size-80 rounded-full bg-[color-mix(in_srgb,var(--chart-2)_15%,transparent)] blur-3xl"
                />
                <div
                    aria-hidden
                    className="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"
                />
                <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-2xl space-y-3">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-chart-2">
                            <LayoutDashboard className="size-3.5" /> Project snapshot
                        </div>
                        <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            {getGreeting(new Date().getHours())}
                            {firstName ? `, ${firstName}` : ''}.
                        </h2>
                        <p className="max-w-xl text-sm leading-6 text-stone-300">
                            Here&apos;s the latest status across your projects, including progress,
                            field activity, and items that need your attention.
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:min-w-72">
                        <HeroMetric value={activeProjects} label={activeLabel} />
                        <HeroMetric value={`${approvalRate}%`} label="Packages approved" />
                    </div>
                </div>
            </section>

            {stats.totalProjects === 0 ? (
                <EmptyCustomerState />
            ) : (
                <CustomerDashboardContent stats={stats} photoCoverage={photoCoverage} />
            )}
        </div>
    );
}

interface CustomerDashboardContentProps {
    stats: DashboardStats;
    photoCoverage: number;
}

function CustomerDashboardContent({
    stats,
    photoCoverage,
}: Readonly<CustomerDashboardContentProps>) {
    const attentionCount = stats.customerAttention.length;
    const attentionDesc = attentionCount ? 'Customer action required' : 'You are all caught up';
    const projectCountLabel = `Across ${stats.totalProjects} project${stats.totalProjects === 1 ? '' : 's'}`;

    return (
        <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    title="Projects"
                    value={stats.totalProjects}
                    description={`${stats.completedProjects} completed`}
                    icon={<FolderKanban className="size-5" />}
                    accentClassName="bg-chart-3"
                    className="transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                />
                <StatsCard
                    title="Progress"
                    value={`${stats.avgProgress}%`}
                    description={projectCountLabel}
                    icon={<ArrowUpRight className="size-5" />}
                    accentClassName="bg-chart-2"
                    className="transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                />
                <StatsCard
                    title="Photos"
                    value={stats.photosUploaded}
                    description={`${photoCoverage}% uploaded`}
                    icon={<Camera className="size-5" />}
                    accentClassName="bg-sky-500"
                    className="transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                />
                <StatsCard
                    title="Needs your attention"
                    value={attentionCount}
                    description={attentionDesc}
                    icon={<CircleAlert className="size-5" />}
                    accentClassName="bg-amber-500"
                    className={attentionCount ? 'ring-1 ring-amber-400/50' : undefined}
                />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.35fr_0.65fr]">
                <Card className={CARD_ACCENT}>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-bold">My Projects</CardTitle>
                        <CardDescription>Current status across your projects.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {stats.customerProjects.map((project) => (
                            <Link
                                key={project.id}
                                href={project.href}
                                className="group flex items-center justify-between gap-4 rounded-lg border border-border/60 p-3 transition-colors hover:bg-muted/40"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-semibold">
                                        {project.name}
                                    </span>
                                    <span className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                        <StatusBadge status={project.status} /> {project.progress}%
                                        complete
                                    </span>
                                </span>
                                <ArrowUpRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                            </Link>
                        ))}
                    </CardContent>
                </Card>
                <AttentionCard items={stats.customerAttention} />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.35fr_0.65fr]">
                <Card className={CARD_ACCENT}>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-bold">Recent Project Activity</CardTitle>
                        <CardDescription>What has changed across your projects.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ActivityList items={stats.customerActivity} />
                    </CardContent>
                </Card>
                <FieldActivityCard field={stats.fieldActivity} />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.35fr_0.65fr]">
                <Card className={CARD_ACCENT}>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-bold">Recent Site Photos</CardTitle>
                        <CardDescription>
                            {stats.photosUploaded} photos uploaded across your projects.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PhotoGrid photos={stats.recentPhotos} />
                    </CardContent>
                </Card>
                <CustomerDeliverablesCard stats={stats} />
            </div>
        </>
    );
}

function FieldActivityCard({
    field,
}: Readonly<{ field: DashboardStats['fieldActivity'] }>) {
    // This count is technicians whose phone sent a location fix inside the active
    // window — not "signed in" and not proof anyone is physically on site. Say
    // "sharing location" so the number is not read as a headcount on the crew.
    if (field.technicians <= 0) {
        return (
            <Card className={CARD_ACCENT}>
                <CardHeader className="pb-3">
                    <CardTitle className="text-sm font-bold">Field Activity</CardTitle>
                    <CardDescription>Recent activity from the field.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No technicians are sharing their location right now.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const techLabel =
        field.technicians === 1
            ? '1 technician sharing location'
            : `${field.technicians} technicians sharing location`;

    return (
        <Card className={CARD_ACCENT}>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-bold">Field Activity</CardTitle>
                <CardDescription>Recent activity from the field.</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    <div className="flex items-start gap-3">
                        <span className="mt-1 flex size-8 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                            <span className="size-2 rounded-full bg-emerald-500" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold">{techLabel}</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {field.project ?? 'Across your projects'}
                            </p>
                        </div>
                    </div>
                    {field.href && (
                        <Link
                            href={field.href}
                            className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-xs font-semibold hover:bg-muted"
                        >
                            View project <ArrowUpRight className="size-3.5" />
                        </Link>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function HeroMetric({
    value,
    label,
}: Readonly<{ value: string | number; label: string }>) {
    return (
        <div className="rounded-lg border border-white/10 bg-white/[0.07] p-3 backdrop-blur-sm">
            <p className="text-2xl font-bold tabular-nums">{value}</p>
            <p className="mt-1 text-xs text-stone-300">{label}</p>
        </div>
    );
}

function EmptyCustomerState() {
    return (
        <Card className={CARD_ACCENT}>
            <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                <FolderKanban className="size-10 text-muted-foreground" />
                <div>
                    <h3 className="font-semibold">Your workspace is ready</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Project updates will appear here once work begins.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status }: Readonly<{ status: string }>) {
    let label = 'In progress';
    if (status === 'completed') {
        label = 'Completed';
    } else if (status === 'archived') {
        label = 'Archived';
    }

    return (
        <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
            {label}
        </Badge>
    );
}

function getAttentionDescription(count: number): string {
    if (count === 1) {
        return '1 item requires your review.';
    }
    if (count > 1) {
        return `${count} items require your review.`;
    }
    return 'You are all caught up.';
}

function AttentionCard({
    items,
}: Readonly<{ items: DashboardStats['customerAttention'] }>) {
    const [currentIndex, setCurrentIndex] = React.useState(0);
    const count = items.length;
    const hasItems = count > 0;

    const safeIndex = Math.min(currentIndex, Math.max(0, count - 1));
    const currentItem = hasItems ? items[safeIndex] : null;

    const handlePrev = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setCurrentIndex((prev) => (prev > 0 ? prev - 1 : count - 1));
    };

    const handleNext = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setCurrentIndex((prev) => (prev < count - 1 ? prev + 1 : 0));
    };

    return (
        <Card
            className={cn(
                CARD_ACCENT,
                'flex flex-col justify-between',
                hasItems && 'ring-1 ring-amber-400/40'
            )}
        >
            <div>
                <CardHeader className="pb-3">
                    <div className="flex items-center justify-between gap-2">
                        <CardTitle className="text-sm font-bold flex items-center gap-2">
                            <CircleAlert
                                className={cn(
                                    'size-4',
                                    hasItems ? 'text-amber-500' : 'text-muted-foreground'
                                )}
                            />
                            Needs Your Attention
                        </CardTitle>
                        {hasItems && (
                            <div className="flex items-center gap-1.5">
                                <Badge
                                    variant="outline"
                                    className="border-amber-500/40 bg-amber-500/10 text-[10px] font-bold text-amber-600 dark:text-amber-400"
                                >
                                    {safeIndex + 1} of {count}
                                </Badge>
                                {count > 1 && (
                                    <div className="flex items-center gap-1 ml-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={handlePrev}
                                            aria-label="Previous attention item"
                                            className="size-6 rounded-md hover:bg-amber-100 dark:hover:bg-amber-950/50 text-foreground"
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={handleNext}
                                            aria-label="Next attention item"
                                            className="size-6 rounded-md hover:bg-amber-100 dark:hover:bg-amber-950/50 text-foreground"
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                    <CardDescription>{getAttentionDescription(count)}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {currentItem ? (
                        <div className="space-y-3">
                            <Link
                                href={currentItem.href}
                                className="group block rounded-xl border border-amber-300/60 bg-amber-50/50 p-3.5 transition-all hover:border-amber-400 hover:bg-amber-50 dark:border-amber-500/20 dark:bg-amber-950/20 dark:hover:bg-amber-950/40 hover:shadow-xs"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                className="border-amber-500/40 bg-amber-500/10 text-[9px] font-semibold text-amber-700 dark:text-amber-300 px-1.5 py-0"
                                            >
                                                Change Request
                                            </Badge>
                                            <span className="truncate text-xs font-semibold text-muted-foreground">
                                                {currentItem.project}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm font-bold text-foreground group-hover:text-amber-700 dark:group-hover:text-amber-300 transition-colors">
                                            {currentItem.title}
                                        </p>
                                    </div>
                                    <span className="shrink-0 rounded-md bg-white/80 dark:bg-stone-900 px-2 py-1 text-xs font-bold tabular-nums text-foreground border border-border/40">
                                        {currentItem.amount}
                                    </span>
                                </div>
                                <div className="mt-3 flex items-center justify-between border-t border-amber-200/50 dark:border-amber-500/20 pt-2 text-xs font-semibold text-amber-700 dark:text-amber-400">
                                    <span>Awaiting your review & sign-off</span>
                                    <span className="flex items-center gap-1">
                                        Review{' '}
                                        <ArrowUpRight className="size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                    </span>
                                </div>
                            </Link>

                            {count > 1 && (
                                <div className="flex items-center justify-between px-1 text-xs text-muted-foreground">
                                    <div className="flex items-center gap-1.5">
                                        {items.map((it, idx) => (
                                            <button
                                                key={it.id}
                                                type="button"
                                                onClick={() => setCurrentIndex(idx)}
                                                aria-label={`Jump to item ${idx + 1}`}
                                                className={cn(
                                                    'h-1.5 rounded-full transition-all duration-300',
                                                    idx === safeIndex
                                                        ? 'w-5 bg-amber-500'
                                                        : 'w-1.5 bg-muted-foreground/30 hover:bg-muted-foreground/50'
                                                )}
                                            />
                                        ))}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={handlePrev}
                                            className="text-[11px] font-semibold text-foreground hover:text-amber-600 transition-colors"
                                        >
                                            ← Prev
                                        </button>
                                        <span>·</span>
                                        <button
                                            type="button"
                                            onClick={handleNext}
                                            className="text-[11px] font-semibold text-foreground hover:text-amber-600 transition-colors"
                                        >
                                            Next →
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="flex items-center gap-2.5 rounded-xl border border-border/50 bg-muted/20 p-4 text-xs font-medium text-muted-foreground">
                            <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>
                                No change requests or deliverables are currently waiting for your
                                review.
                            </span>
                        </div>
                    )}
                </CardContent>
            </div>
            {hasItems && (
                <div className="border-t border-border/60 p-3 bg-muted/10 rounded-b-2xl">
                    <Link
                        href={route('change-orders.index')}
                        className="flex items-center justify-between text-xs font-semibold text-foreground hover:text-primary transition-colors px-1"
                    >
                        <span>View all change requests ({count})</span>
                        <ArrowUpRight className="size-3.5" />
                    </Link>
                </div>
            )}
        </Card>
    );
}

function ActivityList({
    items,
}: Readonly<{ items: DashboardStats['customerActivity'] }>) {
    return items.length ? (
        <div className="space-y-0">
            {items.map((item) => (
                <div
                    key={item.id}
                    className="flex gap-3 border-b border-border/60 py-3 last:border-0"
                >
                    <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
                    <div className="min-w-0">
                        <p className="text-sm font-medium">{item.label}</p>
                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                            {item.project} · {item.meta}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    ) : (
        <p className="text-sm text-muted-foreground">No recent updates yet.</p>
    );
}

function PhotoGrid({
    photos,
}: Readonly<{ photos: DashboardStats['recentPhotos'] }>) {
    const [lightboxIndex, setLightboxIndex] = React.useState<number | null>(null);

    const lightboxPhotos: LightboxPhoto[] = React.useMemo(
        () =>
            photos.map((p) => ({
                id: p.id,
                url: p.full_url || p.url,
                alt: p.project,
                caption: p.project,
            })),
        [photos]
    );

    if (!photos.length) {
        return (
            <p className="text-sm text-muted-foreground">
                Site photos will appear here as field work progresses.
            </p>
        );
    }

    return (
        <>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {photos.map((photo, index) => (
                    <button
                        key={photo.id}
                        type="button"
                        onClick={() => setLightboxIndex(index)}
                        aria-label={`View photo from ${photo.project}`}
                        className="group relative aspect-square w-full overflow-hidden rounded-xl bg-muted ring-1 ring-border/60 text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    >
                        <img
                            src={photo.url}
                            alt={photo.project}
                            className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <div className="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20" />
                        <span className="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/75 via-black/40 to-transparent px-2.5 py-2 text-[11px] font-medium text-white">
                            {photo.project}
                        </span>
                    </button>
                ))}
            </div>

            {lightboxIndex !== null && (
                <PhotoLightbox
                    photos={lightboxPhotos}
                    index={lightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                />
            )}
        </>
    );
}

function getDeliverablesDescription(total: number, approved: number, percent: number): string {
    if (total === 1) {
        return `${approved} of 1 deliverable approved (${percent}%).`;
    }
    if (total > 1) {
        return `${approved} of ${total} deliverables approved (${percent}%).`;
    }
    return 'Status of close-out packages across your projects.';
}

function CustomerDeliverablesCard({ stats }: Readonly<{ stats: DashboardStats }>) {
    const total = stats.totalPackages;
    const approved = stats.packagesApproved;
    const underReview = stats.pendingApprovals;
    const inProgress = Math.max(0, total - approved - underReview);
    const approvalPercent = total > 0 ? Math.round((approved / total) * 100) : 0;

    return (
        <Card className={CARD_ACCENT + ' flex flex-col justify-between'}>
            <div>
                <CardHeader className="pb-3">
                    <CardTitle className="flex items-center gap-2 text-sm font-bold">
                        <PackageCheck className="size-4 text-primary" />
                        Close-Out Deliverables
                    </CardTitle>
                    <CardDescription>
                        {getDeliverablesDescription(total, approved, approvalPercent)}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-muted-foreground">
                                Overall Completion
                            </span>
                            <span className="font-bold tabular-nums">{approvalPercent}%</span>
                        </div>
                        <Progress value={approvalPercent} className="h-2" />
                    </div>

                    <div className="space-y-2 pt-1">
                        <div className="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-6 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-3.5" />
                                </span>
                                <div>
                                    <p className="font-semibold text-foreground">Fully Approved</p>
                                    <p className="text-[10px] text-muted-foreground">
                                        Verified & signed off
                                    </p>
                                </div>
                            </div>
                            <Badge
                                variant="outline"
                                className="border-emerald-500/30 bg-emerald-500/10 font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {approved}
                            </Badge>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-6 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                    <Clock3 className="size-3.5" />
                                </span>
                                <div>
                                    <p className="font-semibold text-foreground">Under Review</p>
                                    <p className="text-[10px] text-muted-foreground">
                                        Awaiting manager sign-off
                                    </p>
                                </div>
                            </div>
                            <Badge
                                variant="outline"
                                className="border-amber-500/30 bg-amber-500/10 font-bold text-amber-600 dark:text-amber-400"
                            >
                                {underReview}
                            </Badge>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-6 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                    <FolderKanban className="size-3.5" />
                                </span>
                                <div>
                                    <p className="font-semibold text-foreground">In Preparation</p>
                                    <p className="text-[10px] text-muted-foreground">
                                        Field work & photo capture
                                    </p>
                                </div>
                            </div>
                            <Badge variant="outline" className="font-bold text-muted-foreground">
                                {inProgress}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </div>
            <div className="border-t border-border/60 p-3 bg-muted/10 rounded-b-2xl">
                <Link
                    href={route('portal.index')}
                    className="flex items-center justify-between text-xs font-semibold text-foreground hover:text-primary transition-colors px-1"
                >
                    <span>View all project deliverables</span>
                    <ArrowUpRight className="size-3.5" />
                </Link>
            </div>
        </Card>
    );
}

export default CustomerDashboardOverview;
