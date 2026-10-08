import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChevronRight, FolderKanban } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ProjectHealthProps {
    totalProjects: number;
    projectsNeedingManager: number;
    projectsNeedingSupervisor: number;
    projectsNotStarted: number;
    projectsInProgress: number;
    completedProjects: number;
}

interface Segment {
    key: string;
    label: string;
    value: number;
    href: string;
    barClass: string;
    dotClass: string;
}

const projectsLink = (params: Record<string, string>) => route('projects.index', params);

export function ProjectHealth({
    totalProjects,
    projectsNeedingManager,
    projectsNeedingSupervisor,
    projectsNotStarted,
    projectsInProgress,
    completedProjects,
}: ProjectHealthProps) {
    const segments: Segment[] = [
        {
            key: 'needs_manager',
            label: 'Needs manager',
            value: projectsNeedingManager,
            href: projectsLink({ assignment: 'unassigned' }),
            barClass: 'bg-destructive',
            dotClass: 'bg-destructive',
        },
        {
            key: 'needs_supervisor',
            label: 'Needs supervisor',
            value: projectsNeedingSupervisor,
            href: projectsLink({ assignment: 'needs_supervisor' }),
            barClass: 'bg-amber-500',
            dotClass: 'bg-amber-500',
        },
        {
            key: 'not_started',
            label: 'Not started',
            value: projectsNotStarted,
            href: projectsLink({ assignment: 'not_started' }),
            barClass: 'bg-muted-foreground/50',
            dotClass: 'bg-muted-foreground',
        },
        {
            key: 'in_progress',
            label: 'In progress',
            value: projectsInProgress,
            href: projectsLink({ assignment: 'in_progress' }),
            barClass: 'bg-primary',
            dotClass: 'bg-primary',
        },
        {
            key: 'completed',
            label: 'Completed',
            value: completedProjects,
            href: projectsLink({ status: 'completed' }),
            barClass: 'bg-emerald-500',
            dotClass: 'bg-emerald-500',
        },
    ];

    const total = Math.max(totalProjects, 1);

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <FolderKanban className="size-4 text-primary" />
                    Project Health
                </CardTitle>
                <CardDescription>
                    {totalProjects} project{totalProjects === 1 ? '' : 's'} — every site fits into
                    exactly one bucket.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div
                    className="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full"
                    role="img"
                    aria-label="Project lifecycle distribution"
                >
                    {segments.map((segment) =>
                        segment.value > 0 ? (
                            <div
                                key={segment.key}
                                className={cn('h-full rounded-full', segment.barClass)}
                                style={{ width: `${(segment.value / total) * 100}%` }}
                            />
                        ) : null
                    )}
                </div>

                <ul className="space-y-1">
                    {segments.map((segment) => (
                        <li key={segment.key}>
                            <Link
                                href={segment.href}
                                className="group flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                            >
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <span className={cn('size-2.5 shrink-0 rounded-full', segment.dotClass)} />
                                    <span className="truncate text-xs font-medium text-muted-foreground group-hover:text-foreground">
                                        {segment.label}
                                    </span>
                                </span>
                                <span className="flex shrink-0 items-center gap-1.5">
                                    <span className="text-sm font-bold tabular-nums">
                                        {segment.value}
                                    </span>
                                    <ChevronRight className="size-3.5 text-muted-foreground/60 transition-colors group-hover:text-foreground" />
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

export default ProjectHealth;
