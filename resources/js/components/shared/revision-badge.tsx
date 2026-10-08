import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export interface RevisionBadgeProps {
    revisionNumber: string | number;
    date?: string;
    className?: string;
}

export function RevisionBadge({ revisionNumber, date, className }: Readonly<RevisionBadgeProps>) {
    let formattedRevision = `R${revisionNumber}`;

    if (typeof revisionNumber === 'string' && revisionNumber.startsWith('R')) {
        formattedRevision = revisionNumber;
    }

    return (
        <Badge
            variant="outline"
            className={cn('font-mono text-xs gap-1 border-muted-foreground/30 bg-muted/30', className)}
        >
            <span className="font-bold text-primary">{formattedRevision}</span>
            {date && <span className="text-muted-foreground">• {date}</span>}
        </Badge>
    );
}

export default RevisionBadge;
