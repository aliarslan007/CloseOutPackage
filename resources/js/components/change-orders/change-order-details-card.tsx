import * as React from 'react';
import type { ChangeOrder } from '@/components/change-orders/change-order-types';
import { formatDate, getFieldDateBadgeInfo, getInitials } from './change-order-format';
import { Calendar } from 'lucide-react';

export interface ChangeOrderDetailsCardProps {
    changeOrder: ChangeOrder;
}

/**
 * Cover note plus the assigned Verizon customer contacts.
 *
 * Supporting photos intentionally live in their own section further down the
 * page, and the cover note is height-capped, so this card keeps a predictable
 * height next to the activity history no matter how long the note runs.
 */
export const ChangeOrderDetailsCard = React.memo(function ChangeOrderDetailsCard({
    changeOrder,
}: Readonly<ChangeOrderDetailsCardProps>) {
    const recipients = changeOrder.recipients ?? [];
    const fieldDateBadge = getFieldDateBadgeInfo(changeOrder.change_order_date);

    return (
        <div className="h-full rounded-xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs space-y-4 text-xs">
            <div>
                <div className="flex items-start justify-between gap-4">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Cover Note / Description
                    </h3>
                    <div className="shrink-0 text-right">
                        <h4 className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            Change Order ID
                        </h4>
                        <p className="mt-0.5 font-mono font-semibold text-foreground">
                            CO-{changeOrder.id.toString().padStart(4, '0')}
                        </p>
                    </div>
                </div>

                {changeOrder.change_order_date && (
                    <div className="mt-2.5 flex items-center gap-2 p-2 rounded-lg bg-muted/40 border border-border/60">
                        <Calendar className="size-3.5 text-primary shrink-0" />
                        <span className="font-semibold text-muted-foreground text-[11px]">Date of Occurrence:</span>
                        <span className="font-bold text-foreground text-xs">{formatDate(changeOrder.change_order_date)}</span>
                        {fieldDateBadge.variant === 'future' && (
                            <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                {fieldDateBadge.label}
                            </span>
                        )}
                        {fieldDateBadge.variant === 'today' && (
                            <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                {fieldDateBadge.label}
                            </span>
                        )}
                        {fieldDateBadge.variant === 'past' && (
                            <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-medium bg-muted text-muted-foreground border border-border">
                                {fieldDateBadge.label}
                            </span>
                        )}
                    </div>
                )}

                {/* Capped and scrollable: a cover note can run to 5000 characters,
                    which would otherwise stretch this card (and the activity
                    history beside it) far past the viewport. Short notes are
                    unaffected — overflow only kicks in past the cap. */}
                <div className="mt-2 max-h-80 overflow-y-auto p-3.5 rounded-lg bg-muted/20 border border-border/70 text-foreground text-xs whitespace-pre-wrap break-words [overflow-wrap:anywhere] leading-relaxed">
                    {changeOrder.description ||
                        'No additional cover note was provided for this change order.'}
                </div>
            </div>

            <div className="border-t border-border/60 pt-4 space-y-2">
                <h4 className="font-bold uppercase tracking-wider text-[11px] text-muted-foreground">
                    Assigned Verizon Customers ({recipients.length})
                </h4>
                {recipients.length === 0 ? (
                    <p className="text-muted-foreground text-xs italic">
                        No Verizon customers assigned yet.
                    </p>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                        {recipients.map((rec) => {
                            const name = rec.user?.name ?? 'Customer';
                            const email = rec.user?.email ?? '';
                            const avatar = rec.user?.profile_photo_url;
                            const hasResponded = Boolean(rec.responded_at);

                            return (
                                <div
                                    key={rec.id}
                                    className="flex items-center justify-between p-3 rounded-lg border border-border bg-muted/20 text-xs"
                                >
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        {avatar ? (
                                            <img
                                                src={avatar}
                                                alt={name}
                                                loading="lazy"
                                                decoding="async"
                                                className="size-8 rounded-full object-cover border border-border shrink-0 aspect-square"
                                            />
                                        ) : (
                                            <div className="size-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 border border-border">
                                                {getInitials(name)}
                                            </div>
                                        )}
                                        <div className="flex flex-col min-w-0">
                                            <span className="font-bold text-foreground truncate">
                                                {name}
                                            </span>
                                            <span className="text-[11px] text-muted-foreground truncate">
                                                {email}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="shrink-0 ml-2">
                                        {hasResponded ? (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                Decided
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                Pending
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
});

export default ChangeOrderDetailsCard;
