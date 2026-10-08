import { ChangeOrderItemDescriptionCell } from '@/components/change-orders/change-order-item-description-cell';
import type { ChangeOrderItem, CustomerDecision } from '@/components/change-orders/change-order-types';
import { formatCurrency, formatVendors } from '@/components/change-orders/change-order-format';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Check, DollarSign, Gift, X } from 'lucide-react';
import * as React from 'react';

export interface CustomerCategorizedItemsTableProps {
    items: ChangeOrderItem[];
    decisions: Record<number, { decision: CustomerDecision; note?: string }>;
    isDecided: boolean;
    onDecisionChange: (itemId: number, decision: CustomerDecision) => void;
    onApproveAll?: () => void;
    onRejectAll?: () => void;
}

type SectionTone = 'billed' | 'absorbed';

function ItemDecisionControls({
    item,
    decision,
    isDecided,
    onDecisionChange,
}: {
    item: ChangeOrderItem;
    decision: CustomerDecision;
    isDecided: boolean;
    onDecisionChange: (itemId: number, decision: CustomerDecision) => void;
}) {
    if (!item.id) return null;
    const itemId = item.id;

    if (isDecided) {
        if (decision === 'approved') {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                    <Check className="size-3.5 stroke-[2.5]" /> Approved
                </span>
            );
        }
        if (decision === 'rejected') {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2.5 py-1 text-xs font-bold text-rose-700 dark:text-rose-300">
                    <X className="size-3.5 stroke-[2.5]" /> Rejected
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                Pending
            </span>
        );
    }

    return (
        <div className="flex items-center justify-center gap-2">
            <button
                type="button"
                onClick={() => onDecisionChange(itemId, 'approved')}
                className={cn(
                    'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs',
                    decision === 'approved'
                        ? 'bg-emerald-600 text-white ring-2 ring-emerald-600/30'
                        : 'border border-emerald-500/40 text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20'
                )}
            >
                <Check className="size-3.5 stroke-[2.5]" />
                <span>Approve</span>
            </button>
            <button
                type="button"
                onClick={() => onDecisionChange(itemId, 'rejected')}
                className={cn(
                    'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs',
                    decision === 'rejected'
                        ? 'bg-rose-600 text-white ring-2 ring-rose-600/30'
                        : 'border border-rose-500/40 text-rose-700 dark:text-rose-400 bg-rose-500/10 hover:bg-rose-500/20'
                )}
            >
                <X className="size-3.5 stroke-[2.5]" />
                <span>Reject</span>
            </button>
        </div>
    );
}

/** Full-width divider row that splits the single table into "you pay" vs "free" groups. */
function SectionRow({
    tone,
    title,
    badge,
    hint,
}: {
    tone: SectionTone;
    title: string;
    badge: string;
    hint: string;
}) {
    const isAbsorbed = tone === 'absorbed';

    return (
        <tr className={isAbsorbed ? 'bg-emerald-500/[0.07]' : 'bg-muted/30'}>
            <td colSpan={6} className="px-4 py-2">
                <div className="flex items-center justify-between gap-2 flex-wrap">
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className={cn(
                                'flex size-5 items-center justify-center rounded',
                                isAbsorbed ? 'bg-emerald-600 text-white' : 'bg-primary/10 text-primary'
                            )}
                        >
                            {isAbsorbed ? (
                                <Gift className="size-3.5" />
                            ) : (
                                <DollarSign className="size-3.5" />
                            )}
                        </span>
                        <span
                            className={cn(
                                'text-[11px] font-bold uppercase tracking-wider',
                                isAbsorbed ? 'text-emerald-800 dark:text-emerald-200' : 'text-foreground'
                            )}
                        >
                            {title}
                        </span>
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                isAbsorbed
                                    ? 'bg-emerald-600 text-white'
                                    : 'bg-muted text-muted-foreground'
                            )}
                        >
                            {badge}
                        </span>
                    </span>
                    <span
                        className={cn(
                            'text-[11px]',
                            isAbsorbed
                                ? 'font-semibold text-emerald-700 dark:text-emerald-300'
                                : 'text-muted-foreground'
                        )}
                    >
                        {hint}
                    </span>
                </div>
            </td>
        </tr>
    );
}

