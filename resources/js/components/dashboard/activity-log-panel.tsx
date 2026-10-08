import { Badge } from '@/components/ui/badge';
import { ColumnDef, DataTable } from '@/components/shared/data-table';
import { User } from 'lucide-react';

export interface ActivityRecord {
    id: number;
    log_name: string;
    description: string;
    subject_type: string;
    subject_id: number;
    causer_name: string;
    causer_email: string | null;
    properties: Record<string, unknown>;
    created_at: string;
    [key: string]: unknown;
}

export interface ActivityPage {
    data: ActivityRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface ActivityLogPanelProps {
    activities?: ActivityPage;
    isAllActivities: boolean;
}

// Values stay on one line so columns never squash on narrow screens; the table
// wrapper scrolls horizontally instead (same treatment as the email logs).
const NOWRAP_CLASS = 'whitespace-nowrap';

const activityColumns: ColumnDef<ActivityRecord>[] = [
    {
        accessorKey: 'created_at',
        header: 'Timestamp',
        className: NOWRAP_CLASS,
        cell: (row) => (
            <span className="text-muted-foreground">
                {row.created_at ? new Date(String(row.created_at)).toLocaleString() : 'Just now'}
            </span>
        ),
    },
    {
        accessorKey: 'causer_name',
        header: 'User',
        className: NOWRAP_CLASS,
        cell: (row) => (
            <span className="flex items-center gap-1.5 font-bold text-foreground">
                <User className="size-3.5 text-primary shrink-0" />
                {row.causer_name}
            </span>
        ),
    },
    {
        accessorKey: 'description',
        header: 'Action Event',
        className: NOWRAP_CLASS,
        cell: (row) => (
            <Badge variant="outline" className="capitalize text-[10px] bg-muted/50">
                {String(row.description || 'System Event')}
            </Badge>
        ),
    },
    {
        accessorKey: 'subject_type',
        header: 'Target Module',
        className: NOWRAP_CLASS,
        cell: (row) => (
            <span className="text-muted-foreground font-medium">
                {row.subject_type ? `${row.subject_type} #${row.subject_id}` : '—'}
            </span>
        ),
    },
];

export function ActivityLogPanel({
    activities,
}: Readonly<ActivityLogPanelProps>) {
    return (
        <DataTable
            columns={activityColumns}
            data={activities?.data ?? []}
            meta={
                activities
                    ? {
                          current_page: activities.current_page,
                          last_page: activities.last_page,
                          per_page: activities.per_page,
                          total: activities.total,
                          from: activities.from,
                          to: activities.to,
                      }
                    : undefined
            }
            emptyTitle="No activity logs yet"
            emptyDescription="System events will appear here live."
        />
    );
}
