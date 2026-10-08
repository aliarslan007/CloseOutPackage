import solucompLogo from '@/assets/images/solucomp-latest-logo.webp';
import { cn } from '@/lib/utils';
import { DollarSign, Gift, Info, RotateCcw } from 'lucide-react';
import * as React from 'react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { ChangeOrder } from './change-order-types';

export interface ChangeOrdersFinancialOverviewProps {
    orders: ChangeOrder[];
    selectedCustomerName?: string | null;
    selectedCustomerCompany?: string | null;
    selectedProjectName?: string | null;
    isFiltered?: boolean;
    onResetFilters?: () => void;
    className?: string;
}

export interface FinancialBreakdown {
    billedTotal: number;
    absorbedTotal: number;
    approvedBilled: number;
    approvedAbsorbed: number;
    pendingBilled: number;
    pendingAbsorbed: number;
    billedOrdersCount: number;
    absorbedOrdersCount: number;
}

export function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'number' ? amount : Number.parseFloat(String(amount)) || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Calculates billed and absorbed totals for a single change order.
 */
export function calculateSingleOrderFinancials(order: ChangeOrder): {
    billedAmount: number;
    absorbedAmount: number;
    isMixed: boolean;
} {
    const rawTotal =
        typeof order.total_amount === 'number'
            ? order.total_amount
            : Number.parseFloat(String(order.total_amount)) || 0;

    const items = order.items ?? [];
    if (items.length === 0) {
        return {
            billedAmount: rawTotal,
            absorbedAmount: 0,
            isMixed: false,
        };
    }

    let billed = 0;
    let absorbed = 0;

    for (const item of items) {
        if (!item.is_included) continue;
        const itemAmount =
            typeof item.amount === 'number'
                ? item.amount
                : Number.parseFloat(String(item.amount)) || 0;

        if (item.cost_absorbed_by_solucomp) {
            absorbed += itemAmount;
        } else {
            billed += itemAmount;
        }
    }

    return {
        billedAmount: billed,
        absorbedAmount: absorbed,
        isMixed: billed > 0 && absorbed > 0,
    };
}

function applySingleItemDecision(
    itemAmt: number,
    isAbsorbed: boolean,
    decision: string | undefined,
    breakdown: FinancialBreakdown
): boolean {
    if (decision === 'approved') {
        if (isAbsorbed) {
            breakdown.approvedAbsorbed += itemAmt;
        } else {
            breakdown.approvedBilled += itemAmt;
        }
        return true;
    }

    if (decision === 'pending' || !decision) {
        if (isAbsorbed) {
            breakdown.pendingAbsorbed += itemAmt;
        } else {
            breakdown.pendingBilled += itemAmt;
        }
    }
    return false;
}

function processGranularOrderItems(
    order: ChangeOrder,
    breakdown: FinancialBreakdown,
    single: { billedAmount: number; absorbedAmount: number }
): void {
    const items = order.items ?? [];
    let hasGranularApproved = false;

    for (const item of items) {
        if (!item.is_included) continue;
        const itemAmt = Number(item.amount) || 0;
        const isApproved = applySingleItemDecision(
            itemAmt,
            Boolean(item.cost_absorbed_by_solucomp),
            item.customer_decision,
            breakdown
        );
        if (isApproved) {
            hasGranularApproved = true;
        }
    }

    if (!hasGranularApproved && items.length === 0) {
        breakdown.pendingBilled += single.billedAmount;
        breakdown.pendingAbsorbed += single.absorbedAmount;
    }
}

function accumulateOrderFinancials(order: ChangeOrder, breakdown: FinancialBreakdown): void {
    const single = calculateSingleOrderFinancials(order);
    if (single.billedAmount > 0) {
        breakdown.billedTotal += single.billedAmount;
        breakdown.billedOrdersCount += 1;
    }
    if (single.absorbedAmount > 0) {
        breakdown.absorbedTotal += single.absorbedAmount;
        breakdown.absorbedOrdersCount += 1;
    }

    if (order.status === 'approved') {
        breakdown.approvedBilled += single.billedAmount;
        breakdown.approvedAbsorbed += single.absorbedAmount;
        return;
    }

    if (order.status === 'sent_to_customer' || order.status === 'customer_action_required') {
        processGranularOrderItems(order, breakdown, single);
        return;
    }

    if (order.status === 'draft') {
        breakdown.pendingBilled += single.billedAmount;
        breakdown.pendingAbsorbed += single.absorbedAmount;
    }
}

