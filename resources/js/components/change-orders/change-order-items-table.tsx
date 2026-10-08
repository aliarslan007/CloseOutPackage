import { ChangeOrderItemDescriptionCell } from '@/components/change-orders/change-order-item-description-cell';
import { ItemDecisionBadge } from '@/components/change-orders/change-order-status-badge';
import type { ChangeOrderItem } from '@/components/change-orders/change-order-types';
import { formatCurrency, formatVendors } from './change-order-format';
import { cn } from '@/lib/utils';
import * as React from 'react';

export interface ChangeOrderItemsTableProps {
    items: ChangeOrderItem[];
    includedItemsCount: number;
    totalIncludedAmount: number;
}

/**
 * Read-only line-item table with the customer decision per row and cost-absorbed indicators.
 */
export const ChangeOrderItemsTable = React.memo(function ChangeOrderItemsTable({
    items,
    includedItemsCount,
    totalIncludedAmount,
}: Readonly<ChangeOrderItemsTableProps>) {
    const totalAbsorbed = React.useMemo(() => {
        return items
            .filter((i) => Boolean(i.cost_absorbed_by_solucomp))
            .reduce((sum, i) => sum + (parseFloat(String(i.amount)) || 0), 0);
    }, [items]);

    return (
        <div className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr className="border-b border-border/70 bg-muted/40 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            <th className="w-12 px-4 py-3 font-bold text-foreground">#</th>
                            <th className="px-4 py-3 font-bold text-foreground">
                                Description & Details
                            </th>
                            <th className="px-4 py-3 font-bold text-foreground">Category</th>
                            <th className="px-4 py-3 font-bold text-foreground">Vendor</th>
                            <th className="px-4 py-3 font-bold text-foreground text-center">
                                Decision
                            </th>
                            <th className="px-4 py-3 font-bold text-foreground text-right">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border/60">
                        {items.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-8 text-center text-muted-foreground italic"
                                >
                                    No items listed for this change order.
                                </td>
                            </tr>
                        ) : (
                            items.map((item, index) => {
                                const isAbsorbed = Boolean(item.cost_absorbed_by_solucomp);

                                return (
                                    <tr
                                        key={item.id ?? index}
                                        className={cn(
                                            'transition-colors',
                                            isAbsorbed && 'bg-emerald-500/[0.03]',
                                            !item.is_included && 'opacity-60 bg-muted/10'
                                        )}
                                    >
                                        <td className="px-4 py-3.5 font-mono text-muted-foreground">
                                            {index + 1}
                                        </td>
                                        <td className="px-4 py-3.5 max-w-sm sm:max-w-md md:max-w-lg">
                                            <ChangeOrderItemDescriptionCell
                                                item={item}
                                                itemIndex={index + 1}
                                            />
                                        </td>
                                        <td className="px-4 py-3.5 whitespace-nowrap text-muted-foreground">
                                            {item.category ?? '—'}
                                        </td>
                                        <td className="px-4 py-3.5 whitespace-nowrap text-muted-foreground">
                                            {formatVendors(item.vendor_name) || '—'}
                                        </td>
                                        <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                            <ItemDecisionBadge
                                                decision={item.customer_decision}
                                                isIncluded={item.is_included}
                                            />
                                        </td>
                                        <td className="px-4 py-3.5 text-right font-mono font-bold text-foreground whitespace-nowrap">
                                            {isAbsorbed ? (
                                                <div className="flex flex-col items-end">
                                                    <span className="line-through text-muted-foreground text-[10px] font-mono">
                                                        {formatCurrency(item.amount)}
                                                    </span>
                                                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                                                        $0.00 (Free)
                                                    </span>
                                                </div>
                                            ) : (
                                                formatCurrency(item.amount)
                                            )}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex items-center justify-between border-t border-border/80 bg-muted/20 px-4 py-3.5 text-xs flex-wrap gap-2">
                <div className="flex items-center gap-2">
                    <span className="font-semibold text-muted-foreground text-[11px]">
                        Total Billed ({includedItemsCount} of {items.length} items)
                    </span>
                    {totalAbsorbed > 0 && (
                        <span className="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                            • + {formatCurrency(totalAbsorbed)} Absorbed by Solucomp (Free)
                        </span>
                    )}
                </div>
                <span className="text-base font-bold text-foreground font-mono">
                    {formatCurrency(totalIncludedAmount)}
                </span>
            </div>
        </div>
    );
});

export default ChangeOrderItemsTable;
