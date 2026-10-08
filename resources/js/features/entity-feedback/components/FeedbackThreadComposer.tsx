import { preparePhotoForUpload, rejectedPhotoMessage } from '@/services/compression.service';
import { cn } from '@/lib/utils';
import { Loader2, Mic, Paperclip, Send } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { PhotoActionOptions } from '../types/entity-feedback.types';
import { FeedbackAttachmentPreview } from './FeedbackAttachmentPreview';
import { EmojiPicker } from './EmojiPicker';

export interface FeedbackThreadComposerProps {
    targetLabel: string;
    maxLength: number;
    isStaff?: boolean;
    isSubmitting?: boolean;
    isPhotoTarget?: boolean;
    entityType?: string;
    onReply: (
        body: string,
        replacementFile?: File | null,
        options?: PhotoActionOptions
    ) => Promise<void> | void;
}

function formatSize(bytes: number): string {
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/** Quiet until 90% of the cap, amber near it, red once the message is full. */
function lengthTone(count: number, max: number): string {
    if (count >= max) return 'font-semibold text-destructive';
    if (count > max * 0.9) return 'text-amber-600 dark:text-amber-400';
    return 'text-muted-foreground';
}

const VOICE_UNAVAILABLE = 'Voice messages are not available yet.';

/**
 * WhatsApp-style reply bar: one rounded pill - pin (attach photo), emoji
 * picker, the input, and a mic that turns into send once there is something
 * to send. The mic is visible but deliberately a no-op with an explanation,
 * so nobody taps it twice wondering.
 */
export const FeedbackThreadComposer = React.memo(function FeedbackThreadComposer({
    targetLabel,
    maxLength,
    isStaff = false,
    isSubmitting = false,
    isPhotoTarget = true,
    entityType,
    onReply,
}: Readonly<FeedbackThreadComposerProps>) {
    const [text, setText] = React.useState('');
    const [file, setFile] = React.useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = React.useState<string | null>(null);
    const [replaceInChangeOrder, setReplaceInChangeOrder] = React.useState(false);
    const [addToChangeOrderPhotos, setAddToChangeOrderPhotos] = React.useState(false);
    const [isPreparing, setIsPreparing] = React.useState(false);
    const [isFocused, setIsFocused] = React.useState(false);
    const [refocusAfterSend, setRefocusAfterSend] = React.useState(false);
    const fileRef = React.useRef<HTMLInputElement>(null);
    const textRef = React.useRef<HTMLInputElement>(null);

    // Nobody waits on an "acknowledge" click to answer: the server marks the
    // round acknowledged the moment any staff reply lands, so the reply box
    // is only ever busy - never locked.
    const isLocked = isSubmitting || isPreparing;
    const hasContent = text.trim().length > 0 || file !== null;
    const canSend = !isLocked && hasContent;

    React.useEffect(() => {
        if (!file) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    // Sending disables the box for the round-trip, and a disabled input drops
    // focus - without this the reader has to click the bar again to write the
    // next message. Focus goes back the moment the box is live.
    React.useEffect(() => {
        if (!isLocked && refocusAfterSend) {
            textRef.current?.focus();
            setRefocusAfterSend(false);
        }
    }, [isLocked, refocusAfterSend]);

    const pickFile = async (raw: File | null) => {
        if (raw === null) {
            setFile(null);
            setReplaceInChangeOrder(false);
            setAddToChangeOrderPhotos(false);
            return;
        }

        setIsPreparing(true);
        try {
            const prepared = await preparePhotoForUpload(raw);
            if (!prepared.ok) {
                toast.error(rejectedPhotoMessage(raw, prepared.reason));
                setFile(null);
                if (fileRef.current) fileRef.current.value = '';
                return;
            }

            const saved = raw.size - prepared.file.size;
            setFile(prepared.file);
            if (saved > 0) {
                toast.success(
                    `Photo compressed: ${formatSize(raw.size)} → ${formatSize(prepared.file.size)}.`
                );
            }
        } finally {
            setIsPreparing(false);
        }
    };

    const insertEmoji = (emoji: string) => {
        const el = textRef.current;
        const start = el?.selectionStart ?? text.length;
        const end = el?.selectionEnd ?? start;
        setText((t) => t.slice(0, start) + emoji + t.slice(end));
        window.requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(start + emoji.length, start + emoji.length);
        });
    };

    const handleSend = async () => {
        if (!canSend) return;
        setRefocusAfterSend(true);
        await onReply(text.trim(), file, {
            replaceInChangeOrder,
            addToChangeOrderPhotos,
        });
        setText('');
        setFile(null);
        setReplaceInChangeOrder(false);
        setAddToChangeOrderPhotos(false);
        if (fileRef.current) fileRef.current.value = '';
    };

    return (
        <div className="space-y-3">
            {file !== null && (
                <FeedbackAttachmentPreview
                    fileName={file.name}
                    sizeLabel={formatSize(file.size)}
                    previewUrl={previewUrl}
                    isLocked={isLocked}
                    showChangeOrderOptions={isStaff && entityType === 'change_order'}
                    isPhotoTarget={isPhotoTarget}
                    replaceInChangeOrder={replaceInChangeOrder}
                    addToChangeOrderPhotos={addToChangeOrderPhotos}
                    onToggleReplace={setReplaceInChangeOrder}
                    onToggleAdd={setAddToChangeOrderPhotos}
                    onRemove={() => void pickFile(null)}
                />
            )}

            {isPreparing && (
                <p className="text-[11px] text-muted-foreground animate-pulse">Compressing…</p>
            )}

            <div className="flex items-center gap-1 rounded-full border-2 border-input bg-card px-2 py-1.5 transition-colors focus-within:border-primary">
                {isStaff && (
                    <>
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => void pickFile(e.target.files?.[0] ?? null)}
                            disabled={isLocked}
                        />
                        <button
                            type="button"
                            aria-label="Attach photo"
                            title="Attach photo"
                            onClick={() => fileRef.current?.click()}
                            disabled={isLocked}
                            className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                        >
                            <Paperclip className="size-[18px] -rotate-45" />
                        </button>
                    </>
                )}

                <EmojiPicker onPick={insertEmoji} disabled={isLocked} />

                <input
                    ref={textRef}
                    type="text"
                    value={text}
                    maxLength={maxLength}
                    disabled={isLocked}
                    onChange={(e) => setText(e.target.value)}
                    onFocus={() => setIsFocused(true)}
                    onBlur={() => setIsFocused(false)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            void handleSend();
                        }
                    }}
                    placeholder="Type a message…"
                    aria-label={`Reply about ${targetLabel}`}
                    className="!min-w-0 !flex-1 !border-0 !bg-transparent px-1 !text-sm !py-0 text-foreground placeholder:text-muted-foreground focus:!outline-none focus:ring-0 disabled:opacity-50"
                />

                {hasContent ? (
                    <button
                        type="button"
                        aria-label="Send message"
                        onClick={() => void handleSend()}
                        disabled={!canSend}
                        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-all hover:opacity-90 active:scale-95 disabled:opacity-40"
                    >
                        {isSubmitting ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Send className="size-4" />
                        )}
                    </button>
                ) : (
                    <button
                        type="button"
                        aria-label="Voice message"
                        title={VOICE_UNAVAILABLE}
                        onClick={() => toast.info(VOICE_UNAVAILABLE)}
                        className="flex size-8 shrink-0 cursor-not-allowed items-center justify-center rounded-full text-muted-foreground"
                    >
                        <Mic className="size-4" />
                    </button>
                )}
            </div>

            {/* Message-length guard: shows the cap the moment the box is in use
                so nobody writes a novel blind, and warns near the limit. */}
            {(isFocused || hasContent) && (
                <div className="flex justify-end px-1">
                    <span
                        className={cn(
                            'text-[11px] tabular-nums',
                            lengthTone(text.length, maxLength)
                        )}
                    >
                        {text.length} / {maxLength} characters
                    </span>
                </div>
            )}
        </div>
    );
});