export function CustomerCategorizedItemsTable({
    items,
    decisions,
    isDecided,
    onDecisionChange,
    onApproveAll,
    onRejectAll,
}: Readonly<CustomerCategorizedItemsTableProps>) {
    const { billedItems, nonBilledItems, totalBilled, totalAbsorbed } = React.useMemo(() => {
        const billed = items.filter((i) => !i.cost_absorbed_by_solucomp);
        const nonBilled = items.filter((i) => Boolean(i.cost_absorbed_by_solucomp));

        const sumAmount = (list: ChangeOrderItem[]) =>
            list.reduce((sum, i) => sum + (parseFloat(String(i.amount)) || 0), 0);

        return {
            billedItems: billed,
            nonBilledItems: nonBilled,
            totalBilled: sumAmount(billed),
            totalAbsorbed: sumAmount(nonBilled),
        };
    }, [items]);

    const itemWord = (count: number) => (count === 1 ? 'item' : 'items');

    return (
        <div className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
            {/* Card header: what this is, plus bulk decisions */}
            <div className="flex items-center justify-between gap-3 border-b border-border/70 bg-muted/20 px-4 py-3 flex-wrap">
                <div className="min-w-0">
                    <h2 className="text-xs font-bold uppercase tracking-wider text-foreground">
                        Line Items
                    </h2>
                    <p className="text-[11px] text-muted-foreground mt-0.5">
                        Approve or reject each item below.
                    </p>
                </div>

                {!isDecided && onApproveAll && onRejectAll && (
                    <div className="flex items-center gap-1.5 shrink-0">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onApproveAll}
                            className="h-7 text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 hover:bg-emerald-500/10 cursor-pointer"
                        >
                            Approve All
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onRejectAll}
                            className="h-7 text-[11px] font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-500/10 cursor-pointer"
                        >
                            Reject All
                        </Button>
                    </div>
                )}
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr className="border-b border-border/70 bg-muted/40 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            <th className="w-12 px-4 py-3 font-bold text-foreground">#</th>
                            <th className="px-4 py-3 font-bold text-foreground">Description</th>
                            <th className="px-4 py-3 font-bold text-foreground">Category</th>
                            <th className="px-4 py-3 font-bold text-foreground">Vendor / Company</th>
                            <th className="px-4 py-3 font-bold text-foreground text-right">Amount</th>
                            <th className="px-4 py-3 font-bold text-foreground text-center w-60">
                                Your Decision
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border/60">
                        {items.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-8 text-center text-xs text-muted-foreground italic"
                                >
                                    No items listed for this change order.
                                </td>
                            </tr>
                        )}

                        {billedItems.length > 0 && (
                            <SectionRow
                                tone="billed"
                                title="You Pay"
                                badge={`${billedItems.length} ${itemWord(billedItems.length)}`}
                                hint="Billed to your account"
                            />
                        )}
                        {billedItems.map((item, idx) => {
                            const currentDecision = item.id
                                ? (decisions[item.id]?.decision ?? 'pending')
                                : 'pending';

                            return (
                                <tr
                                    key={item.id ?? `billed-${idx}`}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3.5 font-semibold text-muted-foreground">
                                        {idx + 1}
                                    </td>
                                    <td className="px-4 py-3.5 max-w-sm sm:max-w-md md:max-w-lg">
                                        <ChangeOrderItemDescriptionCell
                                            item={item}
                                            itemIndex={idx + 1}
                                        />
                                    </td>
                                    <td className="px-4 py-3.5 text-muted-foreground whitespace-nowrap">
                                        {item.category ?? '—'}
                                    </td>
                                    <td className="px-4 py-3.5 text-foreground whitespace-nowrap">
                                        {formatVendors(item.vendor_name) || '—'}
                                    </td>
                                    <td className="px-4 py-3.5 font-mono font-bold text-foreground text-right whitespace-nowrap">
                                        {formatCurrency(item.amount)}
                                    </td>
                                    <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                        <ItemDecisionControls
                                            item={item}
                                            decision={currentDecision}
                                            isDecided={isDecided}
                                            onDecisionChange={onDecisionChange}
                                        />
                                    </td>
                                </tr>
                            );
                        })}

                        {nonBilledItems.length > 0 && (
                            <SectionRow
                                tone="absorbed"
                                title="No Charge"
                                badge={`${nonBilledItems.length} free`}
                                hint="Absorbed by SoluComp — $0.00 to you"
                            />
                        )}
                        {nonBilledItems.map((item, idx) => {
                            const currentDecision = item.id
                                ? (decisions[item.id]?.decision ?? 'pending')
                                : 'pending';

                            return (
                                <tr
                                    key={item.id ?? `absorbed-${idx}`}
                                    className="bg-emerald-500/[0.02] transition-colors hover:bg-emerald-500/[0.06]"
                                >
                                    <td className="px-4 py-3.5 font-semibold text-muted-foreground">
                                        {billedItems.length + idx + 1}
                                    </td>
                                    <td className="px-4 py-3.5 max-w-sm sm:max-w-md md:max-w-lg">
                                        <ChangeOrderItemDescriptionCell
                                            item={item}
                                            itemIndex={billedItems.length + idx + 1}
                                        />
                                    </td>
                                    <td className="px-4 py-3.5 text-muted-foreground whitespace-nowrap">
                                        {item.category ?? '—'}
                                    </td>
                                    <td className="px-4 py-3.5 text-foreground whitespace-nowrap">
                                        {formatVendors(item.vendor_name) || '—'}
                                    </td>
                                    <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                        <div className="flex flex-col items-end">
                                            <span className="line-through text-muted-foreground text-[11px] font-mono">
                                                {formatCurrency(item.amount)}
                                            </span>
                                            <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                                $0.00
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                        <ItemDecisionControls
                                            item={item}
                                            decision={currentDecision}
                                            isDecided={isDecided}
                                            onDecisionChange={onDecisionChange}
                                        />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {items.length > 0 && (
                <div className="flex items-center justify-between gap-3 border-t border-border/80 bg-muted/20 px-4 py-3 flex-wrap">
                    <div className="min-w-0">
                        <span className="text-[11px] font-semibold text-muted-foreground">
                            You Pay ({billedItems.length} {itemWord(billedItems.length)})
                        </span>
                        {totalAbsorbed > 0 && (
                            <p className="flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 mt-0.5">
                                <Gift className="size-3" />
                                {formatCurrency(totalAbsorbed)} of work absorbed by SoluComp — free
                            </p>
                        )}
                    </div>
                    <span className="font-mono font-bold text-foreground text-base">
                        {formatCurrency(totalBilled)}
                    </span>
                </div>
            )}
        </div>
    );
}
