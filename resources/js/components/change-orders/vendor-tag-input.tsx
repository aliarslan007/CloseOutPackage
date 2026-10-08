import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { X } from 'lucide-react';
import * as React from 'react';

export interface VendorTagInputProps {
    value: string[];
    onChange: (vendors: string[]) => void;
    /** Max vendors allowed; input disables at the cap. */
    limit: number;
    id?: string;
    placeholder?: string;
    className?: string;
}

/**
 * Chip input for the Vendor / Company field: type a name, press Enter (or
 * comma) to add it, X on a chip to remove. Server stays authoritative on the
 * cap via `items.*.vendor_name` max rule.
 */
export function VendorTagInput({
    value,
    onChange,
    limit,
    id,
    placeholder = 'e.g. Acme Tree Service',
    className,
}: Readonly<VendorTagInputProps>) {
    const [draft, setDraft] = React.useState('');
    const atCap = value.length >= limit;

    let inputPlaceholder = 'Add another…';
    if (value.length === 0) {
        inputPlaceholder = placeholder;
    } else if (atCap) {
        inputPlaceholder = '';
    }

    const addDraft = () => {
        const name = draft.trim();
        if (!name || atCap || value.includes(name)) {
            setDraft('');
            return;
        }
        onChange([...value, name]);
        setDraft('');
    };

    const removeAt = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addDraft();
        } else if (event.key === 'Backspace' && draft === '' && value.length > 0) {
            removeAt(value.length - 1);
        }
    };

    return (
        <div className={cn('space-y-1.5', className)}>
            <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-border bg-card px-2 py-1.5 focus-within:ring-2 focus-within:ring-primary/20">
                {value.map((vendor, index) => (
                    <span
                        key={`${vendor}-${index}`}
                        className="inline-flex max-w-full items-center gap-1 rounded-md bg-primary/10 px-1.5 py-0.5 text-[11px] font-semibold text-foreground"
                    >
                        <span className="truncate">{vendor}</span>
                        <button
                            type="button"
                            onClick={() => removeAt(index)}
                            aria-label={`Remove ${vendor}`}
                            className="text-muted-foreground hover:text-destructive cursor-pointer"
                        >
                            <X className="size-3" />
                        </button>
                    </span>
                ))}
                <Input
                    id={id}
                    type="text"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onBlur={addDraft}
                    placeholder={inputPlaceholder}
                    disabled={atCap}
                    maxLength={100}
                    className="h-6 min-w-24 flex-1 border-0 bg-transparent px-0 text-xs shadow-none focus-visible:ring-0 disabled:cursor-default"
                />
            </div>
            {atCap && (
                <p className="text-[10px] text-muted-foreground tabular-nums">
                    Max {limit} vendors
                </p>
            )}
        </div>
    );
}
