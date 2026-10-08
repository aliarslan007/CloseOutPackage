import { CUSTOMER_EMAIL_TYPE_OPTIONS } from '@/components/customer-emails/customer-email-filters';
import { Send } from 'lucide-react';

/**
 * Scannable legend of the customer-facing email categories tracked in this
 * outbox, shown beside the page heading so a manager immediately understands
 * which emails appear here — and, once released, become visible in the
 * customer portal. Labels come from the shared type list, not a duplicate.
 */
export function CustomerEmailTypeLegend() {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-xs font-medium text-muted-foreground">Emails shown here:</span>
            {CUSTOMER_EMAIL_TYPE_OPTIONS.map((type) => (
                <span
                    key={type.value}
                    className="inline-flex items-center gap-1 rounded-full border border-border/70 bg-card px-2 py-0.5 text-[11px] font-medium text-foreground"
                >
                    <Send className="size-2.5 text-muted-foreground" />
                    {type.label}
                </span>
            ))}
        </div>
    );
}

export default CustomerEmailTypeLegend;
