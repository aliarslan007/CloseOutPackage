import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { FileSpreadsheet, RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PortalEmptyState } from './components/PortalEmptyState';
import { PortfolioBadge, PortfolioHealth } from './components/PortalHeader';
import { PortalKpiCards } from './components/PortalKpiCards';
import { PortalProjectCard } from './components/PortalProjectCard';
import { PortalProjectListItem } from './components/PortalProjectListItem';
import { PortalToolbar } from './components/PortalToolbar';
import {
    CustomerProject,
    CustomerPortalProps,
    PortalMetrics,
    PortalSortOption,
    PortalStatusFilter,
    PortalViewMode,
} from './portal.types';

function progressOf(project: CustomerProject): number {
    return project.progress_percentage ?? 0;
}

function fullyApproved(project: CustomerProject): boolean {
    const total = project.total_packages_count ?? 0;
    return total > 0 && (project.approved_packages_count ?? 0) === total;
}

function matchesSearch(project: CustomerProject, query: string): boolean {
    if (!query) return true;
    const q = query.toLowerCase();
    const nameMatch = project.name.toLowerCase().includes(q);
    const psMatch = (project.ps_number ?? '').toLowerCase().includes(q);
    const marketMatch = (project.market?.name ?? '').toLowerCase().includes(q);
    const addressMatch = (project.address ?? '').toLowerCase().includes(q);
    const cityMatch = (project.city ?? '').toLowerCase().includes(q);

    return nameMatch || psMatch || marketMatch || addressMatch || cityMatch;
}

function matchesStatus(project: CustomerProject, status: PortalStatusFilter): boolean {
    switch (status) {
        case 'in_progress':
            return progressOf(project) > 0 && progressOf(project) < 100;
        case 'approved':
            return fullyApproved(project);
        case 'attention':
            return (project.rework_packages_count ?? 0) > 0;
        case 'all':
        default:
            return true;
    }
}

function sortProjects(projects: CustomerProject[], sortBy: PortalSortOption): CustomerProject[] {
    const copy = [...projects];

    return copy.sort((a, b) => {
        if (sortBy === 'progress_desc') return progressOf(b) - progressOf(a);
        if (sortBy === 'progress_asc') return progressOf(a) - progressOf(b);
        if (sortBy === 'name_asc') return a.name.localeCompare(b.name);

        // Default 'activity': sort by updated_at or id desc
        const dateA = a.updated_at ? new Date(a.updated_at).getTime() : 0;
        const dateB = b.updated_at ? new Date(b.updated_at).getTime() : 0;
        return dateB - dateA || b.id - a.id;
    });
}

/**
 * ponytail: one flat list, no group headings. The KPI tiles above already carry
 * the "needs your review" split, so repeating it as headings only adds a second
 * place to read the same fact.
 */
function renderProjectsContent(
    projects: CustomerProject[],
    viewMode: PortalViewMode,
    isFiltered: boolean,
    onResetFilters: () => void,
) {
    if (projects.length === 0) {
        return (
            <PortalEmptyState
                isFiltered={isFiltered}
                onResetFilters={isFiltered ? onResetFilters : undefined}
            />
        );
    }

    if (viewMode === 'grid') {
        return (
            <div className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
                <div className="grid grid-cols-1 gap-4 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-3">
                    {projects.map((project) => (
                        <PortalProjectCard key={project.id} project={project} />
                    ))}
                </div>
            </div>
        );
    }

    // px-3 cancels each row's own -mx-3, so the hover fill still reaches both
    // edges; divide-y replaces the group headings as the row separator.
    return (
        <div className="overflow-hidden rounded-md border border-border/80 bg-card px-3 shadow-2xs [&>*+*]:border-t [&>*+*]:border-border/60">
            {projects.map((project) => (
                <PortalProjectListItem key={project.id} project={project} />
            ))}
        </div>
    );
}

