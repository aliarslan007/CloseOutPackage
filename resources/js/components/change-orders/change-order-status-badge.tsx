import { cn } from '@/lib/utils';
import { Check, Clock, HelpCircle, Send, X } from 'lucide-react';
import * as React from 'react';
import { ChangeOrderStatus, CustomerDecision } from './change-order-types';

export interface ChangeOrderStatusBadgeProps {
    status: ChangeOrderStatus | (string & {});
    className?: string;
    showIcon?: boolean;
}

export function ChangeOrderStatusBadge({
    status,
    className,
    showIcon = false,
}: Readonly<ChangeOrderStatusBadgeProps>) {
    switch (status) {
        case 'approved':
            return (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 shadow-2xs',
                        className
                    )}
                >
                    {showIcon && <Check className="size-3.5" />}
                    <span>Approved</span>
                </span>
            );

        case 'rejected':
            return (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 shadow-2xs',
                        className
                    )}
                >
                    {showIcon && <X className="size-3.5" />}
                    <span>Rejected</span>
                </span>
            );

        case 'customer_action_required':
        case 'partially_approved':
            return (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 shadow-2xs',
                        className
                    )}
                >
                    {showIcon && <HelpCircle className="size-3.5" />}
                    <span>Partially Approved</span>
                </span>
            );

        case 'sent_to_customer':
        case 'sent':
            return (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 shadow-2xs',
                        className
                    )}
                >
                    {showIcon && <Send className="size-3.5" />}
                    <span>Sent</span>
                </span>
            );

        case 'draft':
        default:
            return (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border shadow-2xs',
                        className
                    )}
                >
                    {showIcon && <Clock className="size-3.5" />}
                    <span>Draft</span>
                </span>
            );
    }
}

export interface ItemDecisionBadgeProps {
    decision?: CustomerDecision | (string & {}) | null;
    isIncluded?: boolean;
    className?: string;
}

export function ItemDecisionBadge({
    decision,
    isIncluded = true,
    className,
}: Readonly<ItemDecisionBadgeProps>) {
    if (!isIncluded) {
        return (
            <span
                className={cn(
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-muted text-muted-foreground',
                    className
                )}
            >
                Excluded
            </span>
        );
    }

    if (decision === 'approved') {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50 shadow-2xs',
                    className
                )}
            >
                <Check className="size-3" />
                <span>Approved</span>
            </span>
        );
    }

    if (decision === 'rejected') {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50 shadow-2xs',
                    className
                )}
            >
                <X className="size-3" />
                <span>Rejected</span>
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20',
                className
            )}
        >
            <Clock className="size-3" />
            <span>Pending</span>
        </span>
    );
}
