import type { CustomerEmailFilterState } from '@/components/customer-emails/customer-email-filters';
import { CustomerEmailFilters } from '@/components/customer-emails/customer-email-filters';
import { CustomerEmailTable } from '@/components/customer-emails/customer-email-table';
import { CustomerEmailTypeLegend } from '@/components/customer-emails/customer-email-type-legend';
import { SendCustomerEmailDialog } from '@/components/customer-emails/send-customer-email-dialog';
import { EmailLogItem, EmailLogPagination } from '@/components/email-logs/email-log-labels';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface CustomerEmailsIndexProps {
    logs: EmailLogItem[];
    pagination: EmailLogPagination;
    filters: CustomerEmailFilterState;
    projects: Array<{ id: number; name: string }>;
    now: string;
    customer_emails_enabled: boolean;
}

const CUSTOMER_EMAILS_INDEX_ROUTE = 'manager.customer-emails.index';

export function CustomerEmailsIndex({
    logs,
    pagination,
    filters,
    projects,
    now,
    customer_emails_enabled,
}: Readonly<CustomerEmailsIndexProps>) {
    const [activeLog, setActiveLog] = React.useState<EmailLogItem | null>(null);
    const [dialogOpen, setDialogOpen] = React.useState(false);
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());
    const [confirmOpen, setConfirmOpen] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);

    // Drop selections for rows that left the current page (filter/page change).
    React.useEffect(() => {
        setSelectedIds((prev) => {
            if (prev.size === 0) return prev;
            const visible = new Set(logs.map((log) => log.id));
            const next = new Set([...prev].filter((id) => visible.has(id)));
            return next.size === prev.size ? prev : next;
        });
    }, [logs]);

    const handleFilterChange = (key: keyof CustomerEmailFilterState, value: string | undefined) => {
        const query = {
            ...filters,
            [key]: value,
            page: undefined, // Reset to first page on filter change
        };

        // Remove undefined keys
        const cleanQuery = Object.fromEntries(
            Object.entries(query).filter(([_, v]) => v !== undefined && v !== '')
        );

        router.get(route(CUSTOMER_EMAILS_INDEX_ROUTE), cleanQuery, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        router.get(route(CUSTOMER_EMAILS_INDEX_ROUTE), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        router.get(route(CUSTOMER_EMAILS_INDEX_ROUTE), { ...filters, page }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openSendDialog = (log: EmailLogItem) => {
        setActiveLog(log);
        setDialogOpen(true);
    };

    const toggle = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const toggleAll = () => {
        setSelectedIds((prev) =>
            logs.length > 0 && logs.every((log) => prev.has(log.id))
                ? new Set()
                : new Set(logs.map((log) => log.id)),
        );
    };

    const handleDelete = () => {
        if (selectedIds.size === 0) return;

        setIsDeleting(true);
        router.delete(route('manager.customer-emails.destroyBulk'), {
            data: { ids: [...selectedIds] },
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                setSelectedIds(new Set());
                setIsDeleting(false);
            },
            onError: () => {
                toast.error('Could not delete the selected emails. Please try again.');
                setIsDeleting(false);
            },
        });
    };

    const globallyDisabledBadge = customer_emails_enabled ? undefined : (
        <span
            className="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300"
            title="Customer emails kill switch is active (CUSTOMER_EMAILS_ENABLED=false). Delivery and scheduling are disabled."
        >
            <AlertTriangle className="size-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
            Globally Disabled
        </span>
    );

    const emailNoun = selectedIds.size === 1 ? 'email' : 'emails';
    const deleteButtonTitle = selectedIds.size === 0
        ? 'Select emails below to delete them'
        : `Delete ${selectedIds.size} selected ${emailNoun}`;

    return (
        <AppLayout
            title="Customer Emails"
            headerTags={globallyDisabledBadge}
            headerSubtitle={
                <div className="space-y-1.5">
                    <CustomerEmailTypeLegend />
                </div>
            }
            headerActions={
                <Button
                    type="button"
                    variant="destructive"
                    className="gap-2 text-xs shrink-0"
                    disabled={selectedIds.size === 0}
                    title={deleteButtonTitle}
                    onClick={() => setConfirmOpen(true)}
                >
                    <Trash2 />
                    Delete{selectedIds.size > 0 ? ` (${selectedIds.size})` : ''}
                </Button>
            }
        >
            <div className="space-y-4">
                <CustomerEmailFilters
                    filters={filters}
                    projects={projects}
                    onFilterChange={handleFilterChange}
                    onReset={handleReset}
                />

                <CustomerEmailTable
                    logs={logs}
                    pagination={pagination}
                    onOpenSendDialog={openSendDialog}
                    onPageChange={handlePageChange}
                    selectedIds={selectedIds}
                    onToggle={toggle}
                    onToggleAll={toggleAll}
                />

                <SendCustomerEmailDialog
                    log={activeLog}
                    open={dialogOpen}
                    now={now}
                    customerEmailsEnabled={customer_emails_enabled}
                    onClose={() => {
                        setDialogOpen(false);
                        setActiveLog(null);
                    }}
                />

                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={(open) => {
                        if (!open && !isDeleting) setConfirmOpen(false);
                    }}
                    title={`Delete ${selectedIds.size} customer email${selectedIds.size === 1 ? '' : 's'}?`}
                    description="This permanently removes the selected entries from the database, including failed or stuck sending emails. This action cannot be undone."
                    confirmLabel="Delete"
                    variant="destructive"
                    loading={isDeleting}
                    onConfirm={handleDelete}
                />
            </div>
        </AppLayout>
    );
}

export default CustomerEmailsIndex;
