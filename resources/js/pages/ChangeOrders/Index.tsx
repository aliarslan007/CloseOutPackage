import { ChangeOrderStatusBadge } from '@/components/change-orders/change-order-status-badge';
import { ChangeOrder, CustomerFilterOption } from '@/components/change-orders/change-order-types';
import {
    ChangeOrdersFinancialOverview,
    calculateSingleOrderFinancials,
} from '@/components/change-orders/change-orders-financial-overview';
import { CustomerFilterDropdown } from '@/components/change-orders/customer-filter-dropdown';
import {
    ProjectFilterDropdown,
    ProjectFilterOption,
} from '@/components/shared/project-filter-dropdown';
import { SearchInput } from '@/components/shared/search-input';
import { useChangeOrderReorder } from '@/components/change-orders/use-change-order-reorder';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router } from '@inertiajs/react';
import { MissingCustomerDialog } from '@/components/change-orders/missing-customer-dialog';
import { SelectProjectDialog } from '@/components/change-orders/select-project-dialog';
import { useChangeOrderSelection } from '@/components/change-orders/use-change-order-selection';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Eye,
    FileSpreadsheet,
    GripVertical,
    Loader2,
    Plus,
    Send,
    Users2,
    X,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface GlobalChangeOrdersIndexProps extends PageProps {
    changeOrders: ChangeOrder[];
    projects?: ProjectFilterOption[];
    customers?: CustomerFilterOption[];
}

const ITEMS_PER_PAGE = 10;

function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'number' ? amount : Number.parseFloat(String(amount)) || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(num);
}

function formatDate(isoString?: string | null): string {
    if (!isoString) return '—';
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return isoString;
    }
}

function formatTime(isoString?: string | null): string {
    if (!isoString) return '';
    try {
        const d = new Date(isoString);
        return d.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return '';
    }
}

