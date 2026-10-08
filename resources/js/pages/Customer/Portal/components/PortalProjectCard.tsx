import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { UserAvatar } from '@/components/shared/user-avatar';
import { Link } from '@inertiajs/react';
import { Building2, ChevronRight, MapPin } from 'lucide-react';
import { CustomerProject } from '../portal.types';

interface PortalProjectCardProps {
    project: CustomerProject;
}

function packageLabel(approved: number, total: number): string {
    if (total === 0) {
        return 'No packages submitted yet';
    }

    return `${approved} of ${total} ${total === 1 ? 'package' : 'packages'} approved`;
}

export function PortalProjectCard({ project }: Readonly<PortalProjectCardProps>) {
    const progress = project.progress_percentage ?? 0;
    const total = project.total_packages_count ?? 0;
    const approved = project.approved_packages_count ?? 0;
    const allApproved = total > 0 && approved === total;
    const rework = project.rework_packages_count ?? 0;
    const projectUrl = route('projects.show', project.hash_id || project.id);

    return (
        <article className="group relative flex flex-col rounded-2xl border border-border/80 bg-card p-4 shadow-card transition-colors hover:border-foreground/20 hover:bg-secondary/30 focus-within:border-foreground/20 focus-within:bg-secondary/30">
            <div>
                <div className="flex items-start justify-between gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-border/70 bg-secondary/60 px-2 py-0.5 text-xs font-semibold tabular-nums text-foreground">
                        <Building2 className="size-3.5 text-muted-foreground" />
                        {project.ps_number || `PS-${project.id}`}
                    </span>

                    {rework > 0 ? (
                        <Badge variant="warning">Needs your review</Badge>
                    ) : (
                        allApproved && <Badge variant="success">Fully approved</Badge>
                    )}
                </div>

                <h3 className="mt-3 font-heading text-base font-semibold text-foreground">
                    <Link
                        href={projectUrl}
                        className="rounded-sm underline-offset-4 after:absolute after:inset-0 after:content-[''] hover:underline focus-visible:outline-none"
                    >
                        {project.name}
                    </Link>
                </h3>

                <div className="mt-2 space-y-1 text-sm text-muted-foreground">
                    {project.market?.name && (
                        <p className="flex items-center gap-1.5">
                            <MapPin className="size-3.5 shrink-0" />
                            <span className="truncate">{project.market.name}</span>
                        </p>
                    )}
                    {project.address && (
                        <p className="truncate text-xs">
                            {project.address}
                            {project.city ? `, ${project.city}` : ''}
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-4 border-t border-border/70 pt-3.5">
                <div className="flex items-baseline justify-between gap-2">
                    <span className="text-xs font-medium text-muted-foreground">
                        Approval progress
                    </span>
                    <span className="font-heading text-sm font-bold tabular-nums text-foreground">
                        {progress}%
                    </span>
                </div>
                <Progress value={progress} className="mt-2" />

                <div className="mt-2 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                    <span>{packageLabel(approved, total)}</span>
                    {project.manager?.name && (
                        <span
                            className="inline-flex min-w-0 items-center gap-2"
                            title={`Project manager: ${project.manager.name}`}
                        >
                            <UserAvatar
                                name={project.manager.name}
                                src={project.manager.profile_photo_url}
                                className="size-6"
                            />
                            <span className="truncate">{project.manager.name}</span>
                        </span>
                    )}
                </div>
            </div>

            <ChevronRight
                className="absolute bottom-4 right-4 size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                aria-hidden
            />
        </article>
    );
}

export default PortalProjectCard;
