import * as React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmailLink } from '@/components/shared/contact-links';
import { VisibilityProjectDetail } from '@/components/visibility/visibility-project-types';
import { User, UserCog, Users } from 'lucide-react';

export interface VisibilityTeamCardProps {
    project: VisibilityProjectDetail;
}

export function VisibilityTeamCard({ project }: Readonly<VisibilityTeamCardProps>) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <UserCog className="size-4 text-primary" /> Who&apos;s on this job
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                        Manager
                    </p>
                    {project.manager ? (
                        <div className="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2">
                            <User className="size-3.5 text-primary shrink-0" />
                            <span className="text-xs font-medium text-foreground flex-1">
                                {project.manager.name}
                            </span>
                            <EmailLink email={project.manager.email} className="text-[10px]" />
                        </div>
                    ) : (
                        <p className="text-xs text-muted-foreground">No manager assigned yet.</p>
                    )}
                </div>

                <div className="space-y-2">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                        Field Technicians
                    </p>
                    {project.field_supervisors.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            No field technicians assigned yet.
                        </p>
                    ) : (
                        project.field_supervisors.map((supervisor) => (
                            <div
                                key={supervisor.id}
                                className="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                            >
                                <Users className="size-3.5 text-primary shrink-0" />
                                <span className="text-xs font-medium text-foreground flex-1">
                                    {supervisor.name}
                                </span>
                                <EmailLink email={supervisor.email} className="text-[10px]" />
                            </div>
                        ))
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
