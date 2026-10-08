import { ProjectShowDetail } from '@/components/projects/project-show-types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    Award,
    CalendarClock,
    CheckCircle2,
    Hammer,
    Inbox,
    PackageCheck,
} from 'lucide-react';
import * as React from 'react';

export interface ProjectTimelineCardProps {
    project: ProjectShowDetail;
}

type Milestone = {
    label: string;
    value?: string | null;
    icon: React.ReactNode;
    auto?: boolean;
};

/** "2026-09-22T00:00:00.000000Z" or "2026-09-22" -> "Sep 22, 2026"; null-safe. */
function formatDate(value?: string | null): string | null {
    if (!value) {
        return null;
    }
    const date = new Date(value.slice(0, 10));
    if (Number.isNaN(date.getTime())) {
        return null;
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function daysBetween(from?: string | null, to?: string | null): number | null {
    if (!from || !to) {
        return null;
    }
    const a = new Date(from.slice(0, 10)).getTime();
    const b = new Date(to.slice(0, 10)).getTime();
    if (Number.isNaN(a) || Number.isNaN(b) || b < a) {
        return null;
    }
    return Math.round((b - a) / 86_400_000);
}

export function ProjectTimelineCard({ project }: Readonly<ProjectTimelineCardProps>) {
    const milestones: Milestone[] = [
        { label: 'Awarded to Us', value: project.awarded_date, icon: <Award className="size-4" /> },
        { label: 'REC Issued', value: project.rec_issued_date, icon: <Inbox className="size-4" /> },
        {
            label: 'Desired Completion',
            value: project.completed_date,
            icon: <CalendarClock className="size-4" />,
        },
        {
            label: 'Construction Began',
            value: project.construction_started_date,
            icon: <Hammer className="size-4" />,
        },
        {
            label: 'Site Completed',
            value: project.site_completed_date,
            icon: <CheckCircle2 className="size-4" />,
        },
        {
            label: 'Closeout Package',
            value: project.closeout_package_date,
            icon: <PackageCheck className="size-4" />,
            auto: true,
        },
    ];

    const durationDays = daysBetween(project.awarded_date, project.closeout_package_date);

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <CalendarClock className="size-4 text-primary" /> Project Timeline
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ol className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {milestones.map((milestone) => {
                        const formatted = formatDate(milestone.value);
                        return (
                            <li
                                key={milestone.label}
                                className={cn(
                                    'flex flex-col gap-1.5 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5',
                                    !formatted && 'opacity-60'
                                )}
                            >
                                <span className="flex items-center gap-1.5 text-primary">
                                    {milestone.icon}
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                        {milestone.label}
                                    </span>
                                </span>
                                <span className="text-sm font-semibold text-foreground">
                                    {formatted ?? 'Not set'}
                                </span>
                                {milestone.auto && (
                                    <span className="text-[10px] text-muted-foreground">
                                        On approval
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ol>
                {durationDays !== null && (
                    <p className="mt-3 text-xs text-muted-foreground">
                        <span className="font-semibold text-foreground">{durationDays} days</span>{' '}
                        from award to closeout.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
