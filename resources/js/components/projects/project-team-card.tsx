import * as React from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmailLink } from '@/components/shared/contact-links';
import {
    PrimaryTechnicianBadge,
    PrimaryTechnicianStar,
} from '@/components/projects/primary-technician-star';
import { ProjectShowDetail } from '@/components/projects/project-show-types';
import { User, UserCog, Users } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ProjectTeamCardProps {
    project: ProjectShowDetail;
    /** Managers and admins may pick the project's primary field technician. */
    canSetPrimary?: boolean;
}

interface TeamMember {
    id: number;
    name: string;
    email?: string | null;
    isPrimary?: boolean;
}

/** One assigned person rendered as a compact row. */
function MemberRow({
    icon,
    member,
    trailing,
}: Readonly<{ icon: React.ReactNode; member: TeamMember; trailing?: React.ReactNode }>) {
    return (
        <div className="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2">
            {icon}
            <span
                className={cn(
                    'text-foreground flex-1 truncate',
                    member.isPrimary ? 'text-xs font-semibold' : 'text-xs font-medium'
                )}
            >
                {member.name}
            </span>
            {member.isPrimary ? <PrimaryTechnicianBadge /> : null}
            {member.email ? <EmailLink email={member.email} className="text-[10px]" /> : null}
            {trailing}
        </div>
    );
}

function SectionLabel({ children }: Readonly<{ children: React.ReactNode }>) {
    return (
        <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
            {children}
        </p>
    );
}

/**
 * Who is on the project. Every technician is listed — no pager — so the whole
 * crew is visible at a glance, with the primary technician starred and labelled.
 */
export function ProjectTeamCard({ project, canSetPrimary = false }: Readonly<ProjectTeamCardProps>) {
    const [pendingId, setPendingId] = React.useState<number | null>(null);

    const primaryId = project.primary_supervisor_id ?? null;
    // The backend assigns a single manager per project.
    const manager = project.manager;
    const technicians: TeamMember[] = project.field_supervisors ?? [];

    const setPrimary = (technicianId: number) => {
        setPendingId(technicianId);
        router.put(
            route('projects.supervisors.primary', project.hash_id || project.id),
            // Clicking the current primary's star clears it.
            { user_id: primaryId === technicianId ? null : technicianId },
            { preserveScroll: true, onFinish: () => setPendingId(null) }
        );
    };

    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <UserCog className="size-4 text-primary" /> Who&apos;s on this job
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                <div className="space-y-2">
                    <SectionLabel>Manager</SectionLabel>
                    {manager ? (
                        <MemberRow
                            icon={<User className="size-3.5 text-primary shrink-0" />}
                            member={manager}
                        />
                    ) : (
                        <p className="text-xs text-muted-foreground">No manager assigned yet.</p>
                    )}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-2">
                        <SectionLabel>Field Technicians</SectionLabel>
                        {canSetPrimary && technicians.length > 1 ? (
                            <p className="text-[10px] text-muted-foreground">
                                Star the primary technician
                            </p>
                        ) : null}
                    </div>
                    {technicians.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            No field technicians assigned yet.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-1.5">
                            {technicians.map((technician) => (
                                <MemberRow
                                    key={technician.id}
                                    icon={<Users className="size-3.5 text-primary shrink-0" />}
                                    member={{
                                        ...technician,
                                        isPrimary: primaryId === technician.id,
                                    }}
                                    trailing={
                                        <PrimaryTechnicianStar
                                            name={technician.name}
                                            isPrimary={primaryId === technician.id}
                                            canEdit={canSetPrimary}
                                            disabled={pendingId === technician.id}
                                            onClick={() => setPrimary(technician.id)}
                                        />
                                    }
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
