import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/Checkbox';
import { EmailLink } from '@/components/shared/contact-links';
import {
    EmailLogItem,
    EmailLogPagination,
    EMAIL_STATUS_LABELS,
    EMAIL_TYPE_LABELS,
    statusBadgeVariant,
} from '@/components/email-logs/email-log-labels';
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-react';

interface EmailLogTableProps {
    logs: EmailLogItem[];
    pagination: EmailLogPagination;
    goToPage: (page: number) => void;
    canDelete: boolean;
    selectedIds: Set<number>;
    onToggle: (id: number) => void;
    onToggleAll: () => void;
}

export function EmailLogTable({ logs, pagination, goToPage, canDelete, selectedIds, onToggle, onToggleAll }: Readonly<EmailLogTableProps>) {
    const allSelected = logs.length > 0 && logs.every((log) => selectedIds.has(log.id));

    return (
        <Card className="rounded-md border border-border/80 shadow-2xs overflow-hidden">
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-muted/40 border-b border-border text-muted-foreground uppercase text-[11px] tracking-wider">
                            <tr>
                                {canDelete && (
                                    <th className="p-3.5 w-10">
                                        <Checkbox
                                            checked={allSelected}
                                            onChange={onToggleAll}
                                            aria-label="Select all email logs on this page"
                                        />
                                    </th>
                                )}
                                <th className="p-3.5">Timestamp</th>
                                <th className="p-3.5">Recipient</th>
                                <th className="p-3.5">Sender</th>
                                <th className="p-3.5">Subject</th>
                                <th className="p-3.5">Type</th>
                                <th className="p-3.5">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/60">
                            {logs.map((log) => (
                                <tr
                                    key={log.id}
                                    className={
                                        'hover:bg-muted/20 transition-colors' +
                                        (selectedIds.has(log.id) ? ' bg-muted/30' : '')
                                    }
                                >
                                    {canDelete && (
                                        <td className="p-3.5" onClick={(e) => e.stopPropagation()}>
                                            <Checkbox
                                                checked={selectedIds.has(log.id)}
                                                onChange={() => onToggle(log.id)}
                                                aria-label={`Select email log for ${log.recipient_email}`}
                                            />
                                        </td>
                                    )}
                                    <td className="p-3.5 text-muted-foreground whitespace-nowrap">
                                        <div className="flex items-center gap-1.5">
                                            <Calendar className="size-3.5 shrink-0" />
                                            {new Date(log.sent_at ?? log.created_at).toLocaleString()}
                                        </div>
                                    </td>
                                    <td className="p-3.5">
                                        <div className="min-w-0 max-w-[220px]">
                                            <p className="font-bold text-foreground truncate" title={log.recipient_name ?? undefined}>
                                                {log.recipient_name ?? '—'}
                                            </p>
                                            <p className="text-[11px]">
                                                <EmailLink email={log.recipient_email} className="text-[11px] truncate inline-block max-w-full align-middle" />
                                            </p>
                                        </div>
                                    </td>
                                    <td className="p-3.5 text-muted-foreground">
                                        <div className="min-w-0 max-w-[220px]">
                                            <p className="truncate" title={log.sender_name ?? undefined}>
                                                {log.sender_name ?? '—'}
                                            </p>
                                            <p className="text-[11px]">
                                                <EmailLink email={log.sender_email} className="text-[11px] truncate inline-block max-w-full align-middle" />
                                            </p>
                                        </div>
                                    </td>
                                    <td className="p-3.5 max-w-[280px]">
                                        <p
                                            className="font-medium text-foreground truncate"
                                            title={log.subject}
                                        >
                                            {log.subject}
                                        </p>
                                        {log.project?.name && (
                                            <p className="text-[11px] text-muted-foreground">{log.project.name}</p>
                                        )}
                                        {log.failed_reason && (
                                            <p className="text-[11px] text-destructive">{log.failed_reason}</p>
                                        )}
                                    </td>
                                    <td className="p-3.5">
                                        <Badge variant="outline" className="text-[10px]">
                                            {EMAIL_TYPE_LABELS[log.type] ?? log.type}
                                        </Badge>
                                    </td>
                                    <td className="p-3.5">
                                        <Badge variant={statusBadgeVariant(log.status)} className="text-[10px]">
                                            {EMAIL_STATUS_LABELS[log.status] ?? log.status}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
            {pagination.last_page > 1 && (
                <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
                    <p className="text-xs text-muted-foreground">
                        Showing {pagination.from ?? 0}–{pagination.to ?? 0} of {pagination.total}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1 text-xs"
                            disabled={pagination.current_page <= 1}
                            onClick={() => goToPage(pagination.current_page - 1)}
                        >
                            <ChevronLeft className="size-3.5" /> Previous
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Page {pagination.current_page} of {pagination.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1 text-xs"
                            disabled={pagination.current_page >= pagination.last_page}
                            onClick={() => goToPage(pagination.current_page + 1)}
                        >
                            Next <ChevronRight className="size-3.5" />
                        </Button>
                    </div>
                </div>
            )}
        </Card>
    );
}
