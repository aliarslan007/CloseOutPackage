import * as React from 'react';
import type { ChangeOrder } from '@/components/change-orders/change-order-types';
import { formatDate, formatTime, getFieldDateBadgeInfo, getInitials } from './change-order-format';
import { Building2, Calendar, Clock, Users } from 'lucide-react';

export interface ChangeOrderSummaryCardsProps {
    project: { name: string };
    changeOrder: ChangeOrder;
    creatorName: string;
    creatorAvatar?: string | null;
    isDraft: boolean;
}

/**
 * Metadata tiles pinned to the top of the change-order detail page.
 */
export const ChangeOrderSummaryCards = React.memo(function ChangeOrderSummaryCards({
    project,
    changeOrder,
    creatorName,
    creatorAvatar,
    isDraft,
}: Readonly<ChangeOrderSummaryCardsProps>) {
    const recipients = changeOrder.recipients ?? [];

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
            {/* Card 1: Project */}
            <div className="rounded-xl border border-border/80 bg-card p-3.5 flex items-center gap-3 shadow-2xs min-w-0">
                <div className="size-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Building2 className="size-4.5" />
                </div>
                <div className="flex flex-col min-w-0 flex-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground truncate">
                        Project
                    </span>
                    <span
                        className="text-xs font-bold text-foreground truncate mt-0.5"
                        title={project.name}
                    >
                        {project.name}
                    </span>
                </div>
            </div>

            {/* Card 2: Occurrence Date */}
            <div className="rounded-xl border border-border/80 bg-card p-3.5 flex items-center gap-3 shadow-2xs min-w-0">
                <div className="size-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Calendar className="size-4.5" />
                </div>
                <div className="flex flex-col min-w-0 flex-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground truncate">
                        Occurrence Date
                    </span>
                    <span className="text-xs font-bold text-foreground truncate mt-0.5">
                        {formatDate(changeOrder.change_order_date)}
                    </span>
                    {(() => {
                        const badge = getFieldDateBadgeInfo(changeOrder.change_order_date);
                        if (badge.variant === 'future') {
                            return (
                                <span className="text-[10px] font-semibold text-sky-600 dark:text-sky-400 truncate">
                                    Scheduled (Upcoming)
                                </span>
                            );
                        }
                        if (badge.variant === 'today') {
                            return (
                                <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 truncate">
                                    Occurred Today
                                </span>
                            );
                        }
                        if (badge.variant === 'past') {
                            return (
                                <span className="text-[10px] text-muted-foreground truncate">
                                    Occurred on Site
                                </span>
                            );
                        }
                        return (
                            <span className="text-[10px] text-muted-foreground italic truncate">
                                Unspecified
                            </span>
                        );
                    })()}
                </div>
            </div>

            {/* Card 3: Created By */}
            <div className="rounded-xl border border-border/80 bg-card p-3.5 flex items-center gap-3 shadow-2xs min-w-0">
                {creatorAvatar ? (
                    <img
                        src={creatorAvatar}
                        alt={creatorName}
                        loading="lazy"
                        decoding="async"
                        className="size-9 rounded-full object-cover border border-border shrink-0 aspect-square"
                    />
                ) : (
                    <div className="size-9 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 border border-border">
                        {getInitials(creatorName)}
                    </div>
                )}
                <div className="flex flex-col min-w-0 flex-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground truncate">
                        Created By
                    </span>
                    <span
                        className="text-xs font-bold text-foreground truncate mt-0.5"
                        title={creatorName}
                    >
                        {creatorName}
                    </span>
                    <span className="text-[10px] text-muted-foreground truncate">
                        {formatDate(changeOrder.created_at)}
                    </span>
                </div>
            </div>

            {/* Card 4: Status / Sent At */}
            <div className="rounded-xl border border-border/80 bg-card p-3.5 flex items-center gap-3 shadow-2xs min-w-0">
                <div className="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground shrink-0">
                    {isDraft ? (
                        <Clock className="size-4.5 text-amber-500" />
                    ) : (
                        <Calendar className="size-4.5" />
                    )}
                </div>
                <div className="flex flex-col min-w-0 flex-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground truncate">
                        {isDraft ? 'Status' : 'Sent Date'}
                    </span>
                    {isDraft ? (
                        <span className="text-xs font-bold text-amber-600 dark:text-amber-400 mt-0.5 truncate flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-amber-500 shrink-0" />
                            <span>Draft (Unsent)</span>
                        </span>
                    ) : (
                        <>
                            <span className="text-xs font-bold text-foreground truncate mt-0.5">
                                {formatDate(changeOrder.sent_at)}
                            </span>
                            <span className="text-[10px] text-muted-foreground truncate">
                                {formatTime(changeOrder.sent_at)}
                            </span>
                        </>
                    )}
                </div>
            </div>

            {/* Card 5: Verizon Customers */}
            <div className="rounded-xl border border-border/80 bg-card p-3.5 flex items-center gap-3 shadow-2xs min-w-0">
                <div className="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground shrink-0">
                    <Users className="size-4.5" />
                </div>
                <div className="flex flex-col min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-1">
                        <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground truncate">
                            Verizon Customers
                        </span>
                        {recipients.length > 0 && (
                            <span className="text-[10px] font-bold text-primary px-1.5 py-0.2 rounded bg-primary/10 shrink-0">
                                {recipients.length}
                            </span>
                        )}
                    </div>
                    {recipients.length === 0 ? (
                        <span className="text-xs text-muted-foreground italic truncate mt-0.5">
                            None selected
                        </span>
                    ) : (
                        <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                            {recipients.map((rec) => {
                                const name = rec.user?.name ?? 'Customer';
                                const avatar = rec.user?.profile_photo_url;
                                return (
                                    <div key={rec.id} className="flex items-center gap-1.5 min-w-0">
                                        {avatar ? (
                                            <img
                                                src={avatar}
                                                alt={name}
                                                loading="lazy"
                                                decoding="async"
                                                className="size-5 rounded-full object-cover border border-border shrink-0 aspect-square"
                                            />
                                        ) : (
                                            <div className="size-5 rounded-full bg-primary/10 text-primary font-bold text-[8px] flex items-center justify-center border border-border shrink-0 aspect-square">
                                                {getInitials(name)}
                                            </div>
                                        )}
                                        <span
                                            className="text-xs font-bold text-foreground truncate max-w-[120px]"
                                            title={name}
                                        >
                                            {name}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
});

export default ChangeOrderSummaryCards;
