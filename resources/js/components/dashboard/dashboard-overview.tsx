import { AttentionItem, AttentionPanel } from '@/components/dashboard/attention-panel';
import { ChangeOrdersOverview, RecentChangeOrderItem } from '@/components/dashboard/change-orders-overview';
import { CustomerDashboardOverview } from '@/components/dashboard/customer-dashboard-overview';
import { MarketHealth, MarketHealthItem } from '@/components/dashboard/market-health';
import { PackagePipeline } from '@/components/dashboard/package-pipeline';
import { ProjectHealth } from '@/components/dashboard/project-health';
import { EmptyCard } from '@/components/shared/empty-card';
import { StatsCard } from '@/components/shared/stats-card';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Activity,
    AlertCircle,
    BarChart3,
    Camera,
    CheckCircle2,
    FileSignature,
    Mail,
    Send,
} from 'lucide-react';

export interface DashboardStats {
    totalProjects: number;
    projectsPendingSubmission: number;
    completedProjects: number;
    pendingApprovals: number;
    flaggedPhotos: number;
    photosAwaitingReview: number;
    openIssues: number;
    avgProgress: number;
    recentSubmissions: number;
    marketHealth: MarketHealthItem[];
    projectsNeedingManager: number;
    projectsNeedingSupervisor: number;
    projectsNotStarted: number;
    projectsInProgress: number;
    projectsStalled: number;
    totalPackages: number;
    packagesApproved: number;
    packagesReadyToSubmit: number;
    packagesNeedingRework: number;
    packagesRejected: number;
    packagesWithFlaggedPhotos: number;
    packagesDraft: number;
    photosCaptured: number;
    photosUploaded: number;
    photosRequired: number;
    emailsSentToday: number;
    emailsFailedToday: number;
    attentionItems: AttentionItem[];
    customerActivity: { id: string; label: string; project: string; href: string; meta: string; created_at: string | null }[];
    customerProjects: { id: number; name: string; status: string; progress: number; href: string }[];
    customerAttention: { id: number; title: string; project: string; amount: string; href: string; status: string }[];
    recentPhotos: { id: number; url: string; full_url: string; project: string; captured_at: string | null }[];
    fieldActivity: { technicians: number; project: string | null; href: string | null; updated_at: string | null };
    totalChangeOrders: number;
    changeOrdersDraft: number;
    changeOrdersPendingCustomer: number;
    changeOrdersApproved: number;
    changeOrdersRejected: number;
    changeOrdersTotalValue: number;
    changeOrdersApprovedValue: number;
    recentChangeOrders: RecentChangeOrderItem[];
    sectionVisible: Record<string, boolean>;
}

export interface DashboardOverviewProps {
    stats: DashboardStats;
    userName?: string;
    canViewEmailStats: boolean;
    audience?: 'staff' | 'customer';
}

function greetingForHour(hour: number): string {
    if (hour < 12) {
        return 'Good morning';
    }
    if (hour < 17) {
        return 'Good afternoon';
    }
    return 'Good evening';
}

