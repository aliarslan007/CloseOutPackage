import { EmptyCard } from '@/components/shared/empty-card';
import { VisibilityIssuesCard } from '@/components/visibility/visibility-issues-card';
import { VisibilityPackagesCard } from '@/components/visibility/visibility-packages-card';
import { VisibilityProjectHeader } from '@/components/visibility/visibility-project-header';
import { VisibilityProjectDetail } from '@/components/visibility/visibility-project-types';
import { VisibilitySiteInfoCard } from '@/components/visibility/visibility-site-info-card';
import { VisibilitySitePhotoCard } from '@/components/visibility/visibility-site-photo-card';
import { VisibilityTeamCard } from '@/components/visibility/visibility-team-card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';

const VISIBILITY_ROUTE = 'manager.visibility';

export type ManagerVisibilityProjectPageProps = PageProps<{
    project: VisibilityProjectDetail;
    sitePhotoUrl?: string | null;
}>;

export function ManagerVisibilityProject() {
    useFlashToasts();

    const { project, sitePhotoUrl } = usePage<ManagerVisibilityProjectPageProps>().props;

    if (!project) {
        return (
            <AppLayout
                title="Live Project View"
                breadcrumb={[
                    { label: 'Live Visibility', href: route(VISIBILITY_ROUTE) },
                    { label: 'Project' },
                ]}
            >
                <Head title="Live Project View" />
                <EmptyCard
                    title="Project unavailable"
                    description="The project details could not be loaded."
                    icon={<Building2 className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title={project.name}
            breadcrumb={[
                { label: 'Live Visibility', href: route(VISIBILITY_ROUTE) },
                { label: project.name },
            ]}
        >
            <Head title={`${project.name} — Live Project View`} />
            <div className="space-y-2">
                <Link
                    href={route(VISIBILITY_ROUTE)}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to Live Visibility
                </Link>

                <VisibilityProjectHeader project={project} />

                {/* Stage-by-stage package progress is the heart of Live Visibility. */}
                <VisibilityPackagesCard packages={project.closeout_packages} />

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
                    <div className="xl:col-span-2 space-y-4">
                        <VisibilitySitePhotoCard sitePhotoUrl={sitePhotoUrl} />
                        <VisibilitySiteInfoCard project={project} />
                    </div>
                    <div className="space-y-2">
                        <VisibilityTeamCard project={project} />
                        <VisibilityIssuesCard issuesPage={project.issues} projectKey={project.hash_id || project.id} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default ManagerVisibilityProject;
