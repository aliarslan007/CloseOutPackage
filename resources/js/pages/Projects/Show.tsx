import { ProjectHeader } from '@/components/projects/project-header';
import {
    ProjectSiteInformationCard,
    ProjectStatusCard,
} from '@/components/projects/project-info-cards';
import { ProjectIssuesCard } from '@/components/projects/project-issues-card';
import { ProjectPackagesCard } from '@/components/projects/project-packages-card';
import { ProjectShowDetail } from '@/components/projects/project-show-types';
import { ProjectTeamCard } from '@/components/projects/project-team-card';
import { ProjectTimelineCard } from '@/components/projects/project-timeline-card';
import { SitePhotoCard } from '@/components/projects/site-photo-card';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { DocumentsHubCard, type ProjectDocumentsMap } from '@/features/project-documents';
import { useGeolocation } from '@/features/tracking';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/permissions';
import { PageProps } from '@/types/inertia';
import { Head, router, usePage } from '@inertiajs/react';
import * as React from 'react';

export type ProjectShowPageProps = PageProps<{
    project: ProjectShowDetail;
    sitePhotoUrl?: string | null;
    projectDocuments?: ProjectDocumentsMap;
    /** Backend-computed via CloseoutPackagePolicy::create — the source of truth. */
    can_create_package?: boolean;
}>;

const MANAGER_ROLE = 'manager';

function roleHint(roles: string[], isManagerOwner: boolean): string | null {
    if (roles.includes('admin')) {
        return 'You manage this site — track progress and review issues.';
    }
    if (roles.includes(MANAGER_ROLE)) {
        return isManagerOwner
            ? 'You manage this site — review issues when they come in.'
            : 'You can view this site.';
    }
    if (roles.includes('field_supervisor')) {
        return 'You are assigned here — record issues or chat with the manager.';
    }
    if (roles.includes('customer')) {
        return 'Verizon Customer Portal — view site information, team, issues, and close-out packages.';
    }
    return null;
}

export function ProjectShow() {
    useFlashToasts();

    const { project, sitePhotoUrl, projectDocuments, can_create_package } =
        usePage<ProjectShowPageProps>().props;
    const permissions = usePermissions();
    const isFieldTech = permissions.user?.roles?.includes('field_supervisor') ?? false;

    useGeolocation(project?.id, { enabled: isFieldTech });

    const [deleteOpen, setDeleteOpen] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const canEdit = permissions.canEditProject(project?.manager?.id);
    const hint = roleHint(permissions.user?.roles ?? [], canEdit);

    const openIssuesCount = project?.issues?.filter((issue) => !issue.resolved_at).length ?? 0;

    const handleDelete = () => {
        if (!project) return;
        setIsDeleting(true);
        router.delete(route('projects.destroy', project.hash_id || project.id), {
            onSuccess: () => setDeleteOpen(false),
            onError: () => setIsDeleting(false),
        });
    };

    const breadcrumb = permissions.isCustomer
        ? [
            { label: 'Customer Portal', href: route('portal.index') },
            { label: 'Projects', href: route('portal.index') },
            { label: project?.name ?? 'Details' },
        ]
        : [{ label: 'Projects', href: route('projects.index') }, { label: 'Details' }];

    if (!project) {
        return (
            <AppLayout title="Project" breadcrumb={breadcrumb}>
                <Head title="Project" />
                <p className="font-mono text-xs text-muted-foreground">
                    Project could not be loaded.
                </p>
            </AppLayout>
        );
    }

    return (
        <AppLayout title={project.name} breadcrumb={breadcrumb}>
            <Head title={project.name} />
            <div className="space-y-2 min-w-0 w-full">
                <ProjectHeader
                    project={project}
                    canEdit={canEdit}
                    canDelete={permissions.canDeleteProject(project?.manager?.id)}
                    canTrackFieldTechs={permissions.canTrackFieldTechs(project?.manager?.id)}
                    canManageChangeOrders={permissions.canManageChangeOrders}
                    canViewChangeOrders={permissions.canViewChangeOrders}
                    isCustomer={permissions.isCustomer}
                    roleHint={hint}
                    openIssuesCount={openIssuesCount}
                    onDelete={() => setDeleteOpen(true)}
                />

                <ProjectTimelineCard project={project} />

                <DocumentsHubCard
                    projectKey={project.hash_id || project.id}
                    documents={projectDocuments ?? {}}
                    canManage={canEdit}
                />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <SitePhotoCard
                            projectId={project.id}
                            sitePhotoUrl={sitePhotoUrl}
                            canEdit={canEdit}
                        />
                    </div>
                    <ProjectStatusCard project={project} />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <ProjectSiteInformationCard project={project} />
                    </div>
                    <ProjectIssuesCard project={project} />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <ProjectTeamCard
                        project={project}
                        canSetPrimary={permissions.isAdmin || permissions.isManager}
                    />
                    <div className="lg:col-span-2">
                        <ProjectPackagesCard
                            projectId={project.hash_id || project.id}
                            packages={project.closeout_packages}
                            canCreatePackage={can_create_package}
                            isCustomer={permissions.isCustomer}
                        />
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteOpen(false);
                }}
                title="Delete project"
                description={`Delete "${project.name}"? This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default ProjectShow;
