import * as React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmailLink, PhoneLink } from '@/components/shared/contact-links';
import { VisibilityProjectDetail } from '@/components/visibility/visibility-project-types';
import { Calendar, Mail, MapPin, Phone, StickyNote } from 'lucide-react';

export interface VisibilitySiteInfoCardProps {
    project: VisibilityProjectDetail;
}

export function VisibilitySiteInfoCard({ project }: Readonly<VisibilitySiteInfoCardProps>) {
    const locationParts = [project.address, project.city, project.state, project.zip]
        .filter((part): part is string => Boolean(part))
        .join(', ');

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <MapPin className="size-4 text-primary" /> Where is the site
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-xs">
                <p className="text-foreground">{locationParts || 'No address on file.'}</p>
                {project.vzw_engineer_name && (
                    <p className="text-muted-foreground">
                        Verizon Engineer: {project.vzw_engineer_name}
                    </p>
                )}
                {project.vzw_engineer_email && (
                    <p className="flex items-center gap-1.5 text-muted-foreground">
                        <Mail className="size-3.5 shrink-0" />
                        <EmailLink email={project.vzw_engineer_email} />
                    </p>
                )}
                {project.vzw_engineer_phone && (
                    <p className="flex items-center gap-1.5 text-muted-foreground">
                        <Phone className="size-3.5 shrink-0" />
                        <PhoneLink phone={project.vzw_engineer_phone} />
                    </p>
                )}
                {project.notes && (
                    <p className="text-muted-foreground border-t border-border/60 pt-2 flex items-start gap-1.5">
                        <StickyNote className="size-3.5 shrink-0 mt-0.5" />
                        <span className="whitespace-pre-wrap">{project.notes}</span>
                    </p>
                )}
                {project.completed_date && (
                    <p className="flex items-center gap-1.5 text-muted-foreground border-t border-border/60 pt-2">
                        <Calendar className="size-3.5" />
                        Completed: {project.completed_date.slice(0, 10)}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
