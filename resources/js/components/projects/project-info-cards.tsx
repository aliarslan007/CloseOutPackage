import { PROJECT_STATUS_META, ProjectShowDetail } from '@/components/projects/project-show-types';
import { EmailLink, PhoneLink } from '@/components/shared/contact-links';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    Activity,
    AlertCircle,
    Archive,
    CheckCircle2,
    Clock,
    FileCheck,
    FileText,
    Hash,
    HelpCircle,
    Mail,
    MapPin,
    Phone,
    StickyNote,
    User,
} from 'lucide-react';
import * as React from 'react';

const EMERALD_BG_CLASS = 'bg-emerald-500/10 border-emerald-500/20 dark:bg-emerald-500/5';

const STATUS_STYLE_MAP: Record<
    string,
    {
        icon: React.ReactNode;
        bgClass: string;
        textClass: string;
        glowClass: string;
    }
> = {
    pending: {
        icon: <Clock className="size-6 text-sky-500 animate-pulse" />,
        bgClass: 'bg-sky-500/10 border-sky-500/20 dark:bg-sky-500/5',
        textClass: 'text-sky-700 dark:text-sky-400',
        glowClass: 'shadow-sky-500/5',
    },
    in_progress: {
        icon: <Activity className="size-6 text-emerald-500 animate-pulse" />,
        bgClass: EMERALD_BG_CLASS,
        textClass:
            'bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-600 bg-clip-text text-transparent font-extrabold dark:from-emerald-400 dark:via-teal-300 dark:to-emerald-400 animate-pulse',
        glowClass: 'shadow-emerald-500/10 ring-2 ring-emerald-500/10',
    },
    submitted: {
        icon: <FileCheck className="size-6 text-amber-500 animate-bounce" />,
        bgClass: 'bg-amber-500/10 border-amber-500/20 dark:bg-amber-500/5',
        textClass:
            'bg-gradient-to-r from-amber-600 via-orange-500 to-amber-600 bg-clip-text text-transparent font-extrabold dark:from-amber-400 dark:via-orange-300 dark:to-amber-400 animate-pulse',
        glowClass: 'shadow-amber-500/10 ring-2 ring-amber-500/10',
    },
    approved: {
        icon: <CheckCircle2 className="size-6 text-emerald-500 animate-pulse" />,
        bgClass: EMERALD_BG_CLASS,
        textClass: 'text-emerald-700 dark:text-emerald-400 font-bold',
        glowClass: 'shadow-emerald-500/5',
    },
    completed: {
        icon: <CheckCircle2 className="size-6 text-emerald-500" />,
        bgClass: EMERALD_BG_CLASS,
        textClass: 'text-emerald-700 dark:text-emerald-400 font-bold',
        glowClass: 'shadow-emerald-500/5',
    },
    rejected: {
        icon: <AlertCircle className="size-6 text-rose-500 animate-pulse" />,
        bgClass: 'bg-rose-500/10 border-rose-500/20 dark:bg-rose-500/5',
        textClass:
            'bg-gradient-to-r from-rose-600 via-pink-500 to-rose-600 bg-clip-text text-transparent font-extrabold dark:from-rose-400 dark:via-pink-300 dark:to-rose-400 animate-pulse',
        glowClass: 'shadow-rose-500/10 ring-2 ring-rose-500/10',
    },
    archived: {
        icon: <Archive className="size-6 text-muted-foreground" />,
        bgClass: 'bg-muted border-border',
        textClass: 'text-muted-foreground font-bold',
        glowClass: 'shadow-none',
    },
};

export interface ProjectInfoCardsProps {
    project: ProjectShowDetail;
}

export function ProjectInfoCards({ project }: Readonly<ProjectInfoCardsProps>) {
    return (
        <>
            <ProjectSiteInformationCard project={project} />
            <ProjectStatusCard project={project} />
        </>
    );
}

