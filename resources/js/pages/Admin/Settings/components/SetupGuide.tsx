import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';

interface SetupGuideProps {
    title: string;
    children: ReactNode;
}

/**
 * Inline chip for naming a button, menu, or page inside a guide step
 * (e.g. Settings, SMTP & API) — reads as documentation, not decoration.
 */
export function GuideRef({ children }: Readonly<{ children: ReactNode }>) {
    return (
        <code className="rounded bg-muted px-1 py-px font-mono text-[11px] font-medium text-foreground">
            {children}
        </code>
    );
}

/**
 * Collapsible step-by-step credential guide. Collapsed by default so the
 * form stays clean; expands in place when the admin needs it.
 */
export function SetupGuide({ title, children }: Readonly<SetupGuideProps>) {
    return (
        <details className="group rounded-lg border border-border/70 bg-card px-3.5 py-2.5 text-xs">
            <summary className="flex cursor-pointer list-none items-center gap-1.5 font-semibold text-foreground [&::-webkit-details-marker]:hidden">
                <ChevronDown className="size-3.5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                {title}
            </summary>
            <div className="pt-2.5 leading-relaxed text-muted-foreground">{children}</div>
        </details>
    );
}
