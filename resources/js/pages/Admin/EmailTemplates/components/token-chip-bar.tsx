interface TokenChipBarProps {
    readonly tokens: ReadonlyArray<string>;
    readonly onInsert: (token: string) => void;
}

/**
 * Pure display component — renders the {{token}} chips available for the
 * selected email type. Insertion logic lives in the parent form.
 */
export function TokenChipBar({ tokens, onInsert }: Readonly<TokenChipBarProps>) {
    if (tokens.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1" aria-label="Available template tokens">
            {tokens.map((token) => (
                <button
                    key={token}
                    type="button"
                    onClick={() => onInsert(token)}
                    title={`Insert {{${token}}}`}
                    className="cursor-pointer rounded border border-border bg-muted/60 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    {`{{${token}}}`}
                </button>
            ))}
        </div>
    );
}
