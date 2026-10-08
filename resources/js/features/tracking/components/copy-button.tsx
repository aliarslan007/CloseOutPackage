import { cn } from '@/lib/utils';
import { Check, Copy } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface CopyButtonProps {
    readonly value: string;
    readonly label: string;
    readonly className?: string;
}

export function CopyButton({ value, label, className }: Readonly<CopyButtonProps>) {
    const [copied, setCopied] = React.useState(false);

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        e.preventDefault();

        if (typeof navigator !== 'undefined' && navigator.clipboard) {
            navigator.clipboard
                .writeText(value)
                .then(() => {
                    setCopied(true);
                    toast.success(`${label} copied to clipboard`);
                    setTimeout(() => setCopied(false), 2000);
                })
                .catch(() => {
                    toast.error('Failed to copy to clipboard');
                });
        }
    };

    return (
        <button
            type="button"
            onClick={handleCopy}
            title={`Copy ${label}`}
            aria-label={`Copy ${label}`}
            className={cn(
                'inline-flex size-6 shrink-0 items-center justify-center rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring active:scale-95',
                className
            )}
        >
            {copied ? (
                <Check className="size-3.5 text-emerald-600 dark:text-emerald-400" />
            ) : (
                <Copy className="size-3.5" />
            )}
        </button>
    );
}

export default CopyButton;