function renderFilterTargetLabel(
    customerName?: string | null,
    customerCompany?: string | null,
    projectName?: string | null
): string {
    if (customerName) {
        return customerCompany ? `${customerName} (${customerCompany})` : customerName;
    }
    if (projectName) {
        return projectName;
    }
    return 'current selection';
}

/**
 * Two ledgers that must never be confused: money the customer owes us, and
 * money we absorbed. The colour split is semantic, not decoration — every other
 * treatment stays identical so the two read as one instrument panel.
 *
 * Restraint rules that keep this out of "generated dashboard" territory: one
 * accent per card, a flat tinted surface (no gradient), a hairline border (not a
 * 2px saturated one), one square radius for everything, and a plain data row
 * instead of a pile of coloured chips.
 */
const CARD_TONES = {
    billed: {
        rule: 'bg-blue-600',
        surface: 'bg-blue-50/70 dark:bg-blue-950/25',
        border: 'border-blue-200/70 dark:border-blue-900/60',
        iconChip: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        label: 'text-blue-950 dark:text-blue-50',
        hint: 'text-blue-700/75 dark:text-blue-400/90',
        amount: 'text-blue-950 dark:text-white',
        status: 'border-blue-200 bg-white/70 text-blue-800 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-200',
        dot: 'bg-blue-600',
        divider: 'border-blue-200/70 dark:border-blue-900/50',
    },
    absorbed: {
        rule: 'bg-emerald-600',
        surface: 'bg-emerald-50/50 dark:bg-emerald-950/20',
        border: 'border-emerald-200/70 dark:border-emerald-900/60',
        iconChip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
        label: 'text-emerald-950 dark:text-emerald-50',
        hint: 'text-emerald-700/75 dark:text-emerald-400/90',
        amount: 'text-emerald-950 dark:text-white',
        status: 'border-emerald-200 bg-white/70 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
        dot: 'bg-emerald-600',
        divider: 'border-emerald-200/70 dark:border-emerald-900/50',
    },
} as const satisfies Record<string, Record<string, string>>;

type CardTone = keyof typeof CARD_TONES;

interface StatEntry {
    label: string;
    value: string;
    /** `positive` and `pending` are the only two things worth colour-coding. */
    tone?: 'positive' | 'pending';
}

const STAT_VALUE_CLASS = {
    positive: 'text-emerald-700 dark:text-emerald-400',
    pending: 'text-amber-700 dark:text-amber-400',
} as const;

interface SummaryCardProps {
    tone: CardTone;
    icon: LucideIcon;
    label: string;
    /** Supports a brand mark alongside the text (see the absorbed card). */
    hint: ReactNode;
    status: string;
    amount: string;
    stats: StatEntry[];
}

/**
 * Stat tiles only get elevation when they say something. This one sits inside
 * the page grid, so it earns its space with a left accent rule and a large
 * tabular figure instead of a gradient and a glow.
 */
