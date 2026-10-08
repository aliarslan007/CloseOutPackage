import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Hash, MapPin } from 'lucide-react';
import React, { useMemo } from 'react';
import { CustomerProjectDetails } from '../types';

/** First letters of a name, e.g. "Dana Whitfield" -> "DW" (avatar fallback). */
function getInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');
}

const PROJECT_STATUS_STYLES: Record<string, { dot: string; badge: string }> = {
    completed: {
        dot: 'bg-emerald-500',
        badge: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    },
    active: {
        dot: 'bg-sky-500',
        badge: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    },
    in_progress: {
        dot: 'bg-sky-500',
        badge: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    },
    pending: {
        dot: 'bg-amber-500',
        badge: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    rejected: {
        dot: 'bg-rose-500',
        badge: 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-400',
    },
    default: {
        dot: 'bg-slate-400',
        badge: 'border-border/80 bg-muted/50 text-muted-foreground',
    },
};

export function useCustomerProjectHeaderMeta(project: CustomerProjectDetails) {
    const statusConfig = PROJECT_STATUS_STYLES[project.status] ?? PROJECT_STATUS_STYLES.default;

    const headerTags = useMemo(
        () => (
            <div className="flex items-center gap-2 flex-wrap">
                {project.ps_number && (
                    <span className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-bold tabular-nums text-foreground/80 border border-border/60">
                        <Hash className="size-3 text-primary" /> PS {project.ps_number}
                    </span>
                )}

                {project.market?.name && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-muted-foreground">
                        <MapPin className="size-3 text-primary" />
                        {project.market.name}
                    </span>
                )}

                <Badge
                    variant="outline"
                    className={cn(
                        'gap-1.5 text-[10px] font-bold uppercase tracking-wider',
                        statusConfig.badge
                    )}
                >
                    <span className={cn('size-1.5 rounded-full', statusConfig.dot)} />
                    {project.status.replaceAll('_', ' ')}
                </Badge>
            </div>
        ),
        [project.ps_number, project.market?.name, project.status, statusConfig]
    );

    const headerSubtitle = useMemo(
        () => (
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {project.address && (
                    <span className="flex items-center gap-1">
                        <MapPin className="size-3 text-muted-foreground" />
                        {project.address}
                        {project.city ? `, ${project.city}` : ''}
                        {project.state ? `, ${project.state}` : ''}
                    </span>
                )}

                {project.manager?.name && (
                    <span className="inline-flex items-center gap-1.5 leading-none">
                        <span className="text-muted-foreground">Project Manager</span>
                        {project.manager.profile_photo_url ? (
                            <img
                                src={project.manager.profile_photo_url}
                                alt=""
                                loading="lazy"
                                className="size-5 shrink-0 rounded-full border border-border object-cover"
                            />
                        ) : (
                            <span
                                aria-hidden
                                className="inline-flex size-5 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-[9px] leading-none font-bold text-muted-foreground"
                            >
                                {getInitials(project.manager.name)}
                            </span>
                        )}
                        <span className="font-medium text-foreground">{project.manager.name}</span>
                    </span>
                )}
            </div>
        ),
        [project.address, project.city, project.state, project.manager?.name, project.manager?.profile_photo_url]
    );

    return { headerTags, headerSubtitle };
}