function Field({
    icon,
    label,
    value,
    empty,
}: Readonly<{ icon?: React.ReactNode; label: string; value?: React.ReactNode; empty?: string }>) {
    return (
        <div className="space-y-0.5">
            <p className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                {icon}
                {label}
            </p>
            {value !== undefined && value !== null && value !== '' ? (
                <p className="text-foreground text-xs">{value}</p>
            ) : (
                <p className="text-xs text-muted-foreground/60 italic">
                    {empty ?? 'Not provided.'}
                </p>
            )}
        </div>
    );
}

export function ProjectSiteInformationCard({ project }: Readonly<ProjectInfoCardsProps>) {
    const locationParts = [project.address, project.city, project.state, project.zip]
        .filter((part): part is string => Boolean(part))
        .join(', ');

    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <MapPin className="size-4 text-primary" /> Site Information
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4 text-xs">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field
                        icon={<Hash className="size-3" />}
                        label="PS Number"
                        value={project.ps_number}
                        empty="No PS number on file."
                    />
                    <Field
                        icon={<FileText className="size-3" />}
                        label="Project Number"
                        value={project.project_number}
                        empty="No project number on file."
                    />
                    <div className="sm:col-span-2">
                        <Field
                            icon={<MapPin className="size-3" />}
                            label="Address"
                            value={locationParts}
                            empty="No address on file."
                        />
                    </div>
                    <Field
                        icon={<User className="size-3" />}
                        label="Verizon Engineer"
                        value={project.vzw_engineer_name}
                        empty="No engineer assigned."
                    />
                    <Field
                        icon={<Mail className="size-3" />}
                        label="Verizon Engineer Email"
                        value={
                            project.vzw_engineer_email ? (
                                <EmailLink email={project.vzw_engineer_email} />
                            ) : undefined
                        }
                        empty="No email on file."
                    />
                    <Field
                        icon={<Phone className="size-3" />}
                        label="Verizon Engineer Phone"
                        value={
                            project.vzw_engineer_phone ? (
                                <PhoneLink phone={project.vzw_engineer_phone} />
                            ) : undefined
                        }
                        empty="No phone on file."
                    />
                </div>

                <div className="space-y-0.5 border-t border-border/60 pt-3">
                    <p className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                        <StickyNote className="size-3" />
                        Project Notes
                    </p>
                    <p className="text-foreground text-xs whitespace-pre-wrap">
                        {project.notes || (
                            <span className="text-muted-foreground/60 italic">
                                No notes on file.
                            </span>
                        )}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

export function ProjectStatusCard({ project }: Readonly<ProjectInfoCardsProps>) {
    const statusKey = project.status || 'pending';
    const statusMeta = PROJECT_STATUS_META[statusKey] || {
        label: statusKey,
        explanation: 'Status information unavailable.',
    };
    const styleMeta = STATUS_STYLE_MAP[statusKey] || {
        icon: <HelpCircle className="size-6 text-muted-foreground" />,
        bgClass: 'bg-muted border-border',
        textClass: 'text-muted-foreground',
        glowClass: '',
    };

    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <StickyNote className="size-4 text-primary" /> What&apos;s happening
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col items-center justify-center text-center p-6 min-h-[160px]">
                <div className="space-y-4 flex flex-col items-center justify-center">
                    {/* Visual Badge Icon */}
                    <div
                        className={cn(
                            'flex size-14 items-center justify-center rounded-full border shadow-sm transition-all duration-300',
                            styleMeta.bgClass,
                            styleMeta.glowClass
                        )}
                    >
                        {styleMeta.icon}
                    </div>

                    {/* Status Label with Glow/Gradient text */}
                    <div className="space-y-1.5">
                        <p
                            className={cn(
                                'text-base tracking-wide uppercase font-black',
                                styleMeta.textClass
                            )}
                        >
                            {statusMeta.label}
                        </p>
                        <p className="text-xs text-muted-foreground max-w-[220px] leading-relaxed mx-auto font-medium">
                            {statusMeta.explanation}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