function SummaryCard({
    tone,
    icon: Icon,
    label,
    hint,
    status,
    amount,
    stats,
}: Readonly<SummaryCardProps>) {
    const t = CARD_TONES[tone];

    return (
        <div className={cn('relative overflow-hidden rounded-lg border p-5 pl-6', t.surface, t.border)}>
            <span className={cn('absolute inset-y-0 left-0 w-[3px]', t.rule)} aria-hidden="true" />

            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span
                        className={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-md',
                            t.iconChip
                        )}
                    >
                        <Icon className="size-4" strokeWidth={2} />
                    </span>
                        <div className="min-w-0">
                            <p
                                className={cn(
                                    'text-[11px] font-semibold uppercase tracking-[0.08em]',
                                    t.label
                                )}
                            >
                                {label}
                            </p>
                            <div
                                className={cn(
                                    'flex items-center gap-2 truncate text-[11px]',
                                    t.hint
                                )}
                            >
                                {hint}
                            </div>
                        </div>

                </div>

                <span
                    className={cn(
                        'inline-flex shrink-0 items-center gap-1.5 rounded-sm border px-2 py-0.5 text-[11px] font-medium',
                        t.status
                    )}
                >
                    <span className={cn('size-1.5 rounded-full', t.dot)} aria-hidden="true" />
                    {status}
                </span>
            </div>

            <p
                className={cn(
                    /* The figure is the whole point of the card: it stays the
                       loudest thing on the row without resorting to a weight
                       so heavy the digits close up. */
                    'mt-4 font-heading text-[2.15rem] font-semibold leading-none tabular-nums tracking-[-0.02em] sm:text-[2.6rem]',
                    t.amount
                )}
            >
                {amount}
            </p>

            <dl
                className={cn(
                    'mt-4 flex flex-wrap items-baseline gap-x-5 gap-y-1 border-t pt-3 text-xs',
                    t.divider
                )}
            >
                {stats.map((stat) => (
                    <div key={stat.label} className="flex items-baseline gap-1.5">
                        <dt className="text-muted-foreground">{stat.label}</dt>
                        <dd
                            className={cn(
                                'font-semibold tabular-nums text-foreground',
                                stat.tone && STAT_VALUE_CLASS[stat.tone]
                            )}
                        >
                            {stat.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

/**
 * Financial overview for the change-order list: what the customer owes against
 * what the company absorbed, plus the approved/pending split for each.
 */
export function ChangeOrdersFinancialOverview({
    orders = [],
    selectedCustomerName,
    selectedCustomerCompany,
    selectedProjectName,
    isFiltered = false,
    onResetFilters,
    className,
}: Readonly<ChangeOrdersFinancialOverviewProps>) {
    const financials = React.useMemo<FinancialBreakdown>(() => {
        const breakdown: FinancialBreakdown = {
            billedTotal: 0,
            absorbedTotal: 0,
            approvedBilled: 0,
            approvedAbsorbed: 0,
            pendingBilled: 0,
            pendingAbsorbed: 0,
            billedOrdersCount: 0,
            absorbedOrdersCount: 0,
        };

        for (const order of orders) {
            accumulateOrderFinancials(order, breakdown);
        }

        return breakdown;
    }, [orders]);

    const filterTargetText = renderFilterTargetLabel(
        selectedCustomerName,
        selectedCustomerCompany,
        selectedProjectName
    );

    return (
        <div className={cn('space-y-2.5', className)}>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <SummaryCard
                    tone="billed"
                    icon={DollarSign}
                    label="Total Billed to Customer"
                    hint="Payable by the customer"
                    status="Receivable"
                    amount={formatCurrency(financials.billedTotal)}
                    stats={[
                        { label: 'Orders', value: String(financials.billedOrdersCount) },
                        {
                            label: 'Approved',
                            value: formatCurrency(financials.approvedBilled),
                            tone: 'positive',
                        },
                        ...(financials.pendingBilled > 0
                            ? [
                                  {
                                      label: 'Pending',
                                      value: formatCurrency(financials.pendingBilled),
                                      tone: 'pending' as const,
                                  },
                              ]
                            : []),
                    ]}
                />

                <SummaryCard
                    tone="absorbed"
                    icon={Gift}
                    label="Non-Billed / Absorbed"
                    /* Mark leads the label — the same asset the sidebar uses, so
                       "absorbed" always names who absorbed it. */
                    hint={
                        <>
                            <img
                                src={solucompLogo}
                                alt="SoluComp"
                                className="size-[18px] shrink-0 object-contain"
                            />
                            <span className="truncate">Absorbed by Solucomp</span>
                        </>
                    }
                    status="$0 to customer"
                    amount={formatCurrency(financials.absorbedTotal)}
                    stats={[
                        { label: 'Orders', value: String(financials.absorbedOrdersCount) },
                        ...(financials.approvedAbsorbed > 0
                            ? [
                                  {
                                      label: 'Approved',
                                      value: formatCurrency(financials.approvedAbsorbed),
                                      tone: 'positive' as const,
                                  },
                              ]
                            : []),
                    ]}
                />
            </div>

            {/* Filter Context Bar */}
            {isFiltered && (
                <div className="px-4 py-2.5 rounded-xl border border-primary/20 bg-primary/5 flex items-center justify-between text-xs text-muted-foreground">
                    <div className="flex items-center gap-2 truncate">
                        <Info className="size-3.5 text-primary shrink-0" />
                        <span className="truncate">
                            Showing financial totals for{' '}
                            <strong className="font-semibold text-foreground">
                                {filterTargetText}
                            </strong>
                        </span>
                    </div>

                    {onResetFilters && (
                        <button
                            type="button"
                            onClick={onResetFilters}
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer shrink-0 ml-2"
                        >
                            <RotateCcw className="size-3" />
                            <span>Reset</span>
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
