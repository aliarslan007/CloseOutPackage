import { ChangeOrderItemDescriptionCell } from '@/components/change-orders/change-order-item-description-cell';
import { ChangeOrderPhotoGrid } from '@/components/change-orders/change-order-photo-grid';
import { formatVendors } from '@/components/change-orders/change-order-format';
import { ChangeOrder, CustomerDecision } from '@/components/change-orders/change-order-types';
import { Button } from '@/components/ui/button';
import {
    EntityFeedbackSection,
    FeedbackSubmitBar,
    useEntityFeedback,
    type EntityFeedbackRoundItem,
} from '@/features/entity-feedback';
import { cn } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Calendar, Check, Mail, X } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { useFlashToasts } from '@/hooks/use-flash-toast';

export interface ChangeOrderTokenContact {
    name: string;
    email: string;
    role: string;
}

import brandMark from '@/assets/images/solucomp-latest-logo.webp';

export interface ChangeOrderTokenResponseProps {
    token: string;
    changeOrder?: ChangeOrder | null;
    isExpired?: boolean;
    reason?: 'invalid' | 'expired' | 'responded' | 'submitted' | null;
    contacts?: ChangeOrderTokenContact[];
    /** Server-computed login URL so it always matches the current environment. */
    loginUrl?: string | null;
    feedbackRound?: EntityFeedbackRoundItem | null;
    feedbackRoundHistory?: EntityFeedbackRoundItem[];
}

type UnavailableReason = 'invalid' | 'expired' | 'responded' | 'submitted';

const UNAVAILABLE_COPY: Record<
    UnavailableReason,
    { title: string; body: string; eyebrow: string }
> = {
    submitted: {
        title: 'Your decision has been sent.',
        eyebrow: 'Decision submitted',
        body: 'Thank you — your decision for this change order is recorded and has been sent to the project team. You can close this email now.',
    },
    responded: {
        title: 'Your decision is already in.',
        eyebrow: 'Decision recorded',
        body: 'Your response for this change order has been recorded, and this link has been revoked for security. A decision cannot be changed or resubmitted — if anything needs adjusting, an administrator can issue a new link.',
    },
    expired: {
        title: 'This decision link has expired.',
        eyebrow: 'Link expired',
        body: 'Every decision link stays available for a limited time after a change order is sent. This one has now passed its window and its access has been revoked.',
    },
    invalid: {
        title: 'This decision link is unavailable.',
        eyebrow: 'Link unavailable',
        body: 'This link could not be matched to an active change order request. It may have been revoked, replaced, or never issued.',
    },
};

const REASON_STATUS_TONE: Record<UnavailableReason, 'emerald' | 'amber'> = {
    submitted: 'emerald',
    responded: 'emerald',
    expired: 'amber',
    invalid: 'amber',
};

const REASON_FOOTER_NOTE: Record<UnavailableReason, string> = {
    submitted: 'You can close this email. Your decision link is no longer needed.',
    responded: 'Need to amend your response? An administrator can issue a new link for it.',
    expired: 'An administrator can issue a brand-new decision link for you.',
    invalid: 'An administrator can issue a brand-new decision link for you.',
};

const LINK_FACTS = [
    ['Single-use', 'One response per link — never two.'],
    ['Hashed tokens', 'SHA-256, raw tokens never stored.'],
    ['30-day window', 'Links expire after 30 days.'],
] as const;

function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'number' ? amount : Number.parseFloat(amount) || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(num);
}

function formatDate(isoString?: string | null): string {
    if (!isoString) return '—';
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return isoString;
    }
}

