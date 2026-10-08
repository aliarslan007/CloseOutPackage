import { EmailLogFilters } from '@/components/email-logs/email-log-filters';
import { EmailLogFilterState, EmailLogItem, EmailLogPagination } from '@/components/email-logs/email-log-labels';
import { EmailLogTable } from '@/components/email-logs/email-log-table';
import { useEmailLogFilters } from '@/components/email-logs/use-email-log-filters';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { Button } from '@/components/ui/button';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, router, usePage } from '@inertiajs/react';
import { Activity, Mail, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface EmailLogsPageProps {
    routeName: string;
    destroyRouteName?: string;
    title: string;
    breadcrumbLabel: string;
}

type EmailLogsPageData = PageProps<{
    logs: EmailLogItem[];
    pagination: EmailLogPagination;
    filters: Partial<EmailLogFilterState>;
    can_delete?: boolean;
}>;

export function EmailLogsPage({ routeName, destroyRouteName, title }: EmailLogsPageProps) {
    useFlashToasts();

    const { logs, pagination, filters: serverFilters, can_delete: canDelete = false } =
        usePage<EmailLogsPageData>().props;
    const actions = useEmailLogFilters(routeName, serverFilters);

    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());
    const [confirmOpen, setConfirmOpen] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);

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
        setSelectedIds((prev) => (logs.every((log) => prev.has(log.id)) ? new Set() : new Set(logs.map((log) => log.id))));
    };

    const handleDelete = () => {
        if (selectedIds.size === 0 || !canDelete || !destroyRouteName) return;

        setIsDeleting(true);
        router.delete(route(destroyRouteName), {
            data: { ids: [...selectedIds] },
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                setSelectedIds(new Set());
                setIsDeleting(false);
            },
            onError: () => {
                toast.error('Could not delete the selected email logs. Please try again.');
                setIsDeleting(false);
            },
        });
    };

    const breadcrumb = [{ label: title }];

    if (!logs) {
        return (
            <AppLayout title={title} breadcrumb={breadcrumb}>
                <Head title={title} />
                <EmptyCard
                    title="Email logs unavailable"
                    description="The email log could not be loaded. Please try refreshing the page."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title={title}
            breadcrumb={breadcrumb}
            headerActions={
                <div className="flex items-center gap-2">
                    {canDelete && selectedIds.size > 0 && (
                        <Button
                            variant="destructive"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                            onClick={() => setConfirmOpen(true)}
                        >
                            <Trash2 className="size-4" />
                            Delete ({selectedIds.size})
                        </Button>
                    )}
                    <EmailLogFilters actions={actions} />
                </div>
            }
        >
            <Head title={title} />
            <div className="space-y-4">
                {logs.length === 0 ? (
                    <EmptyCard
                        title="No email logs found"
                        description={
                            actions.hasActiveFilters
                                ? 'No emails match your search or filters. Try adjusting them.'
                                : 'Emails recorded by the platform will appear here.'
                        }
                        icon={<Mail className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <EmailLogTable
                        logs={logs}
                        pagination={pagination}
                        goToPage={(page) => actions.push({}, page)}
                        canDelete={canDelete}
                        selectedIds={selectedIds}
                        onToggle={toggle}
                        onToggleAll={toggleAll}
                    />
                )}
            </div>

            {canDelete && (
                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={(open) => {
                        if (!open && !isDeleting) setConfirmOpen(false);
                    }}
                    title={`Delete ${selectedIds.size} email log${selectedIds.size === 1 ? '' : 's'}?`}
                    description="This permanently removes the selected entries from the audit log. This action cannot be undone."
                    confirmLabel="Delete"
                    variant="destructive"
                    loading={isDeleting}
                    onConfirm={handleDelete}
                />
            )}
        </AppLayout>
    );
}
