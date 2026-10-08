import * as React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { StatusBadge } from '@/components/shared/status-badge';
import { EmptyCard } from '@/components/shared/empty-card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { PageProps } from '@/types/inertia';
import { RefreshButton } from '@/components/shared/refresh-button';
import { Activity, Building2, Camera, ChevronRight, FileText, MapPin } from 'lucide-react';

export interface VisibilityProjectItem {
    id: number;
    name: string;
    status: string;
    progress_percentage: number;
    market?: { id: number; name: string } | null;
    latest_package?: {
        id: number;
        status: string;
        current_stage?: string | null;
        submitted_at?: string | null;
    } | null;
}

export interface VisibilitySnapshot {
    projects: VisibilityProjectItem[];
    templateCount: number;
    photoCount: number;
    generatedAt: string;
}

export type ManagerVisibilityPageProps = PageProps<{
    snapshot: VisibilitySnapshot;
}>;

export function ManagerVisibility() {
    useFlashToasts();

    const { snapshot } = usePage<ManagerVisibilityPageProps>().props;

    if (!snapshot) {
        return (
            <AppLayout title="Live Visibility" breadcrumb={[{ label: 'Live Visibility' }]}>
                <Head title="Live Visibility" />
                <EmptyCard
                    title="Visibility unavailable"
                    description="The live project snapshot could not be loaded."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    const projects = snapshot.projects ?? [];

    return (
        <AppLayout
            title="Live Visibility"
            breadcrumb={[{ label: 'Live Visibility' }]}
            headerActions={<RefreshButton label="Refresh" className="h-8 text-xs" />}
        >
            <Head title="Live Visibility" />
            <div className="space-y-2">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Card>
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Building2 className="size-5" />
                            </div>
                            <div>
                                <p className="font-heading text-2xl font-bold text-foreground">
                                    {projects.length}
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Projects
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <FileText className="size-5" />
                            </div>
                            <div>
                                <p className="font-heading text-2xl font-bold text-foreground">
                                    {snapshot.templateCount}
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Templates
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Camera className="size-5" />
                            </div>
                            <div>
                                <p className="font-heading text-2xl font-bold text-foreground">
                                    {snapshot.photoCount}
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Photos Captured
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center justify-between gap-2 text-sm font-bold">
                            <span className="font-heading font-bold text-foreground">Project Snapshot</span>
                            <span className="text-[10px] font-normal text-muted-foreground">
                                Updated {new Date(snapshot.generatedAt).toLocaleString()}
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {projects.length === 0 ? (
                            <p className="rounded-lg border border-dashed p-6 text-center text-xs text-muted-foreground">
                                No projects in your markets yet.
                            </p>
                        ) : (
                            projects.map((project) => (
                                <Link
                                    key={project.id}
                                    href={route('manager.visibility.project', project.id)}
                                    className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-md border border-border/60 bg-muted/20 p-3 transition-colors hover:border-primary/40 hover:bg-muted/40 group"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="font-heading text-xs font-bold text-foreground group-hover:text-primary transition-colors">
                                                {project.name}
                                            </p>
                                            <StatusBadge status={project.status} />
                                            {project.market && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 text-[10px]"
                                                >
                                                    <MapPin className="size-3" />
                                                    {project.market.name}
                                                </Badge>
                                            )}
                                        </div>
                                        {project.latest_package && (
                                            <p className="text-[11px] text-muted-foreground">
                                                Latest package:{' '}
                                                <span className="font-medium">
                                                    {project.latest_package.status.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                                                </span>
                                                {project.latest_package.current_stage
                                                    ? ` · ${project.latest_package.current_stage.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}`
                                                    : ''}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 w-full sm:w-40 shrink-0">
                                        <Progress
                                            value={project.progress_percentage}
                                            className="flex-1"
                                        />
                                        <span className="text-[11px] text-muted-foreground shrink-0">
                                            {project.progress_percentage}%
                                        </span>
                                        <ChevronRight className="size-4 text-muted-foreground group-hover:text-primary transition-colors shrink-0" />
                                    </div>
                                </Link>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

export default ManagerVisibility;
