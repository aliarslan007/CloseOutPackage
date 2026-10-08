import { ChangeOrder } from '@/components/change-orders/change-order-types';
import { AlertCircle, AlertTriangle, Clock, Send } from 'lucide-react';
import * as React from 'react';

export interface CategorizedOrders {
    unassignedOrders: ChangeOrder[];
    validOrders: ChangeOrder[];
    alreadySentOrders: ChangeOrder[];
    draftOrders: ChangeOrder[];
}

export function hasCustomerRecipient(order: ChangeOrder): boolean {
    const hasRecipientsArray = Boolean(order.recipients && order.recipients.length > 0);
    const hasRecipientsCount = typeof order.recipients_count === 'number' && order.recipients_count > 0;
    return hasRecipientsArray || hasRecipientsCount;
}

export function isAlreadySent(order: ChangeOrder): boolean {
    return order.status === 'sent_to_customer' || order.status === 'customer_action_required' || Boolean(order.sent_at);
}

export function getOrderEditUrl(order: ChangeOrder): string | null {
    const projectIdentifier = order.project?.hash_id || order.project?.id || order.project_id;
    if (!projectIdentifier) return null;
    return route('projects.change-orders.edit', [projectIdentifier, order.id]);
}

export function formatDateTime(isoString?: string | null): string {
    if (!isoString) return '';
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return isoString;
    }
}

export function categorizeOrders(orders: ChangeOrder[]): CategorizedOrders {
    const unassigned: ChangeOrder[] = [];
    const valid: ChangeOrder[] = [];
    const alreadySent: ChangeOrder[] = [];
    const drafts: ChangeOrder[] = [];

    for (const order of orders) {
        if (hasCustomerRecipient(order)) {
            valid.push(order);
            if (isAlreadySent(order)) {
                alreadySent.push(order);
            } else {
                drafts.push(order);
            }
        } else {
            unassigned.push(order);
        }
    }

    return {
        unassignedOrders: unassigned,
        validOrders: valid,
        alreadySentOrders: alreadySent,
        draftOrders: drafts,
    };
}

export function getDialogTitle(
    hasUnassigned: boolean,
    allUnassigned: boolean,
    hasAlreadySent: boolean,
    draftCount: number,
    alreadySentCount: number,
    validCount: number,
    selectedCount: number
): string {
    if (allUnassigned) {
        return 'Customer Contact Assignment Required';
    }
    if (hasUnassigned) {
        return 'Missing Customer Contacts';
    }
    if (hasAlreadySent) {
        if (draftCount === 0) {
            const suffix = alreadySentCount === 1 ? '' : 's';
            return `Resend ${alreadySentCount} Change Order${suffix}?`;
        }
        return `Send & Resend ${validCount} Change Orders?`;
    }
    const suffix = selectedCount === 1 ? '' : 's';
    return `Send ${selectedCount} Change Order${suffix} to Customer?`;
}

export function getDialogDescription(hasUnassigned: boolean, hasAlreadySent: boolean): string {
    if (hasUnassigned) {
        return 'Change orders cannot be sent without an assigned Verizon customer recipient.';
    }
    if (hasAlreadySent) {
        return 'Some selected change orders were already sent. You can resend them with fresh notification emails.';
    }
    return 'Assigned Verizon customer contacts will receive an email notification with a decision link.';
}

export function getDialogHeaderIcon(
    hasUnassigned: boolean,
    allUnassigned: boolean,
    hasAlreadySent: boolean
): React.ReactNode {
    if (allUnassigned) {
        return (
            <div className="size-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <AlertCircle className="size-4 text-destructive" />
            </div>
        );
    }
    if (hasUnassigned) {
        return (
            <div className="size-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <AlertTriangle className="size-4" />
            </div>
        );
    }
    if (hasAlreadySent) {
        return (
            <div className="size-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <Clock className="size-4" />
            </div>
        );
    }
    return (
        <div className="size-8 rounded-lg bg-primary/10 flex items-center justify-center">
            <Send className="size-4" />
        </div>
    );
}

export function getHeaderColorClass(hasUnassigned: boolean, hasAlreadySent: boolean): string {
    if (hasUnassigned) {
        return 'text-amber-600 dark:text-amber-500';
    }
    if (hasAlreadySent) {
        return 'text-amber-600 dark:text-amber-400';
    }
    return 'text-primary';
}

export function getConfirmButtonLabel(
    hasUnassigned: boolean,
    hasAlreadySent: boolean,
    draftCount: number,
    validCount: number
): string {
    if (hasUnassigned) {
        return `Exclude & Send Remaining (${validCount})`;
    }
    if (draftCount === 0 && hasAlreadySent) {
        return `Resend to Customer (${validCount})`;
    }
    if (hasAlreadySent) {
        return `Send & Resend (${validCount})`;
    }
    return `Send to Customer (${validCount})`;
}
