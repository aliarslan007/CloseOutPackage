import { ActivityLogPanel, ActivityPage } from '@/components/dashboard/activity-log-panel';
import {
    DashboardOverview,
    DashboardStats,
} from '@/components/dashboard/dashboard-overview';
import { EmptyCard } from '@/components/shared/empty-card';
import { Button } from '@/components/ui/button';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Activity, History } from 'lucide-react';
import * as React from 'react';

export type DashboardPageProps = PageProps<{
    stats: DashboardStats;
    activities?: ActivityPage;
    currentTab?: string;
}>;

const CAN_VIEW_ACTIVITIES = new Set(['admin', 'manager']);
const CAN_VIEW_EMAIL_STATS = new Set(['admin', 'manager']);

export function Dashboard() {
    useFlashToasts();

    const { stats, activities, currentTab, auth } = usePage<DashboardPageProps>().props;
    const canViewActivities = auth.user.roles.some((role) => CAN_VIEW_ACTIVITIES.has(role));
    const canViewEmailStats = auth.user.roles.some((role) => CAN_VIEW_EMAIL_STATS.has(role));
    const isCustomer = auth.user.roles.includes('customer');

    // Derive the active tab from the Inertia prop — no need to re-read the URL.
    const selectedTab: 'overview' | 'activity' = currentTab === 'activity' ? 'activity' : 'overview';

    const isAllActivities =
        typeof globalThis !== 'undefined' &&
        new URLSearchParams(globalThis.location.search).get('activities_all') === '1';

    if (!stats) {
        return (
            <AppLayout title="Dashboard" breadcrumb="Dashboard">
                <Head title="Dashboard" />
                <EmptyCard
                    title="Dashboard unavailable"
                    description="The dashboard data could not be loaded. Please try refreshing the page."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    const isActivityTab = selectedTab === 'activity';

    return (
        <AppLayout
            title={isActivityTab ? 'Activity Logs' : 'Dashboard Overview'}
            breadcrumb={isActivityTab ? 'Dashboard > Activity Logs' : 'Dashboard > Overview'}
            headerActions={
                <div className="flex items-center gap-1.5 flex-wrap sm:flex-nowrap">
                    <Link
                        href={route('dashboard')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                            isActivityTab
                                ? 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                                : 'bg-primary text-primary-foreground shadow-2xs'
                        }`}
                    >
                        Overview
                    </Link>
                    {canViewActivities && (
                        <Link
                            href={`${route('dashboard')}?tab=activity`}
                            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${
                                isActivityTab
                                    ? 'bg-primary text-primary-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                            }`}
                        >
                            <History className="size-3.5" /> Activity Logs
                        </Link>
                    )}
                    {isActivityTab && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs ml-1"
                            onClick={() => {
                                const params = isAllActivities
                                    ? { tab: 'activity' }
                                    : { tab: 'activity', activities_all: 1 };
                                router.get(route('dashboard'), params, { preserveState: true });
                            }}
                        >
                            {isAllActivities ? 'Show Pages' : 'See All Entries'}
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={isActivityTab ? 'Activity Logs' : 'Dashboard'} />

            <div className="space-y-2">
                {isActivityTab && !isCustomer ? (
                    <ActivityLogPanel activities={activities} isAllActivities={isAllActivities} />
                ) : (
                    <DashboardOverview
                        stats={stats}
                        userName={auth.user.name}
                        canViewEmailStats={canViewEmailStats}
                        audience={isCustomer ? 'customer' : 'staff'}
                    />
                )}
            </div>
        </AppLayout>
    );
}

export default Dashboard;
