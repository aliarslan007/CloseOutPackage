import { ChangeOrderStatusBadge } from '@/components/change-orders/change-order-status-badge';
import { ChangeOrder } from '@/components/change-orders/change-order-types';
import { MissingCustomerDialog } from '@/components/change-orders/missing-customer-dialog';
import { useChangeOrderSelection } from '@/components/change-orders/use-change-order-selection';
import { useChangeOrderReorder } from '@/components/change-orders/use-change-order-reorder';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { SearchInput } from '@/components/shared/search-input';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    GripVertical,
    Loader2,
    MoreVertical,
    Plus,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ChangeOrdersIndexProps extends PageProps {
    project: {
        id: number;
        hash_id?: string;
        name: string;
    };
    changeOrders: ChangeOrder[];
}

const ITEMS_PER_PAGE = 10;
const SHOW_ROUTE = 'projects.change-orders.show';

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

function renderCustomerRecipient(recipients?: ChangeOrder['recipients']) {
    if (!recipients || recipients.length === 0) {
        return <span className="text-muted-foreground text-xs italic">—</span>;
    }

    const allNames = recipients.map((r) => r.user?.name).filter(Boolean).join(', ');

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

export function ChangeOrdersIndex({
    project,
    changeOrders = [],
}: Readonly<ChangeOrdersIndexProps>) {
    const projectIdentifier = project.hash_id || project.id;
    const permissions = usePermissions();
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');
    const [currentPage, setCurrentPage] = React.useState(1);
    const [coToDelete, setCoToDelete] = React.useState<ChangeOrder | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const canManage = permissions.canManageChangeOrders;

    // Manager/admin drag-to-reorder; the hook owns the (persisted) ordering.
    const {
        orders: orderedOrders,
        isSaving: isSavingOrder,
        handleProps,
        rowProps,
        isDragSource,
        isDragTarget,
    } = useChangeOrderReorder(
        changeOrders,
        canManage,
        route('projects.change-orders.reorder', { project: projectIdentifier })
    );

    // Filter change orders
    const filteredOrders = React.useMemo(() => {
        return orderedOrders.filter((order) => {
            const matchesSearch =
                searchQuery.trim() === '' ||
                order.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                `CO-${order.id.toString().padStart(4, '0')}`
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase()) ||
                (order.creator?.name ?? '').toLowerCase().includes(searchQuery.toLowerCase());

            const matchesStatus = statusFilter === 'all' || order.status === statusFilter;

            return matchesSearch && matchesStatus;
        });
    }, [orderedOrders, searchQuery, statusFilter]);

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

    const [isSending, setIsSending] = React.useState(false);
    const [isMissingCustomerOpen, setIsMissingCustomerOpen] = React.useState(false);
    const [ordersForModal, setOrdersForModal] = React.useState<ChangeOrder[]>([]);

    const selection = useChangeOrderSelection();

    const handleDelete = React.useCallback(() => {
        if (!coToDelete) return;
        setIsDeleting(true);
        router.delete(route('projects.change-orders.destroy', [projectIdentifier, coToDelete.id]), {
            onFinish: () => {
                setIsDeleting(false);
                setCoToDelete(null);
            },
        });
    }, [coToDelete, projectIdentifier]);

    const handleBatchSendClick = React.useCallback(() => {
        const selected = changeOrders.filter((o) => selection.isSelected(o.id));
        if (selected.length === 0) {
            toast.error('Please select at least one change order to send.');
            return;
        }
        setOrdersForModal(selected);
        setIsMissingCustomerOpen(true);
    }, [changeOrders, selection]);

    const handleSingleSendClick = React.useCallback((order: ChangeOrder) => {
        setOrdersForModal([order]);
        setIsMissingCustomerOpen(true);
    }, []);

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

    return (
        <AppLayout
            title="Change Orders"
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', projectIdentifier) },
                { label: 'Change Orders' },
            ]}
            headerActions={
                <div className="flex items-center gap-2">
                    {permissions.canManageChangeOrders && (
                        <Button
                            type="button"
                            variant={selection.selectedCount > 0 ? 'default' : 'outline'}
                            size="sm"
                            disabled={selection.selectedCount === 0 || isSending}
                            onClick={handleBatchSendClick}
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
                        <Button asChild size="sm" className="h-9 gap-1.5 text-xs shrink-0">
                            <Link href={route('projects.change-orders.create', projectIdentifier)}>
                                <Plus className="size-4" /> Create Change Order
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Change Orders - ${project.name}`} />

            <div className="space-y-3">
                {/* Dedicated Filter & Search Toolbar */}
                <div className="rounded-xl border bg-card p-3 shadow-2xs">
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <SearchInput
                            value={searchQuery}
                            onSearchChange={setSearchQuery}
                            placeholder="Search by title, code, or creator..."
                            className="w-full sm:w-64 shrink-0"
                        />

                        <div className="relative shrink-0">
                            <Filter className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <select
                                value={statusFilter}
                                onChange={(e) => {
                                    setStatusFilter(e.target.value);
                                    setCurrentPage(1);
                                }}
                                className="h-9 w-full sm:w-auto cursor-pointer appearance-none rounded-md border border-input bg-card pl-8 pr-8 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                                aria-label="Filter by status"
                            >
                                <option value="all">All Status</option>
                                <option value="draft">Draft</option>
                                <option value="sent_to_customer">Sent</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        </div>

                        {(Boolean(searchQuery) || statusFilter !== 'all') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearchQuery('');
                                    setStatusFilter('all');
                                    setCurrentPage(1);
                                }}
                                className="h-9 gap-1.5 text-xs text-muted-foreground shrink-0 cursor-pointer"
                            >
                                <X className="size-3" /> Clear
                            </Button>
                        )}

                        {isSavingOrder && (
                            <span className="flex items-center gap-1.5 text-xs font-semibold text-primary sm:ml-auto">
                                <Loader2 className="size-3.5 animate-spin" />
                                Saving order…
                            </span>
                        )}
                    </div>
                </div>

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
                                    : 'There are currently no change orders created for this project.'}
                            </p>
                            {permissions.canCreateChangeOrder && (
                                <Button asChild size="sm" className="gap-1.5 text-xs">
                                    <Link
                                        href={route(
                                            'projects.change-orders.create',
                                            projectIdentifier
                                        )}
                                    >
                                        <Plus className="size-3.5" />
                                        <span>Create Change Order</span>
                                    </Link>
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
                                            Verizon Customers
                                        </th>
                                        <th className="px-4 py-3 font-bold text-foreground text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {paginatedOrders.map((order) => {
                                        const code = `CO-${order.id.toString().padStart(4, '0')}`;
                                        const totalItems = order.items?.length ?? 0;
                                        const includedItems =
                                            order.items?.filter((i) => i.is_included).length ??
                                            totalItems;
                                        const itemWord = totalItems === 1 ? 'item' : 'items';
                                        const itemsLabel =
                                            includedItems === totalItems
                                                ? `${totalItems} ${itemWord}`
                                                : `${includedItems} of ${totalItems} included`;
                                        const creatorName = order.creator?.name ?? 'Manager';
                                        const creatorAvatar = order.creator?.profile_photo_url;
                                        const isDraft = order.status === 'draft';
                                        const showUrl = permissions.isCustomer
                                            ? route('customer.change-orders.show', [
                                                  projectIdentifier,
                                                  order.id,
                                              ])
                                            : route(SHOW_ROUTE, [
                                                  projectIdentifier,
                                                  order.id,
                                              ]);

                                        return (
                                            <tr
                                                key={order.id}
                                                {...rowProps(order)}
                                                onClick={() => router.visit(showUrl)}
                                                className={cn(
                                                    'group cursor-pointer transition-colors hover:bg-muted/40',
                                                    selection.isSelected(order.id) && 'bg-primary/5',
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
                                                            checked={selection.isSelected(order.id)}
                                                            onChange={() =>
                                                                selection.toggleSelect(order.id)
                                                            }
                                                            aria-label={`Select ${order.title}`}
                                                            className="size-4 rounded border-input text-primary focus:ring-primary/20 cursor-pointer"
                                                        />
                                                    </td>
                                                )}
                                                {/* Title & Code */}
                                                <td className="px-4 py-3.5 min-w-0 max-w-[220px]">
                                                    <div className="flex flex-col min-w-0 max-w-[220px]">
                                                        <span className="truncate font-bold text-foreground group-hover:text-primary transition-colors text-xs" title={order.title}>
                                                            {order.title}
                                                        </span>
                                                        <span className="truncate font-mono text-[10px] text-muted-foreground mt-0.5" title={code}>
                                                            {code}
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Items count */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <span className="text-xs font-medium text-foreground">
                                                        {itemsLabel}
                                                    </span>
                                                </td>

                                                {/* Total Amount */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <span className="font-bold text-foreground text-xs">
                                                        {formatCurrency(order.total_amount)}
                                                    </span>
                                                </td>

                                                {/* Status Badge */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <ChangeOrderStatusBadge status={order.status} />
                                                </td>

                                                {/* Created By */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <div className="flex items-center gap-2">
                                                        {creatorAvatar ? (
                                                            <img
                                                                src={creatorAvatar}
                                                                alt={creatorName}
                                                                className="size-5 rounded-full object-cover border border-border shadow-2xs shrink-0 aspect-square"
                                                            />
                                                        ) : (
                                                            <div className="size-5 rounded-full bg-primary/10 text-primary font-bold text-[9px] flex items-center justify-center border border-border shrink-0 shadow-2xs">
                                                                {getInitials(creatorName)}
                                                            </div>
                                                        )}
                                                        <div className="flex flex-col">
                                                            <span className="font-semibold text-foreground text-xs">
                                                                {creatorName}
                                                            </span>
                                                            <span className="text-[10px] text-muted-foreground">
                                                                {formatDate(order.created_at)}
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

                                                {/* Verizon Customers */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    {renderCustomerRecipient(order.recipients)}
                                                </td>

                                                {/* Actions */}
                                                <td
                                                    className="px-4 py-3.5 text-right whitespace-nowrap"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <div className="flex items-center justify-end gap-1">
                                                        {/* Eye button */}
                                                        <Link
                                                            href={showUrl}
                                                            aria-label="View Change Order"
                                                            className="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shadow-2xs"
                                                        >
                                                            <Eye className="size-3.5" />
                                                        </Link>

                                                        {/* Dropdown Menu */}
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <button
                                                                    type="button"
                                                                    aria-label="Change order options"
                                                                    className="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shadow-2xs cursor-pointer"
                                                                >
                                                                    <MoreVertical className="size-3.5" />
                                                                </button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent
                                                                align="end"
                                                                className="w-44 text-xs"
                                                            >
                                                                <DropdownMenuItem asChild>
                                                                    <Link
                                                                        href={showUrl}
                                                                        className="cursor-pointer gap-2"
                                                                    >
                                                                        <Eye className="size-3.5 mr-2" />
                                                                        <span>View Details</span>
                                                                    </Link>
                                                                </DropdownMenuItem>

                                                                {isDraft && permissions.canManageChangeOrders && (
                                                                    <>
                                                                        <DropdownMenuItem asChild>
                                                                            <Link
                                                                                href={route(
                                                                                    'projects.change-orders.edit',
                                                                                    [
                                                                                        projectIdentifier,
                                                                                        order.id,
                                                                                    ]
                                                                                )}
                                                                                className="cursor-pointer gap-2"
                                                                            >
                                                                                <FileText className="size-3.5 mr-2" />
                                                                                <span>
                                                                                    Edit Draft
                                                                                </span>
                                                                            </Link>
                                                                        </DropdownMenuItem>

                                                                        <DropdownMenuItem
                                                                            onClick={() =>
                                                                                handleSingleSendClick(
                                                                                    order
                                                                                )
                                                                            }
                                                                            className="cursor-pointer gap-2 text-primary"
                                                                        >
                                                                            <Send className="size-3.5 mr-2" />
                                                                            <span>
                                                                                Send to Customer
                                                                            </span>
                                                                        </DropdownMenuItem>

                                                                        <DropdownMenuItem
                                                                            onClick={() =>
                                                                                setCoToDelete(order)
                                                                            }
                                                                            className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                                                        >
                                                                            <Trash2 className="size-3.5 mr-2" />
                                                                            <span>
                                                                                Delete Draft
                                                                            </span>
                                                                        </DropdownMenuItem>
                                                                    </>
                                                                )}
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination Footer */}
                    {paginatedOrders.length > 0 && (
                        <div className="flex items-center justify-between gap-4 flex-wrap border-t border-border/70 bg-muted/20 px-4 py-3 text-xs">
                            <div className="text-muted-foreground text-[11px]">
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

            {/* Missing Customer Contacts / Send Confirmation Dialog */}
            <MissingCustomerDialog
                isOpen={isMissingCustomerOpen}
                onClose={() => setIsMissingCustomerOpen(false)}
                selectedOrders={ordersForModal}
                onConfirmSend={handleConfirmSend}
                isSending={isSending}
            />

            {/* Confirm Delete Dialog */}
            <ConfirmDialog
                open={Boolean(coToDelete)}
                onOpenChange={(open: boolean) => {
                    if (!open) setCoToDelete(null);
                }}
                title="Delete Change Order"
                description={`Are you sure you want to delete "${coToDelete?.title}"? This draft will be permanently removed.`}
                confirmLabel={isDeleting ? 'Deleting...' : 'Delete Draft'}
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default ChangeOrdersIndex;
