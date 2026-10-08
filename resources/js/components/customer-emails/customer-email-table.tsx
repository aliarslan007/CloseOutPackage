import * as React from 'react';
import { router } from '@inertiajs/react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { EMAIL_TYPE_LABELS, EmailLogItem, EmailLogPagination } from '@/components/email-logs/email-log-labels';
import { CustomerEmailStatusBadge, CustomerEmailTimestamp } from '@/components/customer-emails/customer-email-status-badge';
import { Send, RotateCw, Ban, ChevronLeft, ChevronRight, Mail } from 'lucide-react';
import { toast } from 'sonner';
import { Checkbox } from '@/components/Checkbox';

interface CustomerEmailTableProps {
    logs: EmailLogItem[];
    pagination: EmailLogPagination;
    onOpenSendDialog: (log: EmailLogItem) => void;
    onPageChange: (page: number) => void;
    selectedIds: Set<number>;
    onToggle: (id: number) => void;
    onToggleAll: () => void;
}

export function CustomerEmailTable({
    logs,
    pagination,
    onOpenSendDialog,
    onPageChange,
    selectedIds,
    onToggle,
    onToggleAll,
}: Readonly<CustomerEmailTableProps>) {
    const allSelected = logs.length > 0 && logs.every((log) => selectedIds.has(log.id));
    const handleCancel = (log: EmailLogItem) => {
        router.post(route('manager.customer-emails.cancel', log.id), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Scheduled email cancelled.'),
            onError: () => toast.error('Could not cancel this email.'),
        });
    };

    const handleRetry = (log: EmailLogItem) => {
        router.post(route('manager.customer-emails.retry', log.id), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Email retry queued.'),
            onError: () => toast.error('Could not retry this email.'),
        });
    };

    if (logs.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
                <Mail className="size-10 text-muted-foreground/60" />
                <h3 className="mt-3 text-sm font-semibold">No customer emails found</h3>
                <p className="mt-1 text-xs text-muted-foreground">
                    Customer email intents recorded for review, scheduling, or delivery will appear here.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="rounded-md border border-border/80 bg-card shadow-2xs overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow className="h-9">
                            <TableHead className="w-10">
                                <Checkbox
                                    checked={allSelected}
                                    onChange={onToggleAll}
                                    aria-label="Select all customer emails on this page"
                                />
                            </TableHead>
                            <TableHead className="w-[200px]">Recipient</TableHead>
                            <TableHead className="w-[190px]">Type / Project</TableHead>
                            <TableHead>Subject</TableHead>
                            <TableHead className="w-[140px]">Status</TableHead>
                            <TableHead className="w-[150px]">Timestamp</TableHead>
                            <TableHead className="w-[150px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {logs.map((log) => {
                            const isOpen = log.status === 'ready' || log.status === 'scheduled';
                            const isScheduled = log.status === 'scheduled';
                            const isFailed = log.status === 'failed';
                            const isSent = log.status === 'sent';

                            return (
                                <TableRow key={log.id} className="h-11">
                                    <TableCell className="py-2" onClick={(e) => e.stopPropagation()}>
                                        <Checkbox
                                            checked={selectedIds.has(log.id)}
                                            onChange={() => onToggle(log.id)}
                                            aria-label={`Select customer email for ${log.recipient_email}`}
                                        />
                                    </TableCell>
                                    <TableCell className="py-2">
                                        <div className="flex flex-col min-w-0">
                                            <span className="font-medium text-xs text-foreground truncate max-w-[190px]">
                                                {log.recipient_name || log.recipient_email}
                                            </span>
                                            {log.recipient_name && (
                                                <span className="text-[11px] text-muted-foreground truncate max-w-[190px]">
                                                    {log.recipient_email}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="py-2">
                                        <div className="flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                            <span className="text-xs font-medium text-foreground">
                                                {EMAIL_TYPE_LABELS[log.type] ?? log.type}
                                            </span>
                                            {log.project?.name && (
                                                <span
                                                    className="text-[11px] text-muted-foreground truncate max-w-[150px]"
                                                    title={log.project.name}
                                                >
                                                    • {log.project.name}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="py-2">
                                        <span className="text-xs font-normal text-foreground line-clamp-1" title={log.subject}>
                                            {log.subject}
                                        </span>
                                    </TableCell>
                                    <TableCell className="py-2">
                                        <CustomerEmailStatusBadge log={log} />
                                    </TableCell>
                                    <TableCell className="py-2 text-xs">
                                        <CustomerEmailTimestamp log={log} />
                                    </TableCell>
                                    <TableCell className="py-2 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {isOpen && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="default"
                                                        size="sm"
                                                        className="h-7 px-2 text-[11px] gap-1"
                                                        onClick={() => onOpenSendDialog(log)}
                                                    >
                                                        <Send className="size-3" />
                                                        {isScheduled ? 'Reschedule' : 'Send / Schedule'}
                                                    </Button>
                                                    {isScheduled && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2 text-[11px] text-destructive hover:bg-destructive/10"
                                                            title="Cancel scheduled delivery"
                                                            onClick={() => handleCancel(log)}
                                                        >
                                                            <Ban className="size-3" />
                                                        </Button>
                                                    )}
                                                </>
                                            )}

                                            {isFailed && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 px-2 text-[11px] gap-1"
                                                        onClick={() => handleRetry(log)}
                                                    >
                                                        <RotateCw className="size-3" /> Retry
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 px-2 text-[11px]"
                                                        onClick={() => onOpenSendDialog(log)}
                                                    >
                                                        Resend
                                                    </Button>
                                                </>
                                            )}

                                            {(isSent || log.status === 'cancelled') && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 px-2 text-[11px] gap-1"
                                                    onClick={() => onOpenSendDialog(log)}
                                                >
                                                    <RotateCw className="size-3" /> Resend
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            {pagination.last_page > 1 && (
                <div className="flex items-center justify-between text-xs text-muted-foreground px-1">
                    <div>
                        Showing {pagination.from ?? 0} to {pagination.to ?? 0} of {pagination.total} entries
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 px-2.5 text-xs"
                            disabled={pagination.current_page <= 1}
                            onClick={() => onPageChange(pagination.current_page - 1)}
                        >
                            <ChevronLeft className="mr-1 size-3.5" /> Previous
                        </Button>
                        <span className="px-2">
                            Page {pagination.current_page} of {pagination.last_page}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 px-2.5 text-xs"
                            disabled={pagination.current_page >= pagination.last_page}
                            onClick={() => onPageChange(pagination.current_page + 1)}
                        >
                            Next <ChevronRight className="ml-1 size-3.5" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