function getInitials(name?: string): string {
    if (!name) return 'CO';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

function getChangeOrderShowUrl(
    projectIdentifier: string | number | undefined,
    orderId: number,
    isCustomer: boolean
): string {
    if (!projectIdentifier) {
        return '#';
    }
    if (isCustomer) {
        return route('customer.change-orders.show', [projectIdentifier, orderId]);
    }
    return route('projects.change-orders.show', [projectIdentifier, orderId]);
}

function renderCustomerRecipient(recipients?: ChangeOrder['recipients']) {
    if (!recipients || recipients.length === 0) {
        return <span className="text-muted-foreground text-xs italic">—</span>;
    }

    const allNames = recipients
        .map((r) => r.user?.name)
        .filter(Boolean)
        .join(', ');

    return (
        <div className="flex items-center gap-2 min-w-0 max-w-[220px]" title={allNames}>
            <div className="flex items-center -space-x-2 shrink-0">
                {recipients.map((r, i) => {
                    const name = r.user?.name ?? 'Customer';
                    const avatar = r.user?.profile_photo_url;
                    return avatar ? (
                        <img
                            key={r.id ?? i}
                            src={avatar}
                            alt={name}
                            title={name}
                            className="size-5 rounded-full object-cover border-2 border-card shadow-2xs shrink-0 aspect-square"
                        />
                    ) : (
                        <div
                            key={r.id ?? i}
                            title={name}
                            className="size-5 rounded-full bg-primary/10 text-primary font-bold text-[9px] flex items-center justify-center border-2 border-card shadow-2xs shrink-0 aspect-square"
                        >
                            {getInitials(name)}
                        </div>
                    );
                })}
            </div>
            <div className="flex flex-col min-w-0">
                <span className="text-xs font-semibold text-foreground truncate" title={allNames}>
                    {allNames}
                </span>
                {recipients.length > 1 && (
                    <span className="text-[10px] text-muted-foreground font-normal">
                        {recipients.length} customers
                    </span>
                )}
            </div>
        </div>
    );
}

export function GlobalChangeOrdersIndex({
    changeOrders = [],
    projects = [],
    customers = [],
}: Readonly<GlobalChangeOrdersIndexProps>) {
    const permissions = usePermissions();
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');
    const [selectedProjectId, setSelectedProjectId] = React.useState<number | 'all'>('all');
    const [selectedCustomerId, setSelectedCustomerId] = React.useState<number | 'all'>('all');
    const [currentPage, setCurrentPage] = React.useState(1);
    const [isSelectProjectOpen, setIsSelectProjectOpen] = React.useState(false);
    const [isMissingCustomerOpen, setIsMissingCustomerOpen] = React.useState(false);
    const [isSending, setIsSending] = React.useState(false);

    const selection = useChangeOrderSelection();
    const canManage = permissions.canManageChangeOrders;

    // Manager/admin drag-to-reorder on the global sequence, which is stored
    // separately from each project's own order.
    const {
        orders: orderedOrders,
        isSaving: isSavingOrder,
        handleProps,
        rowProps,
        isDragSource,
        isDragTarget,
    } = useChangeOrderReorder(changeOrders, canManage, route('change-orders.reorder'));

    const selectedOrders = React.useMemo(() => {
        return changeOrders.filter((o) => selection.isSelected(o.id));
    }, [changeOrders, selection]);

    // Unique customer options aggregated from backend prop or change orders
    const customerOptions = React.useMemo(() => {
        if (customers && customers.length > 0) return customers;
        const map = new Map<number, CustomerFilterOption>();
        for (const co of changeOrders) {
            for (const recipient of co.recipients ?? []) {
                const u = recipient.user;
                if (u && !map.has(u.id)) {
                    map.set(u.id, {
                        id: u.id,
                        name: u.name,
                        email: u.email,
                        company:
                            (u as { company?: string }).company ??
                            co.project?.customer?.name ??
                            null,
                        profile_photo_url: u.profile_photo_url,
                    });
                }
            }
        }
        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [customers, changeOrders]);

    const selectedCustomer = React.useMemo(() => {
        if (selectedCustomerId === 'all') return null;
        return customerOptions.find((c) => c.id === selectedCustomerId) ?? null;
    }, [customerOptions, selectedCustomerId]);

    // When a customer is filtered, scope the project dropdown choices to projects with change orders assigned to that customer
    const availableProjects = React.useMemo(() => {
        if (selectedCustomerId === 'all') return projects;
        const customerProjectIds = new Set(
            orderedOrders
                .filter(
                    (o) =>
                        (o.recipients ?? []).some(
                            (r) =>
                                r.user_id === selectedCustomerId ||
                                r.user?.id === selectedCustomerId
                        ) || o.created_by === selectedCustomerId
                )
                .map((o) => o.project_id)
        );
        return projects.filter((p) => customerProjectIds.has(p.id));
    }, [projects, selectedCustomerId, orderedOrders]);

    const handleSendToCustomerClick = React.useCallback(() => {
        if (selection.selectedCount === 0) {
            toast.error('Please select at least one change order to send.');
            return;
        }
        setIsMissingCustomerOpen(true);
    }, [selection.selectedCount]);

    const handleConfirmSend = React.useCallback(
        (ordersToSend: ChangeOrder[]) => {
            if (ordersToSend.length === 0) return;
            setIsSending(true);
            router.post(
                route('change-orders.batch-send'),
                {
                    change_order_ids: ordersToSend.map((o) => o.id),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsMissingCustomerOpen(false);
                        selection.clearSelection();
                    },
                    onError: (errors) => {
                        const errorMsg =
                            typeof errors === 'object' && errors !== null
                                ? Object.values(errors).flat().join(', ')
                                : 'Failed to send change orders.';
                        toast.error(errorMsg);
                    },
                    onFinish: () => {
                        setIsSending(false);
                    },
                }
            );
        },
        [selection]
    );

    const filteredOrders = React.useMemo(() => {
        return orderedOrders.filter((order) => {
            const matchesSearch =
                searchQuery.trim() === '' ||
                order.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (order.project?.name ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
                (order.project?.customer?.name ?? '')
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase()) ||
                (order.project?.customer?.code ?? '')
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase()) ||
                `CO-${order.id.toString().padStart(4, '0')}`
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase()) ||
                (order.creator?.name ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
                (order.recipients ?? []).some((r) =>
                    (r.user?.name ?? '').toLowerCase().includes(searchQuery.toLowerCase())
                );

            const matchesStatus = statusFilter === 'all' || order.status === statusFilter;
            const matchesProject =
                selectedProjectId === 'all' ||
                order.project_id === selectedProjectId ||
                order.project?.id === selectedProjectId;
            const matchesCustomer =
                selectedCustomerId === 'all' ||
                (order.recipients ?? []).some(
                    (r) => r.user_id === selectedCustomerId || r.user?.id === selectedCustomerId
                ) ||
                order.created_by === selectedCustomerId;

            return matchesSearch && matchesStatus && matchesProject && matchesCustomer;
        });
    }, [orderedOrders, searchQuery, statusFilter, selectedProjectId, selectedCustomerId]);

    const stats = React.useMemo(() => {
        const counts: Record<string, number> = {
            draft: 0,
            sent_to_customer: 0,
            customer_action_required: 0,
            approved: 0,
            rejected: 0,
        };
        const values: Record<string, number> = {
            draft: 0,
            sent_to_customer: 0,
            customer_action_required: 0,
            approved: 0,
            rejected: 0,
        };
        let totalValue = 0;

        // Contextual list scoped by customer and project filters so stats adapt dynamically
        const contextOrders = orderedOrders.filter((order) => {
            const matchesProject =
                selectedProjectId === 'all' ||
                order.project_id === selectedProjectId ||
                order.project?.id === selectedProjectId;
            const matchesCustomer =
                selectedCustomerId === 'all' ||
                (order.recipients ?? []).some(
                    (r) => r.user_id === selectedCustomerId || r.user?.id === selectedCustomerId
                ) ||
                order.created_by === selectedCustomerId;
            return matchesProject && matchesCustomer;
        });

        for (const order of contextOrders) {
            const amount =
                typeof order.total_amount === 'number'
                    ? order.total_amount
                    : Number.parseFloat(String(order.total_amount)) || 0;
            totalValue += amount;
            if (order.status in counts) {
                counts[order.status] += 1;
                values[order.status] += amount;
            }
        }
        return { counts, values, totalValue, totalCount: contextOrders.length };
    }, [orderedOrders, selectedProjectId, selectedCustomerId]);

    const statTiles = [
        {
            key: 'all',
            label: 'All Change Orders',
            shortLabel: 'All',
            count: stats.totalCount,
            sublabel: formatCurrency(stats.totalValue),
            activeClass: 'border-primary/60 bg-primary/10 text-primary font-semibold shadow-xs',
            activeBadgeClass: 'bg-primary text-primary-foreground',
        },
        {
            key: 'draft',
            label: 'Draft',
            shortLabel: 'Draft',
            count: stats.counts.draft,
            sublabel: 'Not sent to customer',
            activeClass:
                'border-slate-400 bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100 font-semibold shadow-xs',
            activeBadgeClass: 'bg-slate-700 text-white dark:bg-slate-200 dark:text-slate-900',
        },
        {
            key: 'sent_to_customer',
            label: 'Sent',
            shortLabel: 'Sent',
            count: stats.counts.sent_to_customer,
            sublabel: 'Awaiting customer review',
            activeClass:
                'border-sky-500/60 bg-sky-50 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 font-semibold shadow-xs',
            activeBadgeClass: 'bg-sky-600 text-white',
        },
        {
            key: 'approved',
            label: 'Approved',
            shortLabel: 'Approved',
            count: stats.counts.approved,
            sublabel: formatCurrency(stats.values.approved),
            activeClass:
                'border-emerald-500/60 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-semibold shadow-xs',
            activeBadgeClass: 'bg-emerald-600 text-white',
        },
        {
            key: 'rejected',
            label: 'Rejected',
            shortLabel: 'Rejected',
            count: stats.counts.rejected,
            sublabel: formatCurrency(stats.values.rejected),
            activeClass:
                'border-rose-500/60 bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 font-semibold shadow-xs',
            activeBadgeClass: 'bg-rose-600 text-white',
        },
    ];

    const selectedProject = React.useMemo(() => {
        if (selectedProjectId === 'all') return null;
        return projects.find((p) => p.id === selectedProjectId) ?? null;
    }, [projects, selectedProjectId]);

    const totalPages = Math.max(1, Math.ceil(filteredOrders.length / ITEMS_PER_PAGE));

    React.useEffect(() => {
        if (currentPage > totalPages) {
            setCurrentPage(totalPages);
        }
    }, [currentPage, totalPages]);

    const paginatedOrders = React.useMemo(() => {
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        return filteredOrders.slice(start, start + ITEMS_PER_PAGE);
    }, [filteredOrders, currentPage]);

    // Grouped by project for display. Built from the orders themselves rather
    // than the `projects` prop, so a project only gets a heading when it
    // actually has change orders here. Map preserves insertion order, so the
    // global_sort_order sequence still decides which project comes first.
    const groupedOrders = React.useMemo(() => {
        const groups = new Map<number, { name: string; orders: ChangeOrder[] }>();

        for (const order of paginatedOrders) {
            const key = order.project?.id ?? order.project_id;
            const group = groups.get(key);

            if (group) {
                group.orders.push(order);
            } else {
                groups.set(key, {
                    name: order.project?.name ?? 'Unassigned project',
                    orders: [order],
                });
            }
        }

        return Array.from(groups, ([id, group]) => ({ id, ...group }));
    }, [paginatedOrders]);

    // The reorder and select-all columns only render for managers.
    const columnCount = 8 + (canManage ? 2 : 0);

    return (
        <AppLayout
            title="Change Orders"
            breadcrumb={[{ label: 'Change Orders' }]}
            headerActions={
                <div className="flex items-center gap-2">
                    {permissions.canManageChangeOrders && (
                        <Button
                            type="button"
                            variant={selection.selectedCount > 0 ? 'default' : 'outline'}
                            size="sm"
                            disabled={selection.selectedCount === 0 || isSending}
                            onClick={handleSendToCustomerClick}
                            className={cn(
                                'h-9 gap-1.5 text-xs shrink-0 cursor-pointer transition-all',
                                selection.selectedCount === 0 &&
                                    'opacity-50 cursor-not-allowed bg-muted text-muted-foreground border-border/80'
                            )}
                            title={
                                selection.selectedCount === 0
                                    ? 'Select change orders using the checkboxes below to send to customer'
                                    : `Send ${selection.selectedCount} change order(s) to customer`
                            }
                        >
                            <Send className="size-3.5" />
                            <span>Send to Customer</span>
                            {selection.selectedCount > 0 && (
                                <span className="ml-0.5 rounded-full bg-primary-foreground/25 px-1.5 py-0.2 text-[10px] font-bold">
                                    {selection.selectedCount}
                                </span>
                            )}
                        </Button>
                    )}

                    {permissions.canCreateChangeOrder && (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setIsSelectProjectOpen(true)}
                            className="h-9 gap-1.5 text-xs shrink-0 cursor-pointer"
                        >
                            <Plus className="size-4" /> Create Change Order
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Change Orders - All Projects" />

            <div className="space-y-4">
                {/* Hero Financial Summary: Billed (Left) vs Non-Billed / Absorbed (Right) */}
                <ChangeOrdersFinancialOverview
                    orders={filteredOrders}
                    selectedCustomerName={selectedCustomer?.name}
                    selectedCustomerCompany={selectedCustomer?.company}
                    selectedProjectName={selectedProject?.name}
                    isFiltered={
                        selectedCustomerId !== 'all' ||
                        selectedProjectId !== 'all' ||
                        statusFilter !== 'all' ||
                        Boolean(searchQuery)
                    }
                    onResetFilters={() => {
                        setSelectedCustomerId('all');
                        setSelectedProjectId('all');
                        setStatusFilter('all');
                        setSearchQuery('');
                        setCurrentPage(1);
                    }}
                />

                {/* Dedicated Filter & Search Toolbar with Integrated Status Counts on the Right */}
                <div className="rounded-xl border bg-card p-2.5 shadow-2xs">
                    <div className="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                        {/* Left: Search & Scope Filters */}
                        <div className="flex items-center gap-1.5 flex-wrap flex-1 min-w-0">
                            <SearchInput
                                value={searchQuery}
                                onSearchChange={setSearchQuery}
                                placeholder="Search by title, project, customer..."
                                className="w-full sm:w-60 shrink-0"
                            />

                            {/* Customer Filter Dropdown — hidden for customers: they are the scope. */}
                            {permissions.canFilterChangeOrdersByCustomer && (
                                <CustomerFilterDropdown
                                    customers={customerOptions}
                                    selectedCustomerId={selectedCustomerId}
                                    onSelectCustomer={(id) => {
                                        setSelectedCustomerId(id);
                                        setCurrentPage(1);
                                    }}
                                    changeOrders={orderedOrders}
                                />
                            )}

                            {/* Project Filter Dropdown */}
                            <ProjectFilterDropdown
                                projects={availableProjects}
                                selectedProjectId={selectedProjectId}
                                onSelectProject={(id) => {
                                    setSelectedProjectId(id);
                                    setCurrentPage(1);
                                }}
                            />

                            {(Boolean(searchQuery) ||
                                selectedCustomerId !== 'all' ||
                                selectedProjectId !== 'all' ||
                                statusFilter !== 'all') && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearchQuery('');
                                        setSelectedCustomerId('all');
                                        setSelectedProjectId('all');
                                        setStatusFilter('all');
                                        setCurrentPage(1);
                                    }}
                                    className="h-9 gap-1.5 text-xs text-muted-foreground shrink-0 cursor-pointer hover:text-foreground"
                                >
                                    <X className="size-3" /> Clear
                                </Button>
                            )}

                            {isSavingOrder && (
                                <span className="flex items-center gap-1.5 text-xs font-semibold text-primary">
                                    <Loader2 className="size-3.5 animate-spin" />
                                    Saving order…
                                </span>
                            )}
                        </div>

                        {/* Right: Status Filter Pills with Counts */}
                        <div className="flex items-center gap-1 overflow-x-auto pt-2 xl:pt-0 border-t xl:border-t-0 border-border/40 shrink-0">
                            {statTiles.map((tile) => {
                                const isActive = statusFilter === tile.key;
                                return (
                                    <button
                                        key={tile.key}
                                        type="button"
                                        aria-pressed={isActive}
                                        onClick={() => {
                                            setStatusFilter(
                                                isActive && tile.key !== 'all' ? 'all' : tile.key
                                            );
                                            setCurrentPage(1);
                                        }}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all shrink-0 cursor-pointer border',
                                            isActive
                                                ? tile.activeClass
                                                : 'border-border/50 bg-muted/20 text-muted-foreground hover:bg-muted hover:text-foreground'
                                        )}
                                        title={`Filter by ${tile.label} (${tile.count})`}
                                    >
                                        <span>{tile.shortLabel}</span>
                                        <span
                                            className={cn(
                                                'px-1.5 py-0.2 rounded-full text-[10px] font-bold tabular-nums',
                                                isActive
                                                    ? tile.activeBadgeClass
                                                    : 'bg-muted/80 text-muted-foreground'
                                            )}
                                        >
                                            {tile.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Active Filter Chips / Notices */}
                {(selectedCustomer || selectedProject) && (
                    <div className="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2 rounded-lg border border-primary/20 bg-primary/5 text-xs">
                        <div className="flex items-center gap-2 min-w-0 flex-wrap">
                            <span className="text-muted-foreground font-medium text-[11px]">
                                Active Filters:
                            </span>
                            {selectedCustomer && (
                                <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-primary/10 font-semibold text-primary text-xs">
                                    {selectedCustomer.profile_photo_url ? (
                                        <div
                                            className="w-4 h-4 rounded-full overflow-hidden shrink-0 border border-border flex items-center justify-center"
                                            style={{
                                                width: '16px',
                                                height: '16px',
                                                minWidth: '16px',
                                                minHeight: '16px',
                                                maxWidth: '16px',
                                                maxHeight: '16px',
                                            }}
                                        >
                                            <img
                                                src={selectedCustomer.profile_photo_url}
                                                alt={selectedCustomer.name}
                                                className="w-full h-full object-cover block"
                                                style={{ width: '100%', height: '100%' }}
                                            />
                                        </div>
                                    ) : (
                                        <Users2 className="size-3" />
                                    )}
                                    <span>Customer: {selectedCustomer.name}</span>
                                    {selectedCustomer.company && (
                                        <span className="text-[10px] px-1.5 py-0.2 rounded bg-primary/20 text-primary font-medium">
                                            {selectedCustomer.company}
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSelectedCustomerId('all');
                                            setCurrentPage(1);
                                        }}
                                        className="hover:text-foreground ml-0.5 cursor-pointer"
                                        aria-label="Remove customer filter"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            )}
                            {selectedProject && (
                                <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-muted font-semibold text-foreground text-xs">
                                    <Building2 className="size-3 text-primary" />
                                    <span>Project: {selectedProject.name}</span>
                                    {(selectedProject.project_number ||
                                        selectedProject.ps_number) && (
                                        <span className="font-mono text-[9px] px-1 py-0.2 rounded bg-card text-muted-foreground">
                                            {selectedProject.project_number ??
                                                selectedProject.ps_number}
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSelectedProjectId('all');
                                            setCurrentPage(1);
                                        }}
                                        className="hover:text-foreground ml-0.5 cursor-pointer"
                                        aria-label="Remove project filter"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                setSelectedCustomerId('all');
                                setSelectedProjectId('all');
                                setStatusFilter('all');
                                setSearchQuery('');
                                setCurrentPage(1);
                            }}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline shrink-0 cursor-pointer"
                        >
                            <X className="size-3" />
                            <span>Reset All Filters</span>
                        </button>
                    </div>
                )}
                {/* Main Table Card */}
                <div className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
                    {paginatedOrders.length === 0 ? (
                        <div className="py-16 text-center px-4">
                            <div className="mx-auto size-12 rounded-xl bg-muted/60 flex items-center justify-center text-muted-foreground mb-3">
                                <FileSpreadsheet className="size-6" />
                            </div>
                            <h3 className="text-sm font-bold text-foreground">
                                No change orders found
                            </h3>
                            <p className="text-xs text-muted-foreground max-w-sm mx-auto mt-1 mb-5">
                                {searchQuery || statusFilter !== 'all'
                                    ? 'No change orders match your search or filter criteria.'
                                    : 'There are currently no change orders created in your accessible projects.'}
                            </p>
                            {permissions.canCreateChangeOrder && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => setIsSelectProjectOpen(true)}
                                    className="gap-1.5 text-xs cursor-pointer"
                                >
                                    <Plus className="size-3.5" />
                                    <span>Create Change Order</span>
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr className="border-b border-border/70 bg-muted/40 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                        {canManage && (
                                            <th scope="col" className="w-8 px-2 py-3">
                                                <span className="sr-only">Reorder</span>
                                            </th>
                                        )}
                                        {permissions.canManageChangeOrders && (
                                            <th className="w-10 px-3 py-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={selection.isAllSelected(
                                                        paginatedOrders.map((o) => o.id)
                                                    )}
                                                    ref={(el) => {
                                                        if (el) {
                                                            el.indeterminate =
                                                                selection.isSomeSelected(
                                                                    paginatedOrders.map((o) => o.id)
                                                                );
                                                        }
                                                    }}
                                                    onChange={() =>
                                                        selection.toggleSelectAll(
                                                            paginatedOrders.map((o) => o.id)
                                                        )
                                                    }
                                                    aria-label="Select all change orders on this page"
                                                    className="size-4 rounded border-input text-primary focus:ring-primary/20 cursor-pointer"
                                                />
                                            </th>
                                        )}
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Title
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Items
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Total Amount
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Created By
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Sent At
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground">
                                            Customer & Contacts
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {groupedOrders.map((group) => (
                                        <React.Fragment key={group.id}>
                                            <tr className="border-y border-sky-200 bg-sky-50/80 dark:border-sky-900/60 dark:bg-sky-950/40">
                                                <td colSpan={columnCount} className="px-4 py-2.5">
                                                    <div className="flex items-center gap-2">
                                                        <Building2 className="size-4 shrink-0 text-sky-700 dark:text-sky-300" />
                                                        <span className="font-heading text-sm font-bold text-sky-900 dark:text-sky-100">
                                                            {group.name}
                                                        </span>
                                                        <span className="rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-sky-800 dark:bg-sky-900/70 dark:text-sky-200">
                                                            {group.orders.length}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                            {group.orders.map((order) => {
                                                const code = `CO-${order.id.toString().padStart(4, '0')}`;
                                                const totalItems = order.items?.length ?? 0;
                                                const includedItems =
                                                    order.items?.filter((i) => i.is_included)
                                                        .length ?? totalItems;
                                                const itemWord =
                                                    totalItems === 1 ? 'item' : 'items';
                                                const itemsLabel =
                                                    includedItems === totalItems
                                                        ? `${totalItems} ${itemWord}`
                                                        : `${includedItems} of ${totalItems} included`;
                                                const creatorName =
                                                    order.creator?.name ?? 'Manager';
                                                const creatorAvatar =
                                                    order.creator?.profile_photo_url;
                                                // A draft nobody has been sent to has no
                                                // customer contact yet.
                                                const hasRecipients =
                                                    (order.recipients?.length ?? 0) > 0;
                                                const showUrl = getChangeOrderShowUrl(
                                                    order.project?.hash_id ||
                                                        order.project?.id ||
                                                        order.project_id,
                                                    order.id,
                                                    permissions.isCustomer
                                                );

                                                return (
                                                    <tr
                                                        key={order.id}
                                                        {...rowProps(order)}
                                                        onClick={() => {
                                                            if (showUrl && showUrl !== '#') {
                                                                router.visit(showUrl);
                                                            }
                                                        }}
                                                        className={cn(
                                                            'group cursor-pointer transition-colors hover:bg-muted/40',
                                                            selection.isSelected(order.id) &&
                                                                'bg-primary/5',
                                                            isDragSource(order) && 'opacity-40',
                                                            isDragTarget(order) &&
                                                                'bg-primary/10 ring-2 ring-primary/40'
                                                        )}
                                                    >
                                                        {canManage && (
                                                            <td
                                                                className="w-8 px-2 py-3.5 text-center"
                                                                onClick={(e) => e.stopPropagation()}
                                                            >
                                                                <button
                                                                    type="button"
                                                                    {...handleProps(order)}
                                                                    aria-label={`Reorder ${order.title}`}
                                                                    title="Drag to reorder"
                                                                    className="inline-flex size-6 cursor-grab items-center justify-center rounded text-muted-foreground/70 transition-colors hover:bg-muted hover:text-foreground active:cursor-grabbing disabled:cursor-default disabled:opacity-40"
                                                                >
                                                                    <GripVertical className="size-3.5" />
                                                                </button>
                                                            </td>
                                                        )}
                                                        {permissions.canManageChangeOrders && (
                                                            <td
                                                                className="w-10 px-3 py-3.5 text-center"
                                                                onClick={(e) => e.stopPropagation()}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={selection.isSelected(
                                                                        order.id
                                                                    )}
                                                                    onChange={() =>
                                                                        selection.toggleSelect(
                                                                            order.id
                                                                        )
                                                                    }
                                                                    aria-label={`Select ${order.title}`}
                                                                    className="size-4 rounded border-input text-primary focus:ring-primary/20 cursor-pointer"
                                                                />
                                                            </td>
                                                        )}
                                                        {/* Title — the project is already the
                                                            group heading above this block of
                                                            rows, so repeating it (and its
                                                            number) on every line just crowds
                                                            the table. */}
                                                        <td className="px-4 py-3.5 min-w-0 max-w-[220px]">
                                                            <div className="flex flex-col min-w-0 max-w-[220px]">
                                                                <span
                                                                    className="truncate font-bold text-foreground group-hover:text-primary transition-colors text-xs"
                                                                    title={order.title}
                                                                >
                                                                    {order.title}
                                                                </span>
                                                                <span
                                                                    className="truncate font-mono text-[10px] text-muted-foreground mt-0.5"
                                                                    title={code}
                                                                >
                                                                    {code}
                                                                </span>
                                                            </div>
                                                        </td>

                                                        {/* Items */}
                                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                                            <span className="text-xs font-medium text-foreground tabular-nums">
                                                                {itemsLabel}
                                                            </span>
                                                        </td>

                                                        {/* Total Amount (Billed & Absorbed) */}
                                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                                            {(() => {
                                                                const {
                                                                    billedAmount,
                                                                    absorbedAmount,
                                                                } =
                                                                    calculateSingleOrderFinancials(
                                                                        order
                                                                    );
                                                                return (
                                                                    <div className="flex flex-col gap-0.5">
                                                                        <span className="font-semibold text-foreground text-xs tabular-nums">
                                                                            {formatCurrency(
                                                                                billedAmount
                                                                            )}
                                                                        </span>
                                                                        {absorbedAmount > 0 && (
                                                                            <div className="flex items-center gap-1 text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                                                                                <span className="tabular-nums">
                                                                                    +
                                                                                    {formatCurrency(
                                                                                        absorbedAmount
                                                                                    )}{' '}
                                                                                    absorbed
                                                                                </span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })()}
                                                        </td>

                                                        {/* Status Badge */}
                                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                                            <ChangeOrderStatusBadge
                                                                status={order.status}
                                                            />
                                                        </td>

                                                        {/* Created By */}
                                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                                            <div className="flex items-center gap-2">
                                                                <div className="size-5 rounded-full overflow-hidden shrink-0 border border-border flex items-center justify-center shadow-2xs">
                                                                    {creatorAvatar ? (
                                                                        <img
                                                                            src={creatorAvatar}
                                                                            alt={creatorName}
                                                                            className="size-5 object-cover block aspect-square"
                                                                        />
                                                                    ) : (
                                                                        <div className="size-5 bg-primary/10 text-primary font-bold text-[9px] flex items-center justify-center">
                                                                            {getInitials(
                                                                                creatorName
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <div className="flex flex-col">
                                                                    <span className="font-semibold text-foreground text-xs">
                                                                        {creatorName}
                                                                    </span>
                                                                    <span className="text-[10px] text-muted-foreground">
                                                                        {formatDate(
                                                                            order.created_at
                                                                        )}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        {/* Sent At */}
                                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                                            {order.sent_at ? (
                                                                <div className="flex flex-col">
                                                                    <span className="font-medium text-foreground text-xs">
                                                                        {formatDate(order.sent_at)}
                                                                    </span>
                                                                    <span className="text-[10px] text-muted-foreground">
                                                                        {formatTime(order.sent_at)}
                                                                    </span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground text-xs">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>

                                                        {/* Customer & Contacts — the project
                                                            owner is not the contact a change
                                                            order went to. With no recipient
                                                            there is nobody to name, so the
                                                            column stays empty rather than
                                                            implying the company was billed. */}
                                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                                            {hasRecipients ? (
                                                                <div className="flex flex-col gap-1 min-w-0 max-w-[240px]">
                                                                    {order.project?.customer && (
                                                                        <div className="flex items-center gap-1.5">
                                                                            <span
                                                                                className="font-semibold text-xs text-foreground truncate"
                                                                                title={
                                                                                    order.project
                                                                                        .customer
                                                                                        .name
                                                                                }
                                                                            >
                                                                                {
                                                                                    order.project
                                                                                        .customer
                                                                                        .name
                                                                                }
                                                                            </span>
                                                                            {order.project.customer
                                                                                .code && (
                                                                                <span className="font-mono text-[9px] font-bold px-1.5 py-0.2 rounded bg-primary/10 text-primary shrink-0">
                                                                                    {
                                                                                        order
                                                                                            .project
                                                                                            .customer
                                                                                            .code
                                                                                    }
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                    <div>
                                                                        {renderCustomerRecipient(
                                                                            order.recipients
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground text-xs italic">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>

                                                        {/* Actions */}
                                                        <td
                                                            className="px-4 py-3.5 text-right whitespace-nowrap"
                                                            onClick={(e) => e.stopPropagation()}
                                                        >
                                                            <Link
                                                                href={showUrl}
                                                                aria-label="View Change Order"
                                                                className="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shadow-2xs"
                                                            >
                                                                <Eye className="size-3.5" />
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </React.Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination Footer */}
                    {paginatedOrders.length > 0 && (
                        <div className="flex items-center justify-between gap-4 flex-wrap border-t border-border/70 bg-muted/20 px-4 py-3 text-xs">
                            <div className="text-muted-foreground text-[11px] tabular-nums">
                                Showing{' '}
                                <span className="font-semibold text-foreground">
                                    {(currentPage - 1) * ITEMS_PER_PAGE + 1}
                                </span>{' '}
                                to{' '}
                                <span className="font-semibold text-foreground">
                                    {Math.min(currentPage * ITEMS_PER_PAGE, filteredOrders.length)}
                                </span>{' '}
                                of{' '}
                                <span className="font-semibold text-foreground">
                                    {filteredOrders.length}
                                </span>{' '}
                                results
                            </div>

                            {totalPages > 1 && (
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        aria-label="First page"
                                        disabled={currentPage === 1}
                                        onClick={() => setCurrentPage(1)}
                                        className="flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                                    >
                                        <ChevronsLeft className="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Previous page"
                                        disabled={currentPage === 1}
                                        onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                        className="flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                                    >
                                        <ChevronLeft className="size-3.5" />
                                    </button>

                                    {Array.from({ length: totalPages }, (_, i) => i + 1)
                                        .filter(
                                            (page) =>
                                                page === 1 ||
                                                page === totalPages ||
                                                Math.abs(page - currentPage) <= 1
                                        )
                                        .map((page) => (
                                            <button
                                                key={page}
                                                type="button"
                                                onClick={() => setCurrentPage(page)}
                                                className={cn(
                                                    'min-w-7 h-7 px-2 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center cursor-pointer',
                                                    currentPage === page
                                                        ? 'bg-foreground text-background font-bold shadow-xs'
                                                        : 'border border-border bg-card text-muted-foreground hover:text-foreground'
                                                )}
                                            >
                                                {page}
                                            </button>
                                        ))}

                                    <button
                                        type="button"
                                        aria-label="Next page"
                                        disabled={currentPage === totalPages}
                                        onClick={() =>
                                            setCurrentPage((p) => Math.min(totalPages, p + 1))
                                        }
                                        className="flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                                    >
                                        <ChevronRight className="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Last page"
                                        disabled={currentPage === totalPages}
                                        onClick={() => setCurrentPage(totalPages)}
                                        className="flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer"
                                    >
                                        <ChevronsRight className="size-3.5" />
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Project Selection Modal for Create Change Order */}
            <SelectProjectDialog
                isOpen={isSelectProjectOpen}
                onClose={() => setIsSelectProjectOpen(false)}
                projects={projects}
                initialProjectId={selectedProjectId}
            />

            {/* Validation and Confirmation Modal for Send to Customer */}
            <MissingCustomerDialog
                isOpen={isMissingCustomerOpen}
                onClose={() => setIsMissingCustomerOpen(false)}
                selectedOrders={selectedOrders}
                onConfirmSend={handleConfirmSend}
                isSending={isSending}
            />
        </AppLayout>
    );
}

export default GlobalChangeOrdersIndex;