export function CustomerPortalIndex({ projects = [] }: Readonly<CustomerPortalProps>) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<PortalStatusFilter>('all');
    const [selectedMarket, setSelectedMarket] = useState('all');
    const [sortBy, setSortBy] = useState<PortalSortOption>('activity');
    const [viewMode, setViewMode] = useState<PortalViewMode>('list');

    const totalProjects = projects.length;

    const metrics: PortalMetrics = useMemo(() => {
        const inProg = projects.filter((p) => progressOf(p) > 0 && progressOf(p) < 100).length;
        const appr = projects.filter(fullyApproved).length;
        const att = projects.filter((p) => (p.rework_packages_count ?? 0) > 0).length;
        const notStarted = projects.filter((p) => (p.total_packages_count ?? 0) === 0).length;
        const avg = totalProjects
            ? Math.round(projects.reduce((sum, p) => sum + progressOf(p), 0) / totalProjects)
            : 0;

        return {
            total: totalProjects,
            inProgress: inProg,
            approved: appr,
            needAttention: att,
            avgProgress: avg,
            notStarted,
        };
    }, [projects, totalProjects]);

    const availableMarkets = useMemo(() => {
        const set = new Set<string>();
        projects.forEach((p) => {
            if (p.market?.name) set.add(p.market.name);
        });
        return Array.from(set).sort();
    }, [projects]);

    const filteredProjects = useMemo(() => {
        const filtered = projects.filter((p) => {
            const matchesQ = matchesSearch(p, searchQuery.trim());
            const matchesS = matchesStatus(p, statusFilter);
            const matchesM = selectedMarket === 'all' || p.market?.name === selectedMarket;

            return matchesQ && matchesS && matchesM;
        });

        return sortProjects(filtered, sortBy);
    }, [projects, searchQuery, statusFilter, selectedMarket, sortBy]);

    const isFiltered = searchQuery.trim() !== '' || statusFilter !== 'all' || selectedMarket !== 'all';

    const handleResetFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
        setSelectedMarket('all');
    };

    const hasProjects = totalProjects > 0;

    return (
        <AppLayout
            title="Customer Portal"
            breadcrumb={[
                { label: 'Customer Portal', href: route('portal.index') },
                { label: 'Portfolio Overview' },
            ]}
            headerTags={<PortfolioBadge />}
            headerActions={
                <>
                    <PortfolioHealth metrics={metrics} />
                    <Button
                        asChild
                        variant="outline"
                        className="h-10 gap-2 px-4 text-sm font-semibold"
                    >
                        <Link href={route('change-orders.index')}>
                            <FileSpreadsheet className="text-muted-foreground" />
                            Change Orders
                        </Link>
                    </Button>
                </>
            }
        >
            <div className="space-y-5 pb-6">
                {hasProjects ? (
                    <>
                        <PortalKpiCards
                            metrics={metrics}
                            marketCount={availableMarkets.length}
                            activeFilter={statusFilter}
                            onSelectFilter={setStatusFilter}
                        />

                        <section>
                            {/* Heading and controls share one row: the toolbar was
                                its own bordered card, which stacked a second
                                surface above the list and pushed the projects down. */}
                            <div className="mb-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-heading text-base font-semibold text-foreground">
                                        Your projects
                                    </h3>
                                    <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold tabular-nums text-muted-foreground">
                                        {filteredProjects.length}
                                    </span>
                                    {isFiltered && (
                                        <Button
                                            variant="ghost"
                                            onClick={handleResetFilters}
                                            className="h-8 gap-1.5 px-2 text-xs font-medium text-muted-foreground hover:text-foreground"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Clear filters
                                        </Button>
                                    )}
                                </div>

                                <PortalToolbar
                                    searchQuery={searchQuery}
                                    onSearchChange={setSearchQuery}
                                    selectedMarket={selectedMarket}
                                    onMarketChange={setSelectedMarket}
                                    availableMarkets={availableMarkets}
                                    sortBy={sortBy}
                                    onSortChange={setSortBy}
                                    viewMode={viewMode}
                                    onViewModeChange={setViewMode}
                                />
                            </div>

                            {renderProjectsContent(
                                filteredProjects,
                                viewMode,
                                isFiltered,
                                handleResetFilters,
                            )}
                        </section>
                    </>
                ) : (
                    <section className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
                        <PortalEmptyState isFiltered={false} />
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

export default CustomerPortalIndex;
