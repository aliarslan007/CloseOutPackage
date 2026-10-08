import { Button } from '@/components/ui/button';
import { Check, Copy } from 'lucide-react';
import type { ReactNode } from 'react';

export type SiteAccessCopyTarget = 'scope_of_work' | 'site_access_notes';

interface SiteAccessBlockProps {
    icon: ReactNode;
    label: string;
    text: string;
    empty: string;
    copied: boolean;
    onCopy: () => void;
}

/** Read-only presentation of one captured value, with a copy affordance. */
export function SiteAccessBlock({
    icon,
    label,
    text,
    empty,
    copied,
    onCopy,
}: Readonly<SiteAccessBlockProps>) {
    return (
        <div className="space-y-2 rounded-xl border bg-card p-4 shadow-2xs">
            <div className="flex items-center justify-between gap-2">
                <h4 className="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    {icon}
                    {label}
                </h4>
                {text !== '' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onCopy}
                        className="h-7 cursor-pointer px-2 text-[11px] text-muted-foreground hover:text-foreground"
                        title={`Copy ${label.toLowerCase()}`}
                    >
                        {copied ? (
                            <Check className="mr-1 size-3 text-emerald-500" />
                        ) : (
                            <Copy className="mr-1 size-3" />
                        )}
                        {copied ? 'Copied' : 'Copy'}
                    </Button>
                )}
            </div>
            {text === '' ? (
                <p className="text-xs italic text-muted-foreground/70">{empty}</p>
            ) : (
                <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{text}</p>
            )}
        </div>
    );
}

export default SiteAccessBlock;
