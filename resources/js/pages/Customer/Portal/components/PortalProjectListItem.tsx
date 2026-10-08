import { Progress } from '@/components/ui/progress';
import { UserAvatar } from '@/components/shared/user-avatar';
import { Link } from '@inertiajs/react';
import { ChevronRight, Clock, MapPin } from 'lucide-react';
import { CustomerProject } from '../portal.types';

interface PortalProjectListItemProps {
    project: CustomerProject;
}

interface StatusSummary {
    label: string;
    tone: string;
}

const TONE_REVIEW = 'text-amber-600 dark:text-amber-400';
const TONE_APPROVED = 'text-emerald-600 dark:text-emerald-400';
const TONE_NEUTRAL = 'text-muted-foreground';

function summarizeStatus(approved: number, total: number, rework: number): StatusSummary {
    if (rework > 0) {
        return { label: `${rework} awaiting your review`, tone: TONE_REVIEW };
    }

    if (total === 0) {
        return { label: 'Awaiting first package', tone: TONE_NEUTRAL };
    }

    if (approved === total) {
        return { label: 'Fully approved', tone: TONE_APPROVED };
    }

    const unit = total === 1 ? 'package' : 'packages';
    return {
        label: `${approved} of ${total} ${unit} approved`,
        tone: TONE_NEUTRAL,
    };
}

function formatLocation(address?: string | null, city?: string | null, state?: string | null): string {
    const parts: string[] = [];
    if (address) parts.push(address);
    if (city) parts.push(city);
    if (state) parts.push(state);
    return parts.join(', ');
}

export function PortalProjectListItem({ project }: Readonly<PortalProjectListItemProps>) {
    const progress = project.progress_percentage ?? 0;
    const total = project.total_packages_count ?? 0;
    const approved = project.approved_packages_count ?? 0;
    const rework = project.rework_packages_count ?? 0;
    const needsReview = rework > 0;
    const projectUrl = route('projects.show', project.hash_id || project.id);
    const locationText = formatLocation(project.address, project.city, project.state);

    const status = summarizeStatus(approved, total, rework);

    return (
        <article className="group relative -mx-3 rounded-lg px-3 py-3.5 transition-colors hover:bg-secondary focus-within:bg-secondary lg:flex lg:items-center lg:gap-6 lg:py-2.5">
            {needsReview && (
                <span
                    aria-hidden
                    className="absolute inset-y-2.5 left-0 w-0.5 rounded-full bg-amber-500"
                />
            )}

            <div className="min-w-0 flex-1">
                <h3
                    className="truncate text-[15px] font-semibold tracking-tight text-foreground"
                    title={project.name}
                >
                    <Link
                        href={projectUrl}
                        className="rounded-sm underline-offset-4 after:absolute after:inset-0 after:content-[''] hover:underline focus-visible:outline-none"
                    >
                        {project.name}
                    </Link>
                </h3>

                <p className="mt-1 flex min-w-0 items-center gap-x-2.5 truncate text-xs text-muted-foreground">
                    {project.market?.name && (
                        <span className="inline-flex shrink-0 items-center gap-1">
                            <MapPin className="size-3" />
                            {project.market.name}
                        </span>
                    )}

                    {locationText && (
                        <span className="min-w-0 truncate">
                            {locationText}
                        </span>
                    )}

                    {project.manager?.name && (
                        <span
                            className="inline-flex shrink-0 items-center gap-1.5"
                            title={`Project manager: ${project.manager.name}`}
                        >
                            <UserAvatar
                                name={project.manager.name}
                                src={project.manager.profile_photo_url}
                                className="size-4"
                            />
                            {project.manager.name}
                        </span>
                    )}

                    <span className={`shrink-0 ${status.tone}`}>{status.label}</span>
                </p>
            </div>

            <div className="mt-2 flex shrink-0 items-center gap-4 lg:mt-0 lg:gap-3">
                <div className="flex items-center gap-2">
                    <Progress value={progress} className="h-1 w-24" />
                    <span className="w-9 text-right text-sm font-bold tabular-nums text-foreground">
                        {progress}%
                    </span>
                </div>

                {project.latest_package?.updated_at && (
                    <span className="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                        <Clock className="size-3" />
                        {new Date(project.latest_package.updated_at).toLocaleDateString(undefined, {
                            month: 'short',
                            day: 'numeric',
                        })}
                    </span>
                )}

                <ChevronRight
                    className="hidden size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 lg:block"
                    aria-hidden
                />
            </div>
        </article>
    );
}

export default PortalProjectListItem;
