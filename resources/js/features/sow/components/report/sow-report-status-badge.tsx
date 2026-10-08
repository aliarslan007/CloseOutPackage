import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SowStatus } from '../../types/sow.types';

const STATUS_META: Record<
    SowStatus,
    { label: string; variant: 'secondary' | 'warning' | 'success' | 'outline' }
> = {
    draft: { label: 'Draft', variant: 'secondary' },
    in_review: { label: 'In Review', variant: 'warning' },
    published: { label: 'Published', variant: 'success' },
    archived: { label: 'Archived', variant: 'outline' },
};

export interface SowReportStatusBadgeProps {
    status: SowStatus;
    className?: string;
}

export function SowReportStatusBadge({ status, className }: Readonly<SowReportStatusBadgeProps>) {
    const meta = STATUS_META[status];

    return (
        <Badge variant={meta.variant} className={cn('capitalize', className)}>
            {meta.label}
        </Badge>
    );
}