export function DashboardOverview({
    stats,
    userName,
    canViewEmailStats,
    audience = 'staff',
}: Readonly<DashboardOverviewProps>) {
    if (audience === 'customer') {
        return <CustomerDashboardOverview stats={stats} userName={userName} />;
    }

    const firstName = userName?.split(' ')[0] ?? '';

    if (stats.totalProjects === 0) {
        return (
            <EmptyCard
                title="No projects yet"
                description="Create your first project to start tracking close-out packages, photo stages, and approvals."
                icon={<Activity className="size-10 text-muted-foreground" />}
            />
        );
    }

    const attentionCount = stats.attentionItems.reduce((sum, item) => sum + item.count, 0);
    const photoCoverage =
        stats.photosRequired > 0
            ? Math.min(100, Math.round((stats.photosUploaded / stats.photosRequired) * 100))
            : 0;

    return (
        <>
            <div className="space-y-1">
                <h2 className="text-lg font-bold tracking-tight">
                    {greetingForHour(new Date().getHours())}
                    {firstName ? `, ${firstName}` : ''}
                </h2>
                <p className="text-xs text-muted-foreground">
                    {stats.completedProjects} of {stats.totalProjects} projects complete
                    {attentionCount > 0 ? (
                        <>
                            {' '}
                            · <strong className="text-foreground">
                                {attentionCount} items
                            </strong>{' '}
                            need attention
                        </>
                    ) : (
                        ' · all caught up'
                    )}
                </p>
            </div>

            <AttentionPanel items={stats.attentionItems} />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ProjectHealth
                    totalProjects={stats.totalProjects}
                    projectsNeedingManager={stats.projectsNeedingManager}
                    projectsNeedingSupervisor={stats.projectsNeedingSupervisor}
                    projectsNotStarted={stats.projectsNotStarted}
                    projectsInProgress={stats.projectsInProgress}
                    completedProjects={stats.completedProjects}
                />
                <PackagePipeline
                    totalPackages={stats.totalPackages}
                    packagesDraft={stats.packagesDraft}
                    packagesReadyToSubmit={stats.packagesReadyToSubmit}
                    pendingApprovals={stats.pendingApprovals}
                    packagesNeedingRework={stats.packagesNeedingRework}
                    packagesRejected={stats.packagesRejected}
                    packagesWithFlaggedPhotos={stats.packagesWithFlaggedPhotos}
                    packagesApproved={stats.packagesApproved}
                />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <StatsCard
                    title="Photos Captured"
                    value={stats.photosCaptured}
                    description={`${stats.photosUploaded} active · ${photoCoverage}% of required photos`}
                    icon={<Camera className="size-5" />}
                />
                <StatsCard
                    title="Flagged Photos"
                    value={stats.flaggedPhotos}
                    description={`Across ${stats.packagesWithFlaggedPhotos} package${
                        stats.packagesWithFlaggedPhotos === 1 ? '' : 's'
                    } needing replacement`}
                    icon={<AlertCircle className="size-5" />}
                />
                <StatsCard
                    title="Photos Awaiting Review"
                    value={stats.photosAwaitingReview}
                    description="Pending photos in the review queue"
                    icon={<CheckCircle2 className="size-5" />}
                />
                {stats.sectionVisible.changeOrders && (
                    <StatsCard
                        title="Change Orders"
                        value={stats.totalChangeOrders}
                        description={
                            stats.totalChangeOrders > 0
                                ? `${stats.changeOrdersPendingCustomer} pending review · $${stats.changeOrdersTotalValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} total`
                                : 'No change orders created'
                        }
                        icon={<FileSignature className="size-5" />}
                    />
                )}
                <StatsCard
                    title="Avg Project Progress"
                    value={`${stats.avgProgress}%`}
                    description="Across all projects in scope"
                    icon={<BarChart3 className="size-5" />}
                />
                <StatsCard
                    title="Recent Submissions"
                    value={stats.recentSubmissions}
                    description="Packages submitted in the last 7 days"
                    icon={<Send className="size-5" />}
                />
                <StatsCard
                    title="Open Issues"
                    value={stats.openIssues}
                    description="Unresolved site issues in scope"
                    icon={<AlertCircle className="size-5" />}
                />
                {canViewEmailStats && (
                    <StatsCard
                        title="Emails Sent Today"
                        value={stats.emailsSentToday}
                        description={
                            stats.emailsFailedToday > 0
                                ? `${stats.emailsFailedToday} failed today`
                                : 'All delivered today'
                        }
                        icon={<Mail className="size-5" />}
                        className={
                            stats.emailsFailedToday > 0 ? 'ring-1 ring-destructive/40' : undefined
                        }
                    />
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2 text-sm font-bold">
                            <BarChart3 className="size-4 text-primary" />
                            Market Health
                        </CardTitle>
                        <CardDescription>
                            Project distribution per market — click a market to open its projects
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <MarketHealth items={stats.marketHealth} />
                    </CardContent>
                </Card>

                {stats.sectionVisible.changeOrders && (
                    <ChangeOrdersOverview
                        totalChangeOrders={stats.totalChangeOrders}
                        changeOrdersDraft={stats.changeOrdersDraft}
                        changeOrdersPendingCustomer={stats.changeOrdersPendingCustomer}
                        changeOrdersApproved={stats.changeOrdersApproved}
                        changeOrdersRejected={stats.changeOrdersRejected}
                        changeOrdersTotalValue={stats.changeOrdersTotalValue}
                        changeOrdersApprovedValue={stats.changeOrdersApprovedValue}
                        recentChangeOrders={stats.recentChangeOrders}
                    />
                )}
            </div>
        </>
    );
}

export default DashboardOverview;

