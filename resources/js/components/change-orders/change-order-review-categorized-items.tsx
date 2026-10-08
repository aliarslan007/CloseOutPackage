import { ChangeOrderItemDescriptionCell } from '@/components/change-orders/change-order-item-description-cell';
import type { ChangeOrderItem } from '@/components/change-orders/change-order-types';
import { formatCurrency, formatVendors } from '@/components/change-orders/change-order-format';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, DollarSign, Gift } from 'lucide-react';
import * as React from 'react';

export interface ChangeOrderReviewCategorizedItemsProps {
    items: ChangeOrderItem[];
}

export function ChangeOrderReviewCategorizedItems({
    items,
}: Readonly<ChangeOrderReviewCategorizedItemsProps>) {
    const { billedItems, nonBilledItems, totalBilled, totalAbsorbed, totalScope } =
        React.useMemo(() => {
            const billed = items.filter((i) => !i.cost_absorbed_by_solucomp);
            const nonBilled = items.filter((i) => Boolean(i.cost_absorbed_by_solucomp));

            const sumAmount = (list: ChangeOrderItem[]) =>
                list.reduce((sum, i) => sum + (parseFloat(String(i.amount)) || 0), 0);

            const billedSum = sumAmount(billed);
            const absorbedSum = sumAmount(nonBilled);

            return {
                billedItems: billed,
                nonBilledItems: nonBilled,
                totalBilled: billedSum,
                totalAbsorbed: absorbedSum,
                totalScope: billedSum + absorbedSum,
            };
        }, [items]);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Line Items Breakdown
                    </h3>
                    <p className="text-[11px] text-muted-foreground">
                        Categorized into billed items and complimentary services absorbed by Solucomp.
                    </p>
                </div>
                <div className="flex items-center gap-2 text-xs">
                    <span className="text-[11px] font-semibold text-muted-foreground">
                        Total Items: {items.length}
                    </span>
                </div>
            </div>

            {/* Side-by-side with vertical divider */}
            <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-border/80 rounded-xl border border-border/80 bg-card overflow-hidden shadow-xs">
                {/* Left Column: Billed Items */}
                <div className="flex flex-col">
                    <div className="border-b border-border/70 bg-muted/30 px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <DollarSign className="size-4 text-primary" />
                            <span className="text-xs font-bold uppercase tracking-wide text-foreground">
                                Billed
                            </span>
                        </div>
                        <Badge variant="secondary" className="text-[10px] font-bold">
                            {billedItems.length} {billedItems.length === 1 ? 'Item' : 'Items'}
                        </Badge>
                    </div>

                    <div className="flex-1 divide-y divide-border/60">
                        {billedItems.length === 0 ? (
                            <div className="p-6 text-center text-xs text-muted-foreground italic">
                                No billed items added.
                            </div>
                        ) : (
                            billedItems.map((item, idx) => (
                                <div key={item.id ?? idx} className="p-3.5 flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start gap-2">
                                            <span className="size-4 rounded bg-muted text-[10px] font-bold text-muted-foreground flex items-center justify-center shrink-0 mt-0.5">
                                                {idx + 1}
                                            </span>
                                            <ChangeOrderItemDescriptionCell
                                                item={item}
                                                itemIndex={idx + 1}
                                                className="flex-1"
                                            />
                                        </div>
                                        <div className="mt-1 ml-6 flex items-center gap-2 text-[11px] text-muted-foreground flex-wrap">
                                            {formatVendors(item.vendor_name) && (
                                                <span>{formatVendors(item.vendor_name)}</span>
                                            )}
                                            {item.category && (
                                                <span>• {item.category}</span>
                                            )}
                                        </div>
                                    </div>
                                    <span className="font-mono font-bold text-foreground text-xs shrink-0">
                                        {formatCurrency(item.amount)}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>

                    <div className="border-t border-border/70 bg-muted/20 px-4 py-2.5 flex items-center justify-between mt-auto">
                        <span className="text-[11px] font-semibold text-muted-foreground">
                            Billed Subtotal
                        </span>
                        <span className="font-mono font-bold text-foreground text-sm">
                            {formatCurrency(totalBilled)}
                        </span>
                    </div>
                </div>

                {/* Right Column: Non-Billed (Cost Absorbed by Solucomp) */}
                <div className="flex flex-col">
                    <div className="border-b border-border/70 bg-emerald-500/5 px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Gift className="size-4 text-emerald-600 dark:text-emerald-400" />
                            <span className="text-xs font-bold uppercase tracking-wide text-foreground">
                                Non-Billed <span className="font-normal text-muted-foreground text-[11px]">(Cost Absorbed by Solucomp)</span>
                            </span>
                        </div>
                        <span className="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                            {nonBilledItems.length} Free
                        </span>
                    </div>

                    <div className="flex-1 divide-y divide-border/60">
                        {nonBilledItems.length === 0 ? (
                            <div className="p-6 text-center text-xs text-muted-foreground italic">
                                No cost-absorbed items in this change order.
                            </div>
                        ) : (
                            nonBilledItems.map((item, idx) => (
                                <div key={item.id ?? idx} className="p-3.5 flex items-start justify-between gap-3 bg-emerald-500/[0.02]">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start gap-2">
                                            <span className="size-4 rounded bg-emerald-500/10 text-[10px] font-bold text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0 mt-0.5">
                                                {idx + 1}
                                            </span>
                                            <ChangeOrderItemDescriptionCell
                                                item={item}
                                                itemIndex={idx + 1}
                                                className="flex-1"
                                            />
                                        </div>
                                        <div className="mt-1 ml-6 flex items-center gap-2 text-[11px] text-muted-foreground flex-wrap">
                                            {formatVendors(item.vendor_name) && (
                                                <span>{formatVendors(item.vendor_name)}</span>
                                            )}
                                            {item.category && (
                                                <span>• {item.category}</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-col items-end shrink-0">
                                        <div className="flex items-center gap-1.5">
                                            <span className="line-through text-muted-foreground text-[11px] font-mono">
                                                {formatCurrency(item.amount)}
                                            </span>
                                            <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                                                $0.00
                                            </span>
                                        </div>
                                        <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            Free to Customer
                                        </span>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    <div className="border-t border-border/70 bg-emerald-500/5 px-4 py-2.5 flex items-center justify-between mt-auto">
                        <div className="flex items-center gap-1.5">
                            <CheckCircle2 className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            <span className="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                Total Absorbed by Solucomp
                            </span>
                        </div>
                        <span className="font-mono font-bold text-emerald-700 dark:text-emerald-300 text-sm">
                            {formatCurrency(totalAbsorbed)}
                        </span>
                    </div>
                </div>
            </div>

            {/* High-level summary strip */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-xl border border-border/70 bg-muted/20 p-3.5">
                <div>
                    <span className="text-[10px] uppercase tracking-wider font-bold text-muted-foreground block">
                        Customer Total Due (Billed)
                    </span>
                    <span className="text-lg font-bold font-mono text-foreground block mt-0.5">
                        {formatCurrency(totalBilled)}
                    </span>
                </div>
                <div>
                    <span className="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-400 block">
                        Cost Absorbed by Solucomp
                    </span>
                    <span className="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 block mt-0.5">
                        {formatCurrency(totalAbsorbed)}
                    </span>
                </div>
                <div>
                    <span className="text-[10px] uppercase tracking-wider font-bold text-muted-foreground block">
                        Total Scope Value
                    </span>
                    <span className="text-lg font-bold font-mono text-muted-foreground block mt-0.5">
                        {formatCurrency(totalScope)}
                    </span>
                </div>
            </div>
        </div>
    );
}
