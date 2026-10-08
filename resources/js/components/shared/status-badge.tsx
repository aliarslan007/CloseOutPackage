import * as React from 'react';
import { Badge, BadgeProps } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type StatusType =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'in_review'
    | 'approved'
    | 'rejected'
    | 'active'
    | 'inactive'
    | 'pending'
    | 'in_progress'
    | string;

export interface StatusBadgeProps extends Omit<BadgeProps, 'variant'> {
    status: StatusType;
    label?: string;
}

const statusVariantMap: Record<string, { variant: BadgeProps['variant']; defaultLabel: string }> = {
    draft: { variant: 'outline', defaultLabel: 'Draft' },
    submitted: { variant: 'info', defaultLabel: 'Submitted' },
    under_review: { variant: 'info', defaultLabel: 'Under Review' },
    in_review: { variant: 'info', defaultLabel: 'In Review' },
    approved: { variant: 'success', defaultLabel: 'Approved' },
    rejected: { variant: 'destructive', defaultLabel: 'Rejected' },
    active: { variant: 'success', defaultLabel: 'Active' },
    inactive: { variant: 'secondary', defaultLabel: 'Inactive' },
    pending: { variant: 'warning', defaultLabel: 'Pending' },
    in_progress: { variant: 'info', defaultLabel: 'In Progress' },
    not_started: { variant: 'secondary', defaultLabel: 'Not Started' },
    completed: { variant: 'success', defaultLabel: 'Completed' },
};

function formatFallbackLabel(status: string): string {
    return status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function StatusBadge({ status, label, className, ...props }: StatusBadgeProps) {
    const safeStatus = typeof status === 'string' ? status : String(status || 'pending');
    const normalizedKey = safeStatus.toLowerCase().trim();
    const config = statusVariantMap[normalizedKey] ?? {
        variant: 'secondary',
        defaultLabel: formatFallbackLabel(safeStatus),
    };

    return (
        <Badge
            variant={config.variant}
            className={cn('font-medium', className)}
            {...props}
        >
            {label ?? config.defaultLabel}
        </Badge>
    );
}

export default StatusBadge;
