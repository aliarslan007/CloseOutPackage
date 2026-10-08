import { PackageLockMeta } from '@/components/packages/package-lock-notice';
import { cn } from '@/lib/utils';
import { Clock3, Download, Eye } from 'lucide-react';
import { CustomerPackageItem } from '../types';
import { formatDate } from '../utils';

/** Workflow label + the single accent tone it maps to (status rule, status dot). */
const STATUS: Record<string, { label: string; tone: string }> = {
    approved: { label: 'Approved', tone: 'bg-emerald-600' },
    customer_review: { label: 'Review Requested', tone: 'bg-amber-500' },
    feedback_received: { label: 'Feedback Submitted', tone: 'bg-sky-600' },
    revision_in_progress: { label: 'Revision In Progress', tone: 'bg-violet-600' },
    submitted: { label: 'Pending Review', tone: 'bg-sky-600' },
    under_review: { label: 'In Review', tone: 'bg-sky-500' },
    draft: { label: 'Draft / In Progress', tone: 'bg-stone-400' },
    rejected: { label: 'Needs Rework', tone: 'bg-amber-600' },
};

function resolveStatus(status: string) {
    return (
        STATUS[status] ?? {
            label: status.replaceAll('_', ' '),
            tone: 'bg-stone-400',
        }
    );
}

/** First letters of a name, e.g. "Dana Whitfield" -> "DW" (avatar fallback). */
function getInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');
}

/**
 * The final PDF only exists once the manager has published it. Say which of the
 * two is missing, otherwise a package without the button just looks broken next to
 * one that has it. Kept to one short line each — the card is narrow.
 */
function FinalPdfNotice({ approved }: Readonly<{ approved: boolean }>) {
    return (
        <p className="flex items-center gap-1.5 truncate rounded-md border border-dashed border-border bg-muted/40 px-2 py-1.5 text-[11px] text-muted-foreground">
            <Clock3 className="size-3 shrink-0 opacity-70" />
            {approved ? 'Final PDF not published yet' : 'Final PDF available after approval'}
        </p>
    );
}

function renderPackageAction(pkg: CustomerPackageItem) {
    // The whole card opens the package, so there is no second "view" button — only
    // the final-PDF download, and an explicit reason whenever it is not available.
    if (pkg.status !== 'approved' || !pkg.latest_final_revision?.storage_path) {
        return <FinalPdfNotice approved={pkg.status === 'approved'} />;
    }

    const packageKey = pkg.hash_id || pkg.id;

    return (
        <a
            href={route('portal.packages.pdf', [packageKey, { type: 'final' }])}
            target="_blank"
            rel="noreferrer"
            className="relative z-10 inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-md border border-border bg-card text-[11px] font-semibold text-foreground transition-colors hover:border-foreground/30 hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
            <Download className="size-3.5 text-emerald-600 dark:text-emerald-400" />
            Download Final PDF
        </a>
    );
}

interface CustomerPackageItemCardProps {
    pkg: CustomerPackageItem;
    activePackageKey?: string | null;
    onSelect?: (pkg: CustomerPackageItem) => void;
}

/**
 * Record-style card: flat surface, hairline border, no accent bar down the left
 * edge, no radius pile-up and no drop shadow — the header's status dot and the
 * solid VIEWING chip carry status and selection instead. The title is the card's
 * only control (a stretched button) so the whole surface is keyboard-reachable
 * without nesting a link inside a button.
 *
 * A package row has no name of its own (`closeout_packages` has no title column),
 * so the heading falls back to the template name. Several revisions of one
 * template therefore share a heading, and the revision carries the identity.
 */
