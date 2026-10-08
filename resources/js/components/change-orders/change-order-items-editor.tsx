import {
    ChangeOrderItem,
    DEFAULT_CHANGE_ORDER_ITEM_DESCRIPTION_LIMIT,
    DEFAULT_CHANGE_ORDER_ITEM_VENDOR_LIMIT,
    ITEM_CATEGORIES,
} from '@/components/change-orders/change-order-types';
import { formatCurrency } from '@/components/change-orders/change-order-format';
import { VendorTagInput } from '@/components/change-orders/vendor-tag-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { DollarSign, Gift, Plus, Trash2 } from 'lucide-react';
import * as React from 'react';

export interface ChangeOrderItemsEditorProps {
    items: ChangeOrderItem[];
    onAddItem: () => void;
    onRemoveItem: (index: number) => void;
    onUpdateItem: (index: number, field: keyof ChangeOrderItem, value: unknown) => void;
    itemVendorLimit?: number;
    itemDescriptionLimit?: number;
}

export function ChangeOrderItemsEditor({
    items,
    onAddItem,
    onRemoveItem,
    onUpdateItem,
    itemVendorLimit = DEFAULT_CHANGE_ORDER_ITEM_VENDOR_LIMIT,
    itemDescriptionLimit = DEFAULT_CHANGE_ORDER_ITEM_DESCRIPTION_LIMIT,
}: Readonly<ChangeOrderItemsEditorProps>) {
    const { billedItems, absorbedItems, billedTotal, absorbedTotal, totalScope } =
        React.useMemo(() => {
            const billed = items.filter((i) => !i.cost_absorbed_by_solucomp);
            const absorbed = items.filter((i) => Boolean(i.cost_absorbed_by_solucomp));

            const sumAmount = (list: ChangeOrderItem[]) =>
                list.reduce(
                    (sum, item) =>
                        sum +
                        (typeof item.amount === 'number'
                            ? item.amount
                            : Number.parseFloat(String(item.amount)) || 0),
                    0
                );

            const bSum = sumAmount(billed);
            const aSum = sumAmount(absorbed);

            return {
                billedItems: billed,
                absorbedItems: absorbed,
                billedTotal: bSum,
                absorbedTotal: aSum,
                totalScope: bSum + aSum,
            };
        }, [items]);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between border-b border-border/70 pb-4">
                <div>
                    <h2 className="text-lg font-bold text-foreground">
                        Itemized Cost Lines
                    </h2>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Add out-of-scope line items, vendors, categories, and estimated costs.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="info"
                    size="sm"
                    onClick={onAddItem}
                    className="rounded-xl gap-1.5 text-xs font-semibold cursor-pointer"
                >
                    <Plus className="size-3.5" />
                    <span>Add Item</span>
                </Button>
            </div>

            {/* Line Items List */}
            <div className="space-y-4">
                {items.map((item, idx) => {
                    const isAbsorbed = Boolean(item.cost_absorbed_by_solucomp);

                    return (
                        <div
                            key={idx}
                            className={cn(
                                'p-4 rounded-xl border transition-all space-y-4 shadow-2xs',
                                isAbsorbed
                                    ? 'border-emerald-500/40 bg-emerald-500/[0.03]'
                                    : 'border-border/90 bg-muted/20'
                            )}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2.5">
                                    <span
                                        className={cn(
                                            'size-6 rounded-md font-bold text-xs flex items-center justify-center',
                                            isAbsorbed
                                                ? 'bg-emerald-500/20 text-emerald-800 dark:text-emerald-200'
                                                : 'bg-foreground/5 text-foreground'
                                        )}
                                    >
                                        {idx + 1}
                                    </span>
                                    <label className="flex items-center gap-2 text-xs font-semibold cursor-pointer select-none">
                                        <input
                                            type="checkbox"
                                            checked={isAbsorbed}
                                            onChange={(e) =>
                                                onUpdateItem(
                                                    idx,
                                                    'cost_absorbed_by_solucomp',
                                                    e.target.checked
                                                )
                                            }
                                            className="size-4 rounded border-border text-emerald-600 focus:ring-emerald-500/20 cursor-pointer"
                                        />
                                        <span
                                            className={cn(
                                                'transition-colors',
                                                isAbsorbed
                                                    ? 'text-emerald-700 dark:text-emerald-300 font-bold'
                                                    : 'text-foreground'
                                            )}
                                        >
                                            Cost Absorbed by Solucomp
                                        </span>
                                    </label>
                                    {isAbsorbed && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                                            <Gift className="size-3" /> Free to Customer ($0.00 billed)
                                        </span>
                                    )}
                                </div>

                                {items.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => onRemoveItem(idx)}
                                        aria-label="Remove item"
                                        className="text-muted-foreground hover:text-destructive p-1 rounded-md transition-colors cursor-pointer"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                )}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3">
                                {/* Description */}
                                <div className="sm:col-span-6 space-y-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <Label className="text-[11px] font-semibold text-muted-foreground">
                                            Description *
                                        </Label>
                                        <span className="text-[10px] tabular-nums text-muted-foreground">
                                            {item.description.length}/{itemDescriptionLimit}
                                        </span>
                                    </div>
                                    <Input
                                        type="text"
                                        placeholder="e.g. Tree removal / extra trenching"
                                        value={item.description}
                                        onChange={(e) =>
                                            onUpdateItem(idx, 'description', e.target.value)
                                        }
                                        maxLength={itemDescriptionLimit}
                                        className="h-9 text-xs rounded-lg"
                                    />
                                </div>

                                {/* Vendor / Company */}
                                <div className="sm:col-span-3 space-y-1">
                                    <Label className="text-[11px] font-semibold text-muted-foreground">
                                        Vendor / Company
                                    </Label>
                                    <VendorTagInput
                                        id={`item-${idx}-vendor`}
                                        value={item.vendor_name ?? []}
                                        onChange={(vendors) =>
                                            onUpdateItem(idx, 'vendor_name', vendors)
                                        }
                                        limit={itemVendorLimit}
                                    />
                                </div>

                                {/* Category */}
                                <div className="sm:col-span-3 space-y-1">
                                    <Label className="text-[11px] font-semibold text-muted-foreground">
                                        Category
                                    </Label>
                                    <select
                                        value={item.category ?? 'Other'}
                                        onChange={(e) =>
                                            onUpdateItem(idx, 'category', e.target.value)
                                        }
                                        className="h-9 w-full px-2.5 text-xs rounded-lg border border-border bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        {ITEM_CATEGORIES.map((cat) => (
                                            <option key={cat} value={cat}>
                                                {cat}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Amount */}
                                <div className="sm:col-span-4 space-y-1">
                                    <Label className="text-[11px] font-semibold text-muted-foreground">
                                        {isAbsorbed ? 'Cost Absorbed Value ($ USD) *' : 'Amount ($ USD) *'}
                                    </Label>
                                    <div className="relative">
                                        <DollarSign className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            value={item.amount}
                                            onChange={(e) =>
                                                onUpdateItem(idx, 'amount', e.target.value)
                                            }
                                            className={cn(
                                                'h-9 pl-7 text-xs rounded-lg font-mono font-semibold',
                                                isAbsorbed && 'border-emerald-500/40 text-emerald-700 dark:text-emerald-300'
                                            )}
                                        />
                                    </div>
                                    {isAbsorbed && (
                                        <p className="text-[10px] text-emerald-600 dark:text-emerald-400">
                                            Solucomp will absorb this cost ($0.00 billed to customer).
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Subtotal Banner */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-muted/30 border border-border text-xs">
                <div>
                    <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">
                        Billed to Customer ({billedItems.length} {billedItems.length === 1 ? 'item' : 'items'})
                    </span>
                    <span className="text-base font-bold font-mono text-foreground block mt-0.5">
                        {formatCurrency(billedTotal)}
                    </span>
                </div>
                <div>
                    <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 block">
                        Cost Absorbed by Solucomp ({absorbedItems.length} free)
                    </span>
                    <span className="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 block mt-0.5">
                        {formatCurrency(absorbedTotal)}
                    </span>
                </div>
                <div>
                    <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">
                        Total Project Scope
                    </span>
                    <span className="text-base font-bold font-mono text-muted-foreground block mt-0.5">
                        {formatCurrency(totalScope)}
                    </span>
                </div>
            </div>
        </div>
    );
}
