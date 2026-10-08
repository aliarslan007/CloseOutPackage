import { ChangeOrder } from '@/components/change-orders/change-order-types';
import {
    formatDateTime,
    getOrderEditUrl,
} from '@/components/change-orders/missing-customer-dialog-helpers';
import { ArrowUpRight, CheckCircle2, Clock, Users } from 'lucide-react';
import * as React from 'react';

export interface UnassignedOrdersSectionProps {
    orders: ChangeOrder[];
}

export function UnassignedOrdersSection({ orders }: Readonly<UnassignedOrdersSectionProps>) {
    if (orders.length === 0) return null;

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs font-semibold text-foreground">
                <span className="flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                    <Users className="size-3.5" />
                    <span>No Customer Assigned ({orders.length}):</span>
                </span>
                <span className="text-[11px] text-muted-foreground font-normal">
                    Assign customer to send
                </span>
            </div>

            <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 divide-y divide-amber-500/20 p-1">
                {orders.map((order) => {
                    const code = `CO-${order.id.toString().padStart(4, '0')}`;
                    const editUrl = getOrderEditUrl(order);

                    return (
                        <div
                            key={order.id}
                            className="flex items-center justify-between gap-3 p-2 text-xs"
                        >
                            <div className="flex flex-col min-w-0 flex-1">
                                <div className="flex items-center gap-2 min-w-0">
                                    <span className="font-mono text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/15 text-amber-800 dark:text-amber-300 shrink-0 whitespace-nowrap inline-flex items-center leading-none">
                                        {code}
                                    </span>
                                    <span className="font-semibold text-foreground truncate min-w-0">
                                        {order.title}
                                    </span>
                                </div>
                                {order.project && (
                                    <span className="text-[11px] text-muted-foreground mt-0.5 truncate">
                                        Project: {order.project.name}
                                    </span>
                                )}
                            </div>

                            {editUrl && (
                                <a
                                    href={editUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline shrink-0 px-2 py-1 rounded hover:bg-primary/5"
                                    title="Open in new tab to assign customer"
                                >
                                    <span>Assign</span>
                                    <ArrowUpRight className="size-3" />
                                </a>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export interface AlreadySentOrdersSectionProps {
    orders: ChangeOrder[];
}

export function AlreadySentOrdersSection({ orders }: Readonly<AlreadySentOrdersSectionProps>) {
    if (orders.length === 0) return null;

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs font-semibold text-foreground">
                <span className="flex items-center gap-1.5 text-blue-700 dark:text-blue-400">
                    <Clock className="size-3.5" />
                    <span>Warning: Already Sent ({orders.length})</span>
                </span>
                <span className="text-[11px] text-muted-foreground font-normal">
                    Will send fresh email links
                </span>
            </div>

            <div className="rounded-lg border border-blue-500/30 bg-blue-500/5 divide-y divide-blue-500/20 p-1">
                {alreadySentOrdersCard(orders)}
            </div>
            <p className="text-[11px] text-muted-foreground px-0.5">
                These change orders were already sent. They are not blocked; clicking below will notify the customers again.
            </p>
        </div>
    );
}

function alreadySentOrdersCard(orders: ChangeOrder[]) {
    return orders.map((order) => {
        const code = `CO-${order.id.toString().padStart(4, '0')}`;
        const recipientsList = order.recipients
            ?.map((r) => r.user?.name)
            .filter(Boolean)
            .join(', ');

        return (
            <div
                key={order.id}
                className="flex items-center justify-between gap-3 p-2 text-xs"
            >
                <div className="flex flex-col min-w-0 flex-1">
                    <div className="flex items-center gap-2 min-w-0">
                        <span className="font-mono text-[10px] font-bold px-2 py-0.5 rounded bg-blue-500/15 text-blue-800 dark:text-blue-300 shrink-0 whitespace-nowrap inline-flex items-center leading-none">
                            {code}
                        </span>
                        <span className="font-semibold text-foreground truncate min-w-0">
                            {order.title}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 mt-0.5 text-[11px] text-muted-foreground flex-wrap">
                        {order.sent_at && (
                            <span>Sent on: {formatDateTime(order.sent_at)}</span>
                        )}
                        {recipientsList && <span>• To: {recipientsList}</span>}
                    </div>
                </div>
                <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 shrink-0">
                    Already Sent
                </span>
            </div>
        );
    });
}

export interface DraftOrdersSectionProps {
    orders: ChangeOrder[];
}

export function DraftOrdersSection({ orders }: Readonly<DraftOrdersSectionProps>) {
    if (orders.length === 0) return null;

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs font-semibold text-foreground">
                <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="size-3.5" />
                    <span>First-Time Send ({orders.length}):</span>
                </span>
            </div>

            <div className="rounded-lg border border-border/80 bg-muted/20 divide-y divide-border/40 p-1">
                {orders.map((order) => {
                    const code = `CO-${order.id.toString().padStart(4, '0')}`;
                    const recipientsList = order.recipients
                        ?.map((r) => r.user?.name)
                        .filter(Boolean)
                        .join(', ');

                    return (
                        <div
                            key={order.id}
                            className="flex items-center justify-between gap-3 p-2 text-xs"
                        >
                            <div className="flex flex-col min-w-0 flex-1">
                                <div className="flex items-center gap-2 min-w-0">
                                    <span className="font-mono text-[10px] font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground shrink-0 whitespace-nowrap inline-flex items-center leading-none">
                                        {code}
                                    </span>
                                    <span className="font-medium text-foreground truncate min-w-0">
                                        {order.title}
                                    </span>
                                </div>
                                {recipientsList && (
                                    <span className="text-[10px] text-muted-foreground mt-0.5 truncate">
                                        To: {recipientsList}
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