export function CustomerPackageItemCard({
    pkg,
    activePackageKey,
    onSelect,
}: Readonly<CustomerPackageItemCardProps>) {
    const rev = `R${pkg.revision_major ?? 1}.${pkg.revision_minor ?? 0}`;
    const isActive = (pkg.hash_id ?? String(pkg.id)) === activePackageKey;
    const isLocked = Boolean(pkg.locked);
    // A package row has no name of its own (`closeout_packages` has no title
    // column), so identify it the same way the project details page does — by its
    // obfuscated route key. The template name goes below as context: several
    // revisions share one template, so it cannot be the heading.
    const name = `Package #${pkg.hash_id || pkg.id}`;
    const templateName = pkg.template?.name?.trim() || null;
    const { label, tone } = resolveStatus(pkg.status);

    return (
        <article
            className={cn(
                'relative flex h-full flex-col rounded-md border bg-card transition-colors',
                // Card is #fff on a #fdfbf7 page — too close to separate on its own,
                // so the border carries the edge (foreground/20 ≈ a real hairline,
                // /45 when selected).
                isActive ? 'border-foreground/45 bg-muted/50' : 'border-foreground/20',
                isLocked
                    ? 'cop-locked-stripes border-dashed border-amber-500/40 dark:border-amber-500/30'
                    : 'hover:border-foreground/40'
            )}
        >
            <div className="flex flex-1 flex-col gap-2.5 px-3.5 py-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="flex items-start gap-1.5 text-[13px] leading-snug font-semibold">
                            {isActive && (
                                <span className="mt-px inline-flex shrink-0 items-center gap-1 rounded-sm bg-success px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-success-foreground">
                                    <Eye className="size-2.5" />
                                    Viewing
                                </span>
                            )}
                            <span
                                className={cn(
                                    'min-w-0 truncate',
                                    isActive ? 'text-foreground' : 'text-foreground/90'
                                )}
                            >
                                {name}
                            </span>
                            <span className="mt-px shrink-0 rounded-sm bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold text-muted-foreground">
                                Rev {rev}
                            </span>
                        </h3>
                    </div>

                    <span className="mt-px inline-flex shrink-0 items-center gap-1.5 text-[11px] font-semibold text-muted-foreground">
                        <span aria-hidden className={cn('size-1.5 rounded-full', tone)} />
                        {label}
                    </span>
                </div>

                <dl className="grid grid-cols-[auto_1fr] items-baseline gap-x-3 border-t border-border/70 pt-2.5 text-[11px]">
                    {templateName && (
                        <>
                            <dt className="text-muted-foreground">Template</dt>
                            <dd title={templateName} className="truncate text-right text-foreground/80">
                                {templateName}
                            </dd>
                        </>
                    )}

                    <dt className="text-muted-foreground">
                        {pkg.status === 'approved' ? 'Approved' : 'Last Updated'}
                    </dt>
                    <dd className="text-right font-medium text-foreground">
                        {formatDate(pkg.approved_at || pkg.updated_at)}
                    </dd>

                    {pkg.creator?.name && (
                        <>
                            <dt className="text-muted-foreground">Prepared By</dt>
                            <dd className="flex min-w-0 items-center justify-end gap-1.5">
                                {pkg.creator.profile_photo_url ? (
                                    <img
                                        src={pkg.creator.profile_photo_url}
                                        alt=""
                                        loading="lazy"
                                        className="size-4 shrink-0 rounded-full border border-border object-cover"
                                    />
                                ) : (
                                    <span
                                        aria-hidden
                                        className="inline-flex size-4 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-[7px] font-bold leading-none text-muted-foreground"
                                    >
                                        {getInitials(pkg.creator.name)}
                                    </span>
                                )}
                                <span className="truncate font-medium text-foreground">
                                    {pkg.creator.name}
                                </span>
                            </dd>
                        </>
                    )}
                </dl>

                {isLocked ? (
                    <PackageLockMeta
                        locked_by={pkg.locked_by}
                        locked_at={pkg.locked_at}
                        className="flex w-full items-center gap-1.5 rounded-md border border-amber-500/30 bg-amber-500/5 px-2 py-1.5 text-[11px] font-medium text-amber-700 dark:text-amber-400"
                    />
                ) : (
                    renderPackageAction(pkg)
                )}
            </div>

            {!isLocked && onSelect && (
                <button
                    type="button"
                    onClick={() => onSelect(pkg)}
                    aria-current={isActive ? 'true' : undefined}
                    className="absolute inset-0 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                >
                    <span className="sr-only">View deliverables for {name}</span>
                </button>
            )}
        </article>
    );
}

export default CustomerPackageItemCard;