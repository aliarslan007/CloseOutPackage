import type { ChangeOrderItem } from '@/components/change-orders/change-order-types';
import { formatCurrency, formatVendors } from '@/components/change-orders/change-order-format';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Check, ChevronDown, ChevronUp, Copy, FileText, Gift, Maximize2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ChangeOrderItemDescriptionCellProps {
    item: ChangeOrderItem;
    itemIndex?: number;
    className?: string;
    /** Character threshold to trigger clamp and expand controls. Defaults to 90. */
    clampThreshold?: number;
    /** Optional extra custom badge or indicator */
    extraBadge?: React.ReactNode;
}

export function ChangeOrderItemDescriptionCell({
    item,
    itemIndex,
    className,
    clampThreshold = 90,
    extraBadge,
}: Readonly<ChangeOrderItemDescriptionCellProps>) {
    const [isExpanded, setIsExpanded] = React.useState(false);
    const [isModalOpen, setIsModalOpen] = React.useState(false);
    const [copied, setCopied] = React.useState(false);

    const description = item.description || '';
    const isLong = description.length > clampThreshold || description.includes('\n');
    const isAbsorbed = Boolean(item.cost_absorbed_by_solucomp);

    const wordCount = React.useMemo(() => {
        if (!description.trim()) return 0;
        return description.trim().split(/\s+/).length;
    }, [description]);

    const handleCopy = React.useCallback(async (e?: React.MouseEvent) => {
        e?.stopPropagation();
        if (!description) return;
        try {
            await navigator.clipboard.writeText(description);
            setCopied(true);
            toast.success('Item description copied to clipboard');
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Failed to copy to clipboard');
        }
    }, [description]);

    return (
        <div className={cn('flex flex-col min-w-0 max-w-full text-xs', className)}>
            {/* Clamped or Expanded Inline Text */}
            <div className="relative group/desc min-w-0">
                <div
                    className={cn(
                        'text-foreground font-medium transition-all break-words [overflow-wrap:anywhere]',
                        !isExpanded && isLong && 'line-clamp-2 leading-relaxed',
                        isExpanded && isLong && 'whitespace-pre-wrap leading-relaxed bg-muted/20 p-2 rounded-md border border-border/60 max-h-64 overflow-y-auto'
                    )}
                    title={!isExpanded && isLong ? 'Click "Show more" or Expand to view full description' : undefined}
                >
                    {description || <span className="text-muted-foreground italic">No description provided</span>}
                </div>

                {/* Inline Toggle & Full View Actions for Long Content */}
                {isLong && (
                    <div className="mt-1 flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setIsExpanded((prev) => !prev);
                            }}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:text-primary/80 transition-colors cursor-pointer py-0.5"
                            aria-expanded={isExpanded}
                        >
                            {isExpanded ? (
                                <>
                                    <ChevronUp className="size-3" />
                                    <span>Show less</span>
                                </>
                            ) : (
                                <>
                                    <ChevronDown className="size-3" />
                                    <span>Show more ({wordCount} words)</span>
                                </>
                            )}
                        </button>

                        <span className="text-muted-foreground/40 text-[10px]">•</span>

                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setIsModalOpen(true);
                            }}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-muted-foreground hover:text-foreground transition-colors cursor-pointer py-0.5"
                            title="View full description in dedicated popup dialog"
                        >
                            <Maximize2 className="size-3" />
                            <span>Full view</span>
                        </button>
                    </div>
                )}
            </div>

            {/* Absorbed & Decision Badges */}
            {isAbsorbed && (
                <span className="mt-1.5 inline-flex max-w-full items-center gap-1 self-start rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                    <Gift className="size-3 shrink-0" />
                    <span className="min-w-0 whitespace-nowrap sm:hidden">
                        Absorbed · Free
                    </span>
                    <span className="hidden min-w-0 sm:inline">
                        Cost Absorbed by Solucomp (Free)
                    </span>
                </span>
            )}

            {item.customer_decision_note && (
                <span className="mt-1.5 text-[11px] text-muted-foreground bg-muted/30 p-1.5 rounded border border-border/50 break-words [overflow-wrap:anywhere]">
                    <span className="font-semibold text-foreground/80">Customer Note:</span> {item.customer_decision_note}
                </span>
            )}

            {extraBadge}

            {/* Enterprise "Full Item Description" Modal Dialog */}
            {isModalOpen && (
                <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                    <DialogContent className="sm:max-w-2xl max-h-[85vh] flex flex-col p-5 sm:p-6 overflow-hidden">
                        <DialogHeader className="space-y-2 border-b border-border/70 pb-3">
                            <div className="flex items-center justify-between gap-2 flex-wrap pr-6">
                                <DialogTitle className="text-base font-bold text-foreground flex items-center gap-2">
                                    <FileText className="size-4 text-primary" />
                                    <span>
                                        {itemIndex ? `Item #${itemIndex} Scope Details` : 'Item Scope Details'}
                                    </span>
                                </DialogTitle>

                                <div className="flex items-center gap-1.5 flex-wrap">
                                    {item.category && (
                                        <Badge variant="secondary" className="text-[10px] font-bold">
                                            {item.category}
                                        </Badge>
                                    )}
                                    {isAbsorbed ? (
                                        <Badge className="bg-emerald-600 text-white text-[10px] font-bold">
                                            Absorbed by Solucomp (Free)
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="font-mono text-[10px] font-bold">
                                            {formatCurrency(item.amount)}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            <DialogDescription className="text-xs text-muted-foreground flex items-center gap-3 flex-wrap">
                                <span>{wordCount} words · {description.length} characters</span>
                                {item.vendor_name && (
                                    <span>• Vendor: <strong className="text-foreground">{formatVendors(item.vendor_name)}</strong></span>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        {/* Description content viewport */}
                        <div className="flex-1 overflow-y-auto py-3 space-y-3 min-h-0">
                            <div className="rounded-md border border-border/70 bg-muted/20 p-4 text-xs sm:text-sm leading-relaxed text-foreground font-sans whitespace-pre-wrap select-text break-words [overflow-wrap:anywhere]">
                                {description}
                            </div>

                            {item.customer_decision_note && (
                                <div className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-300 space-y-1">
                                    <p className="font-bold uppercase tracking-wider text-[10px]">Customer Note / Reason</p>
                                    <p className="whitespace-pre-wrap break-words">{item.customer_decision_note}</p>
                                </div>
                            )}
                        </div>

                        <DialogFooter className="border-t border-border/70 pt-3 flex items-center justify-between sm:justify-between gap-2 flex-wrap">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleCopy}
                                className="h-8 text-xs gap-1.5 cursor-pointer"
                            >
                                {copied ? (
                                    <>
                                        <Check className="size-3.5 text-emerald-600" />
                                        <span className="text-emerald-600 font-semibold">Copied!</span>
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3.5" />
                                        <span>Copy description</span>
                                    </>
                                )}
                            </Button>

                            <Button
                                type="button"
                                variant="default"
                                size="sm"
                                onClick={() => setIsModalOpen(false)}
                                className="h-8 text-xs font-semibold px-4 cursor-pointer"
                            >
                                Close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

export default ChangeOrderItemDescriptionCell;
