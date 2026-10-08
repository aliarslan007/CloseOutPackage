import { ChangeOrderStatusBadge } from '@/components/change-orders/change-order-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, CheckCircle2, Clock, FileEdit, FileSignature, FolderKanban } from 'lucide-react';
import * as React from 'react';

export interface RecentChangeOrderItem {
    id: number;
    hash_id: string;
    title: string;
    project_name: string;
    status: string;
    status_label: string;
    total_amount: string;
    created_at: string | null;
    href: string;
}

export interface ChangeOrdersOverviewProps {
    totalChangeOrders: number;
    changeOrdersDraft: number;
    changeOrdersPendingCustomer: number;
    changeOrdersApproved: number;
    changeOrdersRejected: number;
    changeOrdersTotalValue: number;
    changeOrdersApprovedValue: number;
    recentChangeOrders: RecentChangeOrderItem[];
}

const CHANGE_ORDERS_ROUTE = 'change-orders.index';

const getChangeOrdersUrl = (status?: string): string =>
    status ? route(CHANGE_ORDERS_ROUTE, { status }) : route(CHANGE_ORDERS_ROUTE);

function formatCurrency(amount: number): string {
    return amount.toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatRelativeTime(isoString: string | null): string {
    if (!isoString) return '';
    try {
        const date = new Date(isoString);
        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return '';
    }
}

export function ChangeOrdersOverview({
    totalChangeOrders,
    changeOrdersDraft,
    changeOrdersPendingCustomer,
    changeOrdersApproved,
    changeOrdersRejected,
    changeOrdersTotalValue,
    changeOrdersApprovedValue,
    recentChangeOrders,
}: Readonly<ChangeOrdersOverviewProps>) {
    return (
        <Card className="flex flex-col h-full">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-2">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-sm font-bold">
                            <FileSignature className="size-4 text-primary" />
                            Change Orders
                        </CardTitle>
                        <CardDescription>
                            Scope adjustments, customer reviews, and approvals
                        </CardDescription>
                    </div>
                    <Button variant="ghost" size="sm" asChild className="h-8 gap-1 text-xs font-semibold">
                        <Link href={getChangeOrdersUrl()}>
                            <span>View All</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 flex-1 flex flex-col justify-between">
                {/* Metric Summary Strip */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <Link
                        href={getChangeOrdersUrl()}
                        className="rounded-xl border border-border/70 bg-card p-2.5 transition-colors hover:bg-muted/50 text-left"
                    >
                        <span className="text-[11px] font-medium text-muted-foreground block">Total</span>
                        <div className="flex items-baseline gap-1.5 mt-0.5">
                            <span className="text-lg font-bold tracking-tight text-foreground">{totalChangeOrders}</span>
                        </div>
                        <span className="text-[10px] text-muted-foreground truncate block">
                            {formatCurrency(changeOrdersTotalValue)}
                        </span>
                    </Link>

                    <Link
                        href={getChangeOrdersUrl('sent_to_customer')}
                        className="rounded-xl border border-amber-200/70 dark:border-amber-900/50 bg-amber-50/40 dark:bg-amber-950/20 p-2.5 transition-colors hover:bg-amber-100/50 dark:hover:bg-amber-900/30 text-left"
                    >
                        <span className="text-[11px] font-medium text-amber-900 dark:text-amber-300 flex items-center gap-1">
                            <Clock className="size-3 text-amber-600 dark:text-amber-400" />
                            Pending
                        </span>
                        <span className="text-lg font-bold tracking-tight text-amber-950 dark:text-amber-200 mt-0.5 block">
                            {changeOrdersPendingCustomer}
                        </span>
                        <span className="text-[10px] text-amber-800/80 dark:text-amber-400/80 block">
                            Customer decision
                        </span>
                    </Link>

                    <Link
                        href={getChangeOrdersUrl('approved')}
                        className="rounded-xl border border-emerald-200/70 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 p-2.5 transition-colors hover:bg-emerald-100/50 dark:hover:bg-emerald-900/30 text-left"
                    >
                        <span className="text-[11px] font-medium text-emerald-900 dark:text-emerald-300 flex items-center gap-1">
                            <CheckCircle2 className="size-3 text-emerald-600 dark:text-emerald-400" />
                            Approved
                        </span>
                        <span className="text-lg font-bold tracking-tight text-emerald-950 dark:text-emerald-200 mt-0.5 block">
                            {changeOrdersApproved}
                        </span>
                        <span className="text-[10px] text-emerald-800/80 dark:text-emerald-400/80 truncate block">
                            {formatCurrency(changeOrdersApprovedValue)}
                        </span>
                    </Link>

                    <Link
                        href={getChangeOrdersUrl('draft')}
                        className="rounded-xl border border-border/70 bg-card p-2.5 transition-colors hover:bg-muted/50 text-left"
                    >
                        <span className="text-[11px] font-medium text-muted-foreground flex items-center gap-1">
                            <FileEdit className="size-3 text-muted-foreground" />
                            Drafts
                        </span>
                        <span className="text-lg font-bold tracking-tight text-foreground mt-0.5 block">
                            {changeOrdersDraft}
                        </span>
                        <span className="text-[10px] text-muted-foreground block">
                            {changeOrdersRejected > 0 ? `${changeOrdersRejected} rejected` : 'In preparation'}
                        </span>
                    </Link>
                </div>

                {/* Recent Items List */}
                <div className="space-y-2 flex-1">
                    <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground px-0.5">
                        <span>Recent Change Orders</span>
                        <span className="text-[11px] font-normal">Amount</span>
                    </div>

                    {recentChangeOrders.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border/80 p-6 text-center space-y-2 bg-muted/20">
                            <FolderKanban className="size-6 text-muted-foreground/60 mx-auto" />
                            <p className="text-xs text-muted-foreground font-medium">
                                No change orders recorded yet.
                            </p>
                            <Button variant="outline" size="sm" asChild className="h-7 text-xs">
                                <Link href={getChangeOrdersUrl()}>Open Change Orders Hub</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-1.5 divide-y divide-border/40">
                            {recentChangeOrders.map((item) => (
                                <Link
                                    key={item.id}
                                    href={item.href}
                                    className="group flex items-center justify-between gap-3 pt-2 first:pt-0 hover:bg-muted/40 p-2 rounded-lg transition-colors -mx-1"
                                >
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-xs font-bold text-foreground group-hover:text-primary transition-colors truncate max-w-[220px]">
                                                {item.title}
                                            </span>
                                            <ChangeOrderStatusBadge status={item.status} className="scale-90 origin-left" />
                                        </div>
                                        <div className="flex items-center gap-2 text-[11px] text-muted-foreground truncate">
                                            <span className="truncate">{item.project_name}</span>
                                            {item.created_at && (
                                                <>
                                                    <span>·</span>
                                                    <span className="shrink-0">{formatRelativeTime(item.created_at)}</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-xs font-bold text-foreground block">
                                            ${item.total_amount}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default ChangeOrdersOverview;