export function ChangeOrderTokenResponse({
    token,
    changeOrder,
    isExpired = false,
    reason = null,
    contacts = [],
    loginUrl = null,
    feedbackRound = null,
    feedbackRoundHistory = [],
}: Readonly<ChangeOrderTokenResponseProps>) {
    // This page renders outside the app layout, so it must surface flashes itself.
    useFlashToasts();

    const loginHref = loginUrl ?? route('login');

    const items = React.useMemo(() => {
        return (changeOrder?.items ?? []).filter((i) => i.is_included);
    }, [changeOrder?.items]);

    const [decisions, setDecisions] = React.useState<
        Record<number, { decision: CustomerDecision; note?: string }>
    >(() => {
        const init: Record<number, { decision: CustomerDecision; note?: string }> = {};
        items.forEach((item) => {
            if (item.id) {
                init[item.id] = {
                    decision: item.customer_decision ?? 'pending',
                    note: item.customer_decision_note ?? '',
                };
            }
        });
        return init;
    });

    const [isSubmitting, setIsSubmitting] = React.useState(false);

    /**
     * A reply continues the conversation on the image it was written about, so
     * the note carries that image's id and joins its thread.
     */
    const handleThreadReply = async (
        photoId: number | null,
        photoType: string | null,
        body: string,
        replacementFile?: File | null
    ) => {
        await feedback.saveAnnotationNote({
            entityType: 'change_order',
            entityId: changeOrder?.id ?? 0,
            originalImageUrl: null,
            body,
            sourcePhotoId: photoId,
            sourcePhotoType: photoType === null ? 'section' : photoType,
            replacementImageFile: replacementFile,
        });
    };

    const feedback = useEntityFeedback({
        entityType: 'change_order',
        entityId: changeOrder?.id ?? 0,
        round: feedbackRound,
        token,
        isCustomer: true,
    });

    if (isExpired || !changeOrder) {
        const copy = UNAVAILABLE_COPY[reason ?? 'invalid'];
        const tone = REASON_STATUS_TONE[reason ?? 'invalid'];

        return (
            <div className="h-screen overflow-y-auto overscroll-y-contain bg-background text-foreground flex flex-col pb-safe">
                <Head title={`${copy.title} · SoluComp`} />

                {/* Top brand bar */}
                <header className="border-b border-border/70">
                    <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6 lg:px-10">
                        <a
                            href={loginHref}
                            className="flex items-center gap-3 transition-opacity hover:opacity-80"
                        >
                            <img
                                src={brandMark}
                                alt="SoluComp"
                                className="h-9 w-auto shrink-0 object-contain"
                            />
                            <div className="flex flex-col leading-none gap-1">
                                <span className="font-bold text-[15px] font-heading text-foreground tracking-tight">
                                    COP
                                </span>
                                <span className="text-[12px] font-medium text-muted-foreground">
                                    Close Out Package
                                </span>
                            </div>
                        </a>
                        <span className="hidden items-center gap-2 text-[11px] font-medium uppercase tracking-[0.16em] text-muted-foreground sm:flex">
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                            Secure Customer Decision Link
                        </span>
                    </div>
                </header>

                <main className="flex flex-1 lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                    {/* Brand rail — enterprise statement, desktop only */}
                    <aside className="hidden flex-col justify-between gap-12 bg-[#241f1a] px-12 py-12 text-stone-300 lg:flex xl:px-16">
                        <div className="flex items-center gap-3">
                            <img
                                src={brandMark}
                                alt="SoluComp"
                                className="h-10 w-auto shrink-0 object-contain"
                            />
                            <div className="flex flex-col leading-none gap-1 text-white">
                                <span className="font-bold text-[16px] font-heading tracking-tight">
                                    COP
                                </span>
                                <span className="text-[12px] font-medium text-stone-400">
                                    Close Out Package
                                </span>
                            </div>
                        </div>

                        <div className="max-w-md space-y-5">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-red-500">
                                Change order decisions
                            </p>
                            <h2 className="font-heading text-3xl font-semibold leading-snug text-white">
                                Short-lived links.
                                <br />
                                Final decisions.
                            </h2>
                            <p className="text-sm leading-relaxed text-stone-400">
                                Every decision link is single-use, hashed end-to-end, and
                                time-boxed, so a change order response stays exactly as it was sent.
                            </p>
                        </div>

                        <div className="max-w-md">
                            {LINK_FACTS.map(([label, detail]) => (
                                <div
                                    key={label}
                                    className="flex items-baseline justify-between gap-6 border-t border-white/10 py-3 first:border-t-0"
                                >
                                    <span className="text-[11px] font-medium uppercase tracking-[0.16em] text-stone-500">
                                        {label}
                                    </span>
                                    <span className="text-right text-xs text-stone-400">
                                        {detail}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </aside>

                    {/* Notice column */}
                    <section className="flex items-center px-6 py-12 sm:px-10 lg:px-16 xl:px-20">
                        <div className="mx-auto w-full max-w-xl lg:mx-0">
                            <div className="flex items-center gap-2">
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        tone === 'emerald' ? 'bg-emerald-500' : 'bg-amber-500'
                                    )}
                                />
                                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                    {copy.eyebrow}
                                </span>
                            </div>

                            <h1 className="mt-5 font-heading text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                                {copy.title}
                            </h1>

                            <p className="mt-4 max-w-md text-sm leading-relaxed text-muted-foreground">
                                {copy.body}
                            </p>

                            {/* Security facts */}
                            <div className="mt-8 grid grid-cols-3 gap-px overflow-hidden rounded-lg border border-border/70 bg-border/70 shadow-2xs">
                                {LINK_FACTS.map(([label, detail]) => (
                                    <div key={label} className="bg-card px-4 py-3.5">
                                        <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                            {label}
                                        </p>
                                        <p
                                            className="mt-1 text-xs text-foreground/90"
                                            title={detail}
                                        >
                                            {detail}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            {/* Admin contact */}
                            {contacts.length > 0 && (
                                <div className="mt-8">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                        Contact an administrator
                                    </p>
                                    <ul className="mt-3 space-y-2.5">
                                        {contacts.map((contact) => (
                                            <li
                                                key={`${contact.role}-${contact.email}`}
                                                className="flex flex-wrap items-center justify-between gap-x-6 gap-y-1"
                                            >
                                                <span className="flex items-baseline gap-2 text-sm">
                                                    <span className="font-semibold text-foreground">
                                                        {contact.name}
                                                    </span>
                                                    <span className="text-[10px] uppercase tracking-[0.14em] text-muted-foreground">
                                                        {contact.role}
                                                    </span>
                                                </span>
                                                <a
                                                    href={`mailto:${contact.email}?subject=Change%20Order%20Decision%20Enquiry`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1.5 text-sm font-medium text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline"
                                                >
                                                    <Mail className="size-3.5" />
                                                    <span className="underline decoration-border/70">
                                                        {contact.email}
                                                    </span>
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Actions */}
                            <div className="mt-10 border-t border-border/70 pt-6">
                                <Button
                                    asChild
                                    className="h-11 w-full px-6 text-sm font-semibold sm:w-auto"
                                >
                                    <a href={loginHref}>
                                        <ArrowLeft className="size-4" />
                                        Return to Login Portal
                                    </a>
                                </Button>
                                <p className="mt-3 text-[11px] text-muted-foreground">
                                    {REASON_FOOTER_NOTE[reason ?? 'invalid']}
                                </p>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-border/50">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4 text-[11px] text-muted-foreground lg:px-10">
                        <span>
                            © {new Date().getFullYear()} SoluComp — Network Made Easy. All rights
                            reserved.
                        </span>
                        <span className="hidden sm:block">SoluComp Project Management</span>
                    </div>
                </footer>
            </div>
        );
    }

    const creatorName = changeOrder.creator?.name ?? 'Project Manager';
    const creatorEmail = changeOrder.creator?.email;
    const projectName = changeOrder.project?.name ?? 'Verizon Site';

    const setItemDecision = (itemId: number, decision: CustomerDecision) => {
        setDecisions((prev) => ({
            ...prev,
            [itemId]: {
                ...prev[itemId],
                decision,
            },
        }));
    };

    const handleApproveAll = () => {
        const updated: Record<number, { decision: CustomerDecision; note?: string }> = {};
        items.forEach((item) => {
            if (item.id) {
                updated[item.id] = {
                    ...decisions[item.id],
                    decision: 'approved',
                };
            }
        });
        setDecisions(updated);
        toast.success('Marked all items as Approved.');
    };

    const handleRejectAll = () => {
        const updated: Record<number, { decision: CustomerDecision; note?: string }> = {};
        items.forEach((item) => {
            if (item.id) {
                updated[item.id] = {
                    ...decisions[item.id],
                    decision: 'rejected',
                };
            }
        });
        setDecisions(updated);
        toast.info('Marked all items as Rejected.');
    };

    const hasPending = Object.values(decisions).some((d) => d.decision === 'pending');

    const handleSubmit = () => {
        if (hasPending) {
            toast.error('Please make a decision (Approve or Reject) on all line items.');
            return;
        }

        setIsSubmitting(true);
        const payload = {
            decisions: Object.entries(decisions).map(([itemId, val]) => ({
                item_id: Number.parseInt(itemId, 10),
                decision: val.decision,
                note: val.note || null,
            })),
        };

        router.post(route('change-orders.token.decide', { token }), payload, {
            onFinish: () => setIsSubmitting(false),
            onSuccess: () => {
                toast.success('Your decisions have been recorded successfully.');
            },
        });
    };

    return (
        <div className="h-screen overflow-y-auto overscroll-y-contain bg-muted/20 text-foreground flex flex-col justify-between p-4 sm:p-6 lg:p-8 pb-safe">
            <Head title={`Review Change Order: ${changeOrder.title}`} />

            <div className="mx-auto w-full max-w-4xl space-y-6">
                {/* Brand Header */}
                <div className="flex items-center justify-between pb-4 border-b border-border/80">
                    <a
                        href={loginHref}
                        className="flex items-center gap-3 transition-opacity hover:opacity-80"
                    >
                        <img
                            src={brandMark}
                            alt="SoluComp"
                            className="h-9 w-auto shrink-0 object-contain"
                        />
                        <div className="flex flex-col leading-none gap-1">
                            <span className="font-bold text-[15px] font-heading text-foreground tracking-tight">
                                COP
                            </span>
                            <span className="text-[12px] font-medium text-muted-foreground">
                                Close Out Package
                            </span>
                        </div>
                    </a>
                    <span className="hidden items-center gap-2 text-[11px] font-medium uppercase tracking-[0.16em] text-muted-foreground sm:flex">
                        <span className="size-1.5 rounded-full bg-emerald-500" />
                        Secure Customer Decision Link
                    </span>
                </div>

                {/* Main Header */}
                <div>
                    <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                        Change Order Response
                    </h1>
                    <p className="text-sm font-medium text-muted-foreground mt-0.5">
                        Project:{' '}
                        <span className="font-semibold text-foreground">{projectName}</span>
                    </p>
                </div>

                {/* Amber Warning Banner */}
                <div className="rounded-xl border border-amber-200 bg-amber-50/90 dark:border-amber-900/60 dark:bg-amber-950/30 p-4 text-xs text-amber-900 dark:text-amber-200 flex items-center gap-3 shadow-2xs">
                    <AlertCircle className="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span className="font-medium">
                        Please review the items below and provide your decision.
                    </span>
                </div>

                {/* Metadata Header Card */}
                <div className="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 text-xs">
                        <div>
                            <span className="font-semibold text-muted-foreground block text-[11px] uppercase tracking-wider">
                                Change Order:
                            </span>
                            <span className="font-bold text-foreground text-sm mt-1 block">
                                {changeOrder.title}
                            </span>
                        </div>

                        <div>
                            <span className="font-semibold text-muted-foreground block text-[11px] uppercase tracking-wider">
                                Submitted by:
                            </span>
                            <span className="font-bold text-foreground text-sm mt-1 block">
                                {creatorName}
                            </span>
                        </div>

                        <div>
                            <span className="font-semibold text-muted-foreground block text-[11px] uppercase tracking-wider">
                                Date of Occurrence:
                            </span>
                            <span className="font-bold text-foreground text-sm mt-1 flex items-center gap-1.5">
                                <Calendar className="size-3.5 text-primary shrink-0" />
                                <span>{formatDate(changeOrder.change_order_date)}</span>
                            </span>
                        </div>

                        <div>
                            <span className="font-semibold text-muted-foreground block text-[11px] uppercase tracking-wider">
                                Date Sent:
                            </span>
                            <span className="font-bold text-foreground text-sm mt-1 block">
                                {formatDate(changeOrder.sent_at || changeOrder.created_at)}
                            </span>
                        </div>

                        <div>
                            <span className="font-semibold text-muted-foreground block text-[11px] uppercase tracking-wider">
                                Total Amount:
                            </span>
                            <span className="font-bold text-foreground text-sm mt-1 block font-mono">
                                {formatCurrency(changeOrder.total_amount)}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Items Table Card */}
                <div className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr className="border-b border-border/70 bg-muted/40 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <th className="w-12 px-5 py-3.5 font-bold text-foreground">
                                        #
                                    </th>
                                    <th className="px-5 py-3.5 font-bold text-foreground">
                                        Description
                                    </th>
                                    <th className="px-5 py-3.5 font-bold text-foreground">
                                        Vendor / Company
                                    </th>
                                    <th className="px-5 py-3.5 font-bold text-foreground">
                                        Amount
                                    </th>
                                    <th className="px-5 py-3.5 font-bold text-foreground text-center w-60">
                                        Your Decision
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {items.map((item, idx) => {
                                    const currentDecision = item.id
                                        ? (decisions[item.id]?.decision ?? 'pending')
                                        : 'pending';
                                    const isAbsorbed = Boolean(item.cost_absorbed_by_solucomp);

                                    return (
                                        <tr
                                            key={item.id ?? idx}
                                            className={cn(
                                                'transition-colors',
                                                isAbsorbed
                                                    ? 'bg-emerald-500/[0.02] hover:bg-emerald-500/[0.06]'
                                                    : 'hover:bg-muted/30'
                                            )}
                                        >
                                            <td className="px-5 py-4 font-semibold text-muted-foreground">
                                                {idx + 1}
                                            </td>
                                            <td className="px-5 py-4 max-w-sm sm:max-w-md md:max-w-lg">
                                                <ChangeOrderItemDescriptionCell
                                                    item={item}
                                                    itemIndex={idx + 1}
                                                />
                                            </td>
                                            <td className="px-5 py-4 text-foreground whitespace-nowrap">
                                                {formatVendors(item.vendor_name) || '—'}
                                            </td>
                                            <td className="px-5 py-4 text-right font-mono font-bold text-foreground whitespace-nowrap">
                                                {isAbsorbed ? (
                                                    <div className="flex flex-col items-end">
                                                        <span className="line-through text-[11px] font-normal text-muted-foreground">
                                                            {formatCurrency(item.amount)}
                                                        </span>
                                                        <span className="font-bold text-emerald-600 dark:text-emerald-400">
                                                            $0.00
                                                        </span>
                                                    </div>
                                                ) : (
                                                    formatCurrency(item.amount)
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-center whitespace-nowrap">
                                                {typeof item.id === 'number' && (
                                                    <div className="flex items-center justify-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setItemDecision(
                                                                    item.id as number,
                                                                    'approved'
                                                                )
                                                            }
                                                            className={cn(
                                                                'px-4 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs',
                                                                currentDecision === 'approved'
                                                                    ? 'bg-emerald-600 text-white ring-2 ring-emerald-600/30'
                                                                    : 'border border-emerald-500/40 text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20'
                                                            )}
                                                        >
                                                            <Check className="size-3.5 stroke-[2.5]" />
                                                            <span>Approve</span>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setItemDecision(
                                                                    item.id as number,
                                                                    'rejected'
                                                                )
                                                            }
                                                            className={cn(
                                                                'px-4 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs',
                                                                currentDecision === 'rejected'
                                                                    ? 'bg-rose-600 text-white ring-2 ring-rose-600/30'
                                                                    : 'border border-rose-500/40 text-rose-700 dark:text-rose-400 bg-rose-500/10 hover:bg-rose-500/20'
                                                            )}
                                                        >
                                                            <X className="size-3.5 stroke-[2.5]" />
                                                            <span>Reject</span>
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between gap-4 border-t border-border/80 bg-muted/20 px-5 py-4 flex-wrap">
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleRejectAll}
                                className="rounded-xl text-xs font-semibold hover:bg-rose-500/10 hover:text-rose-700 hover:border-rose-300"
                            >
                                Reject All
                            </Button>
                        </div>

                        <div className="flex items-center gap-4">
                            <span className="text-xs text-muted-foreground">
                                Total:{' '}
                                <span className="text-sm font-bold text-foreground font-mono">
                                    {formatCurrency(changeOrder.total_amount)}
                                </span>
                            </span>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleApproveAll}
                                className="rounded-xl text-xs font-semibold hover:bg-emerald-500/10 hover:text-emerald-700 hover:border-emerald-300"
                            >
                                Approve All
                            </Button>

                            <Button
                                type="button"
                                onClick={handleSubmit}
                                disabled={isSubmitting}
                                className="rounded-xl text-xs font-bold px-6 bg-foreground text-background hover:bg-foreground/90 shadow-xs"
                            >
                                {isSubmitting ? 'Submitting...' : 'Submit Decisions'}
                            </Button>
                        </div>
                    </div>
                </div>

                {(changeOrder?.photos?.length ?? 0) > 0 && (
                    <div className="rounded-xl border border-border/80 bg-card shadow-xs p-4 sm:p-5 space-y-3">
                        <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            Photos ({changeOrder?.photos?.length ?? 0})
                        </h3>
                        {/* No onAnnotate: the emailed decision link is sign-off only.
                            Raising a query, replacement or annotation needs an
                            authenticated portal session, so the hover "Mark /
                            Annotate" button is deliberately absent here. */}
                        <ChangeOrderPhotoGrid photos={changeOrder?.photos ?? []} token={token} />
                    </div>
                )}

                <EntityFeedbackSection
                    round={feedback.activeRound}
                    history={feedbackRoundHistory}
                    isCustomer
                    onDeleteNote={feedback.deleteNote}
                    onDeleteRound={feedback.deleteRound}
                    onEditNote={feedback.editNote}
                    onAcceptRound={feedback.acceptRound}
                    isAcknowledged={feedback.isAcknowledged}
                    onReply={handleThreadReply}
                    canReply={Boolean(changeOrder)}
                />

                {/* Footer Help Contact */}
                <div className="text-center text-xs text-muted-foreground pt-4">
                    Need help? Contact{' '}
                    {creatorEmail ? (
                        <a
                            href={`mailto:${creatorEmail}`}
                            className="font-bold text-foreground hover:underline"
                        >
                            {creatorName}
                        </a>
                    ) : (
                        <span className="font-bold text-foreground">{creatorName}</span>
                    )}
                </div>
            </div>

            <FeedbackSubmitBar
                noteCount={feedback.hasOpenRound ? feedback.notes.length : 0}
                isSubmitting={feedback.isSubmitting}
                onSubmit={feedback.submitRound}
            />
        </div>
    );
}

export default ChangeOrderTokenResponse;
