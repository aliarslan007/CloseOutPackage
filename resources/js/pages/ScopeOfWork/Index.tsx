import { EmptyCard } from '@/components/shared/empty-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SowArchivedSection } from '@/features/sow/components/index/sow-archived-section';
import { SowCard } from '@/features/sow/components/index/sow-card';
import { SowPipelineLane } from '@/features/sow/components/index/sow-pipeline-lane';
import { SowStatsBar } from '@/features/sow/components/index/sow-stats-bar';
import type { SowReport, SowStatus } from '@/features/sow/types/sow.types';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, Search, X } from 'lucide-react';
import * as React from 'react';

export interface SowIndexProps extends PageProps {
    reports: SowReport[];
    filters: { status: string | null };
    counts: Record<'all' | SowStatus, number>;
}

const PIPELINE_ORDER: SowStatus[] = ['draft', 'in_review', 'published'];

function matchesSearch(report: SowReport, query: string): boolean {
    const formattedId = `SOW-${String(report.id).padStart(4, '0')}`;
    const fields = [
        report.site_name,
        report.title,
        report.ps_number,
        report.site_address,
        report.market?.name,
        report.creator?.name,
        formattedId,
    ];

    return fields
        .filter((val): val is string => typeof val === 'string' && val.length > 0)
        .some((val) => val.toLowerCase().includes(query));
}

export function SowIndex({ reports, counts }: Readonly<SowIndexProps>) {
    useFlashToasts();
    const [search, setSearch] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | SowStatus>('all');

    const searched = React.useMemo(() => {
        const term = search.trim().toLowerCase();
        const byStatus =
            statusFilter === 'all'
                ? reports
                : reports.filter((report) => report.status === statusFilter);

        if (term === '') return byStatus;

        return byStatus.filter((report) => matchesSearch(report, term));
    }, [reports, search, statusFilter]);

    const laneReports = React.useCallback(
        (status: SowStatus): SowReport[] =>
            searched.filter((report) => report.status === status),
        [searched]
    );

    const archivedReports = React.useMemo(
        () => (statusFilter === 'all' || statusFilter === 'archived' ? laneReports('archived') : []),
        [statusFilter, laneReports]
    );

    const hasQuery = search.trim() !== '';

    // Render body based on empty states or active view
    let content: React.ReactNode;

    if (reports.length === 0) {
        content = (
            <EmptyCard
                icon={<FileText className="size-6" />}
                title="No SOW reports yet"
                description="Create your first Scope of Work report after a field survey."
                actionLabel="New SOW Report"
                onAction={() => router.visit(route('sow.reports.create'))}
            />
        );
    } else if (hasQuery && searched.length === 0) {
        content = (
            <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card p-10 text-center">
                <p className="text-sm font-semibold text-foreground">
                    No reports match “{search.trim()}”
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    Check your spelling or try searching by site name, PS number, or market.
                </p>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setSearch('')}
                    className="mt-4 h-8 text-xs cursor-pointer"
                >
                    Clear search query
                </Button>
            </div>
        );
    } else if (statusFilter === 'all') {
        // Full pipeline board
        content = (
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                    {PIPELINE_ORDER.map((status) => (
                        <SowPipelineLane
                            key={status}
                            status={status}
                            reports={laneReports(status)}
                        />
                    ))}
                </div>

                <SowArchivedSection reports={archivedReports} />
            </div>
        );
    } else {
        // Focused grid view when filtered to a single status
        content = (
            <div className="space-y-4">
                <div className="flex items-center justify-between rounded-xl border border-border/70 bg-muted/40 px-3.5 py-2 text-xs">
                    <span className="font-medium text-foreground">
                        Showing <strong className="font-mono">{searched.length}</strong> {statusFilter.replace('_', ' ')} reports
                    </span>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('all')}
                        className="text-xs font-medium text-primary hover:underline cursor-pointer"
                    >
                        Reset to all
                    </button>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {searched.map((report) => (
                        <SowCard key={report.id} report={report} />
                    ))}
                </div>
            </div>
        );
    }

    const headerToolbar = (
        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <div className="relative w-full sm:w-[220px] lg:w-[260px]">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search site, PS#, market…"
                    className="h-9 pr-9 pl-9 text-xs"
                />
                {hasQuery && (
                    <button
                        type="button"
                        onClick={() => setSearch('')}
                        aria-label="Clear search"
                        className="absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground cursor-pointer"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            <Button asChild size="sm" className="h-9 shrink-0 gap-1.5 text-xs font-semibold shadow-xs">
                <Link href={route('sow.reports.create')}>
                    <Plus className="size-4" />
                    New SOW Report
                </Link>
            </Button>
        </div>
    );

    return (
        <AppLayout
            title="Scope of Work"
            breadcrumb={[{ label: 'Scope of Work' }]}
            headerActions={headerToolbar}
        >
            <Head title="Scope of Work" />
            <div className="space-y-4">
                {reports.length > 0 && (
                    <SowStatsBar
                        counts={counts}
                        activeFilter={statusFilter}
                        onSelectFilter={setStatusFilter}
                    />
                )}

                {content}
            </div>
        </AppLayout>
    );
}

export default SowIndex;
