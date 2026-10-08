import { cn } from '@/lib/utils';

/**
 * Divider that closes the photo subcategory area and opens the customer
 * review section: one line, the section name punched into its center, and a
 * short plain-language hint underneath. When feedback is pending, the label
 * picks up the amber "waiting" tint so the handoff is impossible to miss.
 */
export function FeedbackSectionDivider({ highlight = false }: Readonly<{ highlight?: boolean }>) {
    return (
        <div className="my-4 flex flex-col items-center gap-1 px-1 text-center">
            <div className="flex w-full items-center gap-3" role="separator">
                <span className="h-px flex-1 bg-border" />
                <span
                    className={cn(
                        'shrink-0 text-xs font-bold tracking-wide',
                        highlight ? 'text-amber-700 dark:text-amber-300' : 'text-muted-foreground'
                    )}
                >
                    {highlight
                        ? 'New customer feedback waiting — see below'
                        : 'Customer Review & Feedback'}
                </span>
                <span className="h-px flex-1 bg-border" />
            </div>
            <p className="text-[11px] leading-none text-muted-foreground/80">
                Photo categories end here. The conversation with the customer is below.
            </p>
        </div>
    );
}
