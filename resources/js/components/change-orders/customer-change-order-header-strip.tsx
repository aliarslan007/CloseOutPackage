import { ChangeOrder } from '@/components/change-orders/change-order-types';
import { formatCurrency, formatDate, getFieldDateBadgeInfo } from '@/components/change-orders/change-order-format';
import { Calendar } from 'lucide-react';
import * as React from 'react';

export interface CustomerChangeOrderHeaderStripProps {
    changeOrder: ChangeOrder;
    totalAbsorbed: number;
}

export const CustomerChangeOrderHeaderStrip = React.memo(function CustomerChangeOrderHeaderStrip({
    changeOrder,
    totalAbsorbed,
}: Readonly<CustomerChangeOrderHeaderStripProps>) {
    const creatorName = changeOrder.creator?.name ?? 'Project Manager';
    const creatorEmail = changeOrder.creator?.email;
    const creatorPhotoUrl = changeOrder.creator?.profile_photo_url ?? null;
    const creatorInitials = creatorName
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();

    const fieldDateBadge = getFieldDateBadgeInfo(changeOrder.change_order_date);

    return (
        <div className="rounded-xl border border-border/80 bg-card p-4 sm:p-5 shadow-xs">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-xs">
                {/* Change Order Title */}
                <div className="min-w-0">
                    <span className="font-semibold text-muted-foreground block text-[10px] uppercase tracking-wider">
                        Change Order
                    </span>
                    <span
                        className="font-bold text-foreground text-xs mt-0.5 block truncate"
                        title={changeOrder.title}
                    >
                        {changeOrder.title}
                    </span>
                    <span className="font-mono text-[10px] text-muted-foreground">
                        CO-{changeOrder.id.toString().padStart(4, '0')}
                    </span>
                </div>

                {/* Submitted By */}
                <div className="min-w-0">
                    <span className="font-semibold text-muted-foreground block text-[10px] uppercase tracking-wider">
                        Submitted By
                    </span>
                    <span className="mt-0.5 flex items-center gap-2 min-w-0">
                        {creatorPhotoUrl ? (
                            <img
                                src={creatorPhotoUrl}
                                alt={creatorName}
                                loading="lazy"
                                decoding="async"
                                className="size-7 shrink-0 rounded-full border border-border object-cover"
                            />
                        ) : (
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                                {creatorInitials}
                            </span>
                        )}
                        <span className="min-w-0">
                            <span
                                className="font-bold text-foreground text-xs block truncate"
                                title={creatorName}
                            >
                                {creatorName}
                            </span>
                            {creatorEmail && (
                                <span className="text-[10px] text-muted-foreground block truncate">
                                    {creatorEmail}
                                </span>
                            )}
                        </span>
                    </span>
                </div>

                {/* Date of Occurrence */}
                <div className="min-w-0">
                    <span className="font-semibold text-muted-foreground block text-[10px] uppercase tracking-wider">
                        Date of Occurrence
                    </span>
                    <span className="font-bold text-foreground text-xs mt-0.5 flex items-center gap-1.5">
                        <Calendar className="size-3.5 text-primary shrink-0" />
                        <span>{formatDate(changeOrder.change_order_date)}</span>
                    </span>
                    {fieldDateBadge.variant === 'future' && (
                        <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 mt-0.5">
                            {fieldDateBadge.label}
                        </span>
                    )}
                    {fieldDateBadge.variant === 'today' && (
                        <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 mt-0.5">
                            {fieldDateBadge.label}
                        </span>
                    )}
                    {fieldDateBadge.variant === 'past' && (
                        <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-medium bg-muted text-muted-foreground border border-border mt-0.5">
                            {fieldDateBadge.label}
                        </span>
                    )}
                </div>

                {/* Date Sent */}
                <div className="min-w-0">
                    <span className="font-semibold text-muted-foreground block text-[10px] uppercase tracking-wider">
                        Date Sent
                    </span>
                    <span className="font-bold text-foreground text-xs mt-0.5 block">
                        {formatDate(changeOrder.sent_at || changeOrder.created_at)}
                    </span>
                </div>

                {/* Total Billed — typographic anchor of the strip */}
                <div className="min-w-0 sm:border-l sm:border-border sm:pl-4">
                    <span className="font-semibold text-muted-foreground block text-[10px] uppercase tracking-wider">
                        Total Billed
                    </span>
                    <span className="mt-0.5 block text-xl font-bold tabular-nums tracking-tight text-foreground">
                        {formatCurrency(changeOrder.total_amount)}
                    </span>
                    {totalAbsorbed > 0 && (
                        <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 block mt-0.5">
                            + {formatCurrency(totalAbsorbed)} Absorbed by Solucomp (Free)
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
});

export default CustomerChangeOrderHeaderStrip;
