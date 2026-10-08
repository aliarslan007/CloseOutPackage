import { cn } from '@/lib/utils';

/**
 * Pulsing amber flag shown on a package row while the customer has submitted
 * feedback nobody has answered yet (backend `pending_feedback_count`, the same
 * SUBMITTED-round signal the package screen's divider nudges on).
 *
 * Rendered *inside* an existing cell - under the value it qualifies - so
 * listings never grow a new column or a horizontal scrollbar. Wording is
 * role-neutral ("awaiting reply", not "you must reply") because customers
 * browse the same package lists.
 */
export function PendingFeedbackFlag({
    count,
    label = 'Feedback awaiting reply',
    className,
}: Readonly<{ count?: number; label?: string; className?: string }>) {
    if (!count || count < 1) {
        return null;
    }

    return (
        <span
            title={
                count === 1
                    ? 'Customer feedback is waiting for a reply'
                    : `${count} are waiting for a reply`
            }
            className={cn(
                'inline-flex w-fit items-center gap-1.5 whitespace-nowrap rounded border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300',
                className
            )}
        >
            <span
                aria-hidden="true"
                className="size-1.5 shrink-0 rounded-full bg-amber-500 motion-safe:animate-pulse"
            />
            {label}
        </span>
    );
}
