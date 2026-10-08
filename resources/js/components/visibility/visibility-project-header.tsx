import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { StatusBadge } from '@/components/shared/status-badge';
import { PROJECT_STATUS_META } from '@/components/projects/project-show-types';
import { VisibilityProjectDetail } from '@/components/visibility/visibility-project-types';
import { Hash, MapPin } from 'lucide-react';

export interface VisibilityProjectHeaderProps {
    project: VisibilityProjectDetail;
}

export function VisibilityProjectHeader({ project }: Readonly<VisibilityProjectHeaderProps>) {
    const statusMeta = PROJECT_STATUS_META[project.status];
    const locationParts = [project.city, project.state].filter(Boolean).join(', ');

    return (
        <div className="rounded-xl border border-border/80 bg-card p-4 space-y-3">
            <div className="space-y-2">
                <div className="flex items-center gap-2 flex-wrap">
                    {/* A project name can be one long unbroken string, and as a
                        flex item it defaults to min-width:auto, so it refused to
                        shrink and widened the whole card. `anywhere` (not
                        `break-word`) is what also shrinks the min-content size. */}
                    <h2
                        className="min-w-0 font-heading text-base font-bold text-foreground [overflow-wrap:anywhere]"
                        title={project.name}
                    >
                        {project.name}
                    </h2>
                    <StatusBadge status={project.status} label={statusMeta?.label} />
                    {project.market && (
                        <Badge variant="outline" className="gap-1 text-[10px]">
                            <MapPin className="size-3" /> {project.market.name}
                        </Badge>
                    )}
                </div>

                <div className="flex items-center gap-2 w-full max-w-sm">
                    <Progress value={project.progress_percentage} className="flex-1" />
                    <span className="text-[11px] font-medium text-muted-foreground shrink-0">
                        {project.progress_percentage}% done
                    </span>
                </div>

                <p className="text-[11px] text-muted-foreground">
                    {statusMeta?.explanation ?? 'Status information unavailable.'}
                </p>
            </div>

            <div className="flex items-center gap-2 flex-wrap border-t border-border/60 pt-3">
                {project.ps_number && (
                    <Badge variant="secondary" className="gap-1 text-[10px]">
                        <Hash className="size-3" /> PS {project.ps_number}
                    </Badge>
                )}
                {project.project_number && (
                    <Badge variant="secondary" className="gap-1 text-[10px]">
                        <Hash className="size-3" /> Proj {project.project_number}
                    </Badge>
                )}
                {locationParts && (
                    <Badge variant="secondary" className="gap-1 text-[10px]">
                        <MapPin className="size-3" /> {locationParts}
                    </Badge>
                )}
            </div>
        </div>
    );
}
