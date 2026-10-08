import { SmilePlus } from 'lucide-react';
import * as React from 'react';

/**
 * A curated set of the emojis people actually type in work chats - faces,
 * hands, hearts, a few objects. Written as escape pairs on purpose: raw
 * multi-byte emoji in source have been corrupted by editors before.
 * Deliberately a static list, not a picker library - emoji-mart is ~1 MB
 * of machinery for a 70-cell grid.
 */
const EMOJIS = [
    '\u{1F600}',
    '\u{1F603}',
    '\u{1F604}',
    '\u{1F601}',
    '\u{1F605}',
    '\u{1F602}',
    '\u{1F923}',
    '\u{1F60A}',
    '\u{1F607}',
    '\u{1F642}',
    '\u{1F643}',
    '\u{1F609}',
    '\u{1F60D}',
    '\u{1F618}',
    '\u{1F61C}',
    '\u{1F60B}',
    '\u{1F92A}',
    '\u{1F914}',
    '\u{1F928}',
    '\u{1F610}',
    '\u{1F971}',
    '\u{1F634}',
    '\u{1F924}',
    '\u{1F62A}',
    '\u{1F637}',
    '\u{1F912}',
    '\u{1F915}',
    '\u{1F922}',
    '\u{1F92E}',
    '\u{1F973}',
    '\u{1F60E}',
    '\u{1F913}',
    '\u{1F97A}',
    '\u{1F622}',
    '\u{1F62D}',
    '\u{1F624}',
    '\u{1F621}',
    '\u{1F92C}',
    '\u{1F92F}',
    '\u{1F631}',
    '\u{1F633}',
    '\u{1F644}',
    '\u{1F62C}',
    '\u{1F917}',
    '\u{1F92B}',
    '\u{1F92D}',
    '\u{1F972}',
    '\u{1F920}',
    '\u{1F44D}',
    '\u{1F44E}',
    '\u{1F44C}',
    '\u{1F44F}',
    '\u{1F64C}',
    '\u{1F64F}',
    '\u{1F4AA}',
    '\u{1F91D}',
    '\u{2764}',
    '\u{1F49B}',
    '\u{1F49A}',
    '\u{1F499}',
    '\u{1F49C}',
    '\u{1F4AF}',
    '\u{1F525}',
    '\u{1F389}',
    '\u{2705}',
    '\u{274C}',
    '\u{2B50}',
    '\u{26A1}',
    '\u{1F4F7}',
    '\u{1F527}',
    '\u{1F9F9}',
    '\u{1F4A1}',
];

export interface EmojiPickerProps {
    onPick: (emoji: string) => void;
    disabled?: boolean;
}

export function EmojiPicker({ onPick, disabled = false }: Readonly<EmojiPickerProps>) {
    const [open, setOpen] = React.useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                aria-label="Insert emoji"
                aria-expanded={open}
                disabled={disabled}
                onClick={() => setOpen((o) => !o)}
                className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
            >
                <SmilePlus className="size-[18px]" />
            </button>

            {open && (
                <>
                    {/* Transparent full-screen catch to close on outside click -
                        cheaper than a popover primitive with the same effect. */}
                    <button
                        type="button"
                        aria-hidden
                        tabIndex={-1}
                        onClick={() => setOpen(false)}
                        className="fixed inset-0 z-10 cursor-default"
                    />
                    <div
                        role="menu"
                        aria-label="Emoji"
                        className="absolute bottom-full left-0 z-20 mb-1 grid w-[17.5rem] grid-cols-8 gap-0.5 rounded-xl border border-border bg-card p-2 shadow-lg"
                    >
                        {EMOJIS.map((emoji) => (
                            <button
                                key={emoji}
                                type="button"
                                role="menuitem"
                                onClick={() => onPick(emoji)}
                                className="flex size-7 items-center justify-center rounded text-base leading-none transition-colors hover:bg-muted"
                            >
                                {emoji}
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
