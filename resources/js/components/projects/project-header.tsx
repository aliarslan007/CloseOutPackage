import { PROJECT_STATUS_META, ProjectShowDetail } from '@/components/projects/project-show-types';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    FileSpreadsheet,
    FolderKanban,
    MapPin,
    Pencil,
    Trash2,
} from 'lucide-react';

export interface ProjectHeaderProps {
    project: ProjectShowDetail;
    canEdit?: boolean;
    canDelete?: boolean;
    canTrackFieldTechs?: boolean;
    canManageChangeOrders?: boolean;
    canViewChangeOrders?: boolean;
    isCustomer?: boolean;
    roleHint?: string | null;
    openIssuesCount?: number;
    onDelete?: () => void;
}

export function ProjectHeader({
    project,
    canEdit = false,
    canDelete = false,
    canTrackFieldTechs = false,
    canManageChangeOrders = false,
    canViewChangeOrders = false,
    isCustomer = false,
    roleHint,
    openIssuesCount = 0,
    onDelete,
}: Readonly<ProjectHeaderProps>) {
    const statusMeta = PROJECT_STATUS_META[project.status];
    const packagesRoute = isCustomer
        ? route('portal.projects.show', project.hash_id || project.id)
        : route('packages.index', project.hash_id || project.id);

    return (
        <div className="rounded-md border border-border/80 bg-card p-4 space-y-3 min-w-0 overflow-hidden shadow-2xs">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 min-w-0">
                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex items-center gap-2 flex-wrap min-w-0">
                        {/* A project name can be one long unbroken string, and as a
                            flex item it defaults to min-width:auto, so it refused to
                            shrink and widened the whole card. `anywhere` (not
                            `break-word`) is what also shrinks the min-content size. */}
                        <h2
                            className="min-w-0 font-heading text-base font-bold text-foreground break-words [overflow-wrap:anywhere]"
                            title={project.name}
                        >
                            {project.name}
                        </h2>
                        <StatusBadge status={project.status} label={statusMeta?.label} />
                        {project.market && (
                            <Badge variant="outline" className="gap-1 text-[10px] shrink-0">
                                <MapPin className="size-3" /> {project.market.name}
                            </Badge>
                        )}
                        {project.creator && (
                            <span className="inline-flex items-center gap-1 text-[10px] text-muted-foreground shrink-0">
                                {project.creator.profile_photo_url ? (
                                    <img
                                        src={project.creator.profile_photo_url}
                                        alt=""
                                        className="size-4 rounded-full object-cover ring-1 ring-border"
                                    />
                                ) : (
                                    <span className="flex size-4 items-center justify-center rounded-full bg-muted text-[8px] font-medium text-muted-foreground">
                                        {project.creator.name.charAt(0)}
                                    </span>
                                )}
                                {project.creator.name}
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-2 w-full max-w-sm min-w-0">
                        <Progress value={project.progress_percentage} className="flex-1" />
                        <span className="text-[11px] font-medium text-muted-foreground shrink-0">
                            {project.progress_percentage}% done
                        </span>
                    </div>

                    {(statusMeta?.explanation || roleHint) && (
                        <p className="text-[11px] text-muted-foreground break-words [overflow-wrap:anywhere]">
                            {statusMeta?.explanation}
                            {statusMeta?.explanation && roleHint ? ' ' : ''}
                            {roleHint}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-2 flex-wrap shrink-0">
                    <Button asChild variant="default" size="sm" className="h-8 text-xs gap-1">
                        <Link href={packagesRoute}>
                            <FolderKanban className="size-3.5" /> Closeout Packages
                        </Link>
                    </Button>
                    {openIssuesCount > 0 ? (
                        <Button
                            asChild
                            variant="destructive"
                            size="sm"
                            className="h-8 text-xs gap-1 shadow-sm"
                            title={`${openIssuesCount} open issue${openIssuesCount === 1 ? '' : 's'} need attention`}
                        >
                            <Link href={route('projects.issues.index', project.hash_id || project.id)}>
                                <AlertTriangle className="size-3.5 animate-pulse" /> Issues ({openIssuesCount})
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild variant="secondary" size="sm" className="h-8 border border-border/70 text-xs gap-1 shadow-sm">
                            <Link href={route('projects.issues.index', project.hash_id || project.id)}>
                                <AlertTriangle className="size-3.5" /> Issues
                            </Link>
                        </Button>
                    )}
                    {(canManageChangeOrders || canViewChangeOrders || isCustomer) && (
                        <Button asChild variant="secondary" size="sm" className="h-8 border border-border/70 text-xs gap-1 shadow-sm">
                            <Link href={route('projects.change-orders.index', project.hash_id || project.id)}>
                                <FileSpreadsheet className="size-3.5" /> Change Orders
                            </Link>
                        </Button>
                    )}
                    {canTrackFieldTechs && (
                        <Button asChild variant="secondary" size="sm" className="h-8 border border-border/70 text-xs gap-1 shadow-sm">
                            <Link href={route('projects.tracking.show', project.hash_id || project.id)}>
                                <MapPin className="size-3.5" /> Track Field Techs
                            </Link>
                        </Button>
                    )}
                    {canEdit && (
                        <Button asChild variant="secondary" size="sm" className="h-8 border border-border/70 text-xs gap-1 shadow-sm">
                            <Link href={route('projects.edit', project.hash_id || project.id)}>
                                <Pencil className="size-3.5" /> Edit
                            </Link>
                        </Button>
                    )}
                    {canDelete && onDelete && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onDelete}
                            className="h-8 gap-1 border border-destructive/25 bg-destructive/10 text-xs text-destructive shadow-sm hover:bg-destructive/15"
                        >
                            <Trash2 className="size-3.5" /> Delete
                        </Button>
                    )}
                </div>
            </div>

        </div>
    );
}
