import { useCallback, useEffect, useRef, useState } from 'react';
import { AlertTriangle, Loader2, ShieldCheck, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { mediaLibraryApi } from '../api/media-library.api';
import type {
    MediaPurgePreview,
    MediaPurgePreviewResponse,
    MediaPurgeProgress,
} from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';

export interface MediaPurgeDialogProps {
    isOpen: boolean;
    onClose: () => void;
    onCompleted: () => void;
}

type Phase = 'preview' | 'running' | 'done';

const POLL_INTERVAL_MS = 1500;

const TONE_CLASS = {
    default: 'text-foreground',
    destructive: 'text-destructive',
    success: 'text-success',
} as const;

function SummaryRow({
    label,
    value,
    tone = 'default',
    emphasis = false,
}: Readonly<{
    label: string;
    value: string;
    tone?: keyof typeof TONE_CLASS;
    emphasis?: boolean;
}>) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-1.5">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span
                className={`text-sm tabular-nums ${emphasis ? 'font-bold' : 'font-medium'} ${TONE_CLASS[tone]}`}
            >
                {value}
            </span>
        </div>
    );
}

function PurgeSummary({ preview }: Readonly<{ preview: MediaPurgePreview }>) {
    return (
        <div className="rounded-lg border border-border bg-muted/40 px-3 py-1">
            <SummaryRow
                label="Will be deleted"
                value={`${preview.deletable_assets} assets · ${formatFileSize(preview.deletable_bytes)}`}
                tone="destructive"
                emphasis
            />
            <SummaryRow
                label="Kept — referenced by SOW"
                value={`${preview.protected_assets} assets · ${formatFileSize(preview.protected_bytes)}`}
                tone="success"
            />
            {preview.in_progress_assets > 0 ? (
                <SummaryRow
                    label="Skipped — still uploading"
                    value={`${preview.in_progress_assets} assets`}
                />
            ) : null}
            {preview.orphan_chunk_folders > 0 ? (
                <SummaryRow
                    label="Abandoned upload folders"
                    value={`${preview.orphan_chunk_folders} cleaned up`}
                />
            ) : null}
            <div className="my-1 border-t border-border" />
            <SummaryRow
                label="Total in library"
                value={`${preview.total_assets} assets · ${formatFileSize(preview.total_bytes)}`}
            />
            <SummaryRow label="Contributors affected" value={`${preview.uploaders}`} />
        </div>
    );
}

function PurgeSafetyNote({ preview }: Readonly<{ preview: MediaPurgePreview }>) {
    return (
        <div className="flex items-start gap-2 rounded-lg border border-border bg-card p-3 text-xs text-muted-foreground">
            <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-success" />
            <div className="space-y-1">
                <p className="font-semibold text-foreground">Safe to leave alone</p>
                <p>
                    Closeout packages, site photos and change-order photos each keep their own copy of
                    the file, so they are unaffected.
                </p>
                <p>
                    <span className="font-medium text-foreground">
                        {preview.protected_reasons.annotated_images} annotated image(s)
                    </span>{' '}
                    and{' '}
                    <span className="font-medium text-foreground">
                        {preview.protected_reasons.report_covers} SOW report cover(s)
                    </span>{' '}
                    are excluded automatically.
                </p>
            </div>
        </div>
    );
}

function PurgeConfirmField({
    phrase,
    typed,
    disabled,
    onTypedChange,
}: Readonly<{
    phrase: string;
    typed: string;
    disabled: boolean;
    onTypedChange: (value: string) => void;
}>) {
    return (
        <div className="space-y-1.5">
            <label htmlFor="media-purge-confirm" className="text-sm font-medium text-foreground">
                Type <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{phrase}</code>{' '}
                to confirm
            </label>
            <input
                id="media-purge-confirm"
                type="text"
                value={typed}
                autoComplete="off"
                spellCheck={false}
                disabled={disabled}
                onChange={(e) => onTypedChange(e.target.value)}
                placeholder={phrase}
                className="h-10 w-full rounded-lg border border-border bg-card px-3 font-mono text-sm text-foreground transition-colors placeholder:text-muted-foreground/60 hover:border-foreground/20 focus:border-destructive focus:outline-none focus:ring-2 focus:ring-destructive/20 disabled:opacity-50"
            />
            {disabled ? (
                <p className="text-xs text-muted-foreground">
                    Nothing to delete — every asset is protected by a SOW reference or still uploading.
                </p>
            ) : null}
        </div>
    );
}

function PurgeRunningPanel({
    progress,
    expected,
}: Readonly<{ progress: MediaPurgeProgress; expected: number }>) {
    const percent = expected > 0 ? Math.min(100, Math.round((progress.processed / expected) * 100)) : 100;

    return (
        <div className="space-y-2 rounded-lg border border-border bg-muted/40 p-3">
            <div className="flex items-baseline justify-between gap-2 text-sm">
                <span className="font-medium text-foreground">Deleting…</span>
                <span className="tabular-nums text-muted-foreground">
                    {progress.processed} / {expected}
                </span>
            </div>
            <Progress value={percent} />
            <p className="text-xs text-muted-foreground">
                {progress.deleted} deleted · {formatFileSize(progress.deleted_bytes)} ·{' '}
                {progress.protected_skipped} protected · {progress.in_progress_skipped} skipped
                {progress.failed > 0 ? ` · ${progress.failed} failed` : ''}
            </p>
            <p className="text-xs text-muted-foreground">You can close this — the purge keeps running.</p>
        </div>
    );
}

function PurgeDonePanel({ progress }: Readonly<{ progress: MediaPurgeProgress }>) {
    return (
        <div className="space-y-2 rounded-lg border border-success/30 bg-success/10 p-3 text-sm">
            <p className="font-semibold text-foreground">Purge complete</p>
            <p className="text-muted-foreground">
                {progress.deleted} assets deleted · {formatFileSize(progress.deleted_bytes)} reclaimed ·{' '}
                {progress.protected_skipped} protected · {progress.in_progress_skipped} skipped
                {progress.orphan_chunk_folders_removed > 0
                    ? ` · ${progress.orphan_chunk_folders_removed} abandoned folders removed`
                    : ''}
            </p>
            {progress.failed > 0 ? (
                <p className="text-xs text-destructive">
                    {progress.failed} asset(s) could not be removed and are listed in the application
                    log.
                </p>
            ) : null}
        </div>
    );
}

/** What a failed start means, resolved outside the component to keep it readable. */
type StartFailure =
    | { kind: 'stale'; preview: MediaPurgePreview; message: string }
    | { kind: 'invalidPhrase'; message: string }
    | { kind: 'unavailable'; message: string };

function classifyStartFailure(caught: unknown): StartFailure {
    const response = (caught as { response?: { status?: number; data?: Record<string, unknown> } })
        ?.response;
    const data = response?.data ?? {};

    // 409 carries a fresh preview: the admin must re-confirm the new numbers.
    if (response?.status === 409 && data.preview) {
        return {
            kind: 'stale',
            preview: data.preview as MediaPurgePreview,
            message:
                (data.message as string) ?? 'The library changed. Review the new numbers and confirm again.',
        };
    }

    if (response?.status === 422) {
        return { kind: 'invalidPhrase', message: 'Type the confirmation phrase exactly as shown.' };
    }

    return { kind: 'unavailable', message: 'Could not start the purge. Please try again.' };
}

interface PurgeDialogFooterProps {
    isDone: boolean;
    isRunning: boolean;
    isBusy: boolean;
    canStart: boolean;
    onStart: () => void;
    onClose: () => void;
}

function PurgeDialogFooter({
    isDone,
    isRunning,
    isBusy,
    canStart,
    onStart,
    onClose,
}: Readonly<PurgeDialogFooterProps>) {
    if (isDone) {
        return (
            <DialogFooter>
                <Button type="button" onClick={onClose}>
                    Close
                </Button>
            </DialogFooter>
        );
    }

    return (
        <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={isRunning}>
                {isRunning ? 'Run in background' : 'Cancel'}
            </Button>
            {isRunning ? null : (
                <Button
                    type="button"
                    variant="destructive"
                    onClick={onStart}
                    disabled={isBusy || !canStart}
                >
                    {isBusy ? (
                        <>
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Starting…
                        </>
                    ) : (
                        <>
                            <Trash2 className="h-4 w-4" />
                            Delete everything
                        </>
                    )}
                </Button>
            )}
        </DialogFooter>
    );
}

interface PurgeDialogBodyProps {
    preview: MediaPurgePreview | null;
    phrase: string;
    typed: string;
    progress: MediaPurgeProgress | null;
    isBusy: boolean;
    isRunning: boolean;
    isDone: boolean;
    nothingToDelete: boolean;
    isConfirmStep: boolean;
    canStart: boolean;
    error: string | null;
    onTypedChange: (value: string) => void;
    onStart: () => void;
    onClose: () => void;
}

function PurgeDialogBody({
    preview,
    phrase,
    typed,
    progress,
    isBusy,
    isRunning,
    isDone,
    nothingToDelete,
    isConfirmStep,
    canStart,
    error,
    onTypedChange,
    onStart,
    onClose,
}: Readonly<PurgeDialogBodyProps>) {
    const showSpinner = isBusy && preview === null;

    return (
        <>
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2 text-destructive">
                    <Trash2 className="h-5 w-5" />
                    Delete all Media Library assets
                </DialogTitle>
                <DialogDescription>
                    Removes every asset in the Media Library for all users, including the stored files
                    and generated thumbnails. This cannot be undone.
                </DialogDescription>
            </DialogHeader>

            {showSpinner ? (
                <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Calculating what would be deleted…
                </div>
            ) : null}

            {error ? (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                >
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{error}</span>
                </div>
            ) : null}

            {preview ? (
                <div className="space-y-4">
                    <PurgeSummary preview={preview} />
                    <PurgeSafetyNote preview={preview} />

                    {isConfirmStep ? (
                        <PurgeConfirmField
                            phrase={phrase}
                            typed={typed}
                            disabled={nothingToDelete}
                            onTypedChange={onTypedChange}
                        />
                    ) : null}

                    {isRunning && progress ? (
                        <PurgeRunningPanel
                            progress={progress}
                            expected={progress.expected_total || preview.total_assets}
                        />
                    ) : null}

                    {isDone && progress ? <PurgeDonePanel progress={progress} /> : null}
                </div>
            ) : null}

            <PurgeDialogFooter
                isDone={isDone}
                isRunning={isRunning}
                isBusy={isBusy}
                canStart={canStart}
                onStart={onStart}
                onClose={onClose}
            />
        </>
    );
}

export function MediaPurgeDialog({ isOpen, onClose, onCompleted }: Readonly<MediaPurgeDialogProps>) {
    const [phase, setPhase] = useState<Phase>('preview');
    const [preview, setPreview] = useState<MediaPurgePreview | null>(null);
    const [phrase, setPhrase] = useState('');
    const [typed, setTyped] = useState('');
    const [progress, setProgress] = useState<MediaPurgeProgress | null>(null);
    const [isBusy, setIsBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const stopPolling = useCallback(() => {
        if (pollRef.current !== null) {
            clearTimeout(pollRef.current);
            pollRef.current = null;
        }
    }, []);

    const finish = useCallback(() => {
        setPhase('done');
        onCompleted();
        stopPolling();
    }, [onCompleted, stopPolling]);

    /** A purge another admin started resumes as progress, not a second launch. */
    const applyPreviewResponse = useCallback((response: MediaPurgePreviewResponse) => {
        setPreview(response.preview);
        setPhrase(response.confirmation_phrase);
        setTyped('');

        if (response.progress?.state === 'running') {
            setProgress(response.progress as MediaPurgeProgress);
            setPhase('running');

            return;
        }

        setPhase('preview');
    }, []);

    const loadPreview = useCallback(async () => {
        setIsBusy(true);
        setError(null);

        try {
            applyPreviewResponse(await mediaLibraryApi.getPurgePreview());
        } catch {
            setError('Could not load the purge preview. Check your connection and try again.');
        } finally {
            setIsBusy(false);
        }
    }, [applyPreviewResponse]);

    useEffect(() => {
        if (isOpen) {
            void loadPreview();
        } else {
            stopPolling();
            setPhase('preview');
            setPreview(null);
            setPhrase('');
            setTyped('');
            setProgress(null);
            setError(null);
        }

        return stopPolling;
    }, [isOpen, loadPreview, stopPolling]);

    const poll = useCallback(async () => {
        try {
            const { progress: next } = await mediaLibraryApi.getPurgeStatus();

            if (next?.state === 'completed') {
                setProgress(next as MediaPurgeProgress);
                finish();

                return;
            }

            if (next?.state === 'running') {
                setProgress(next as MediaPurgeProgress);
            }
        } catch {
            // A dropped poll is not evidence the purge failed. Keep trying; the
            // next tick reads the authoritative state from the server.
        }

        pollRef.current = setTimeout(() => void poll(), POLL_INTERVAL_MS);
    }, [finish]);

    const startPolling = useCallback(() => {
        stopPolling();
        pollRef.current = setTimeout(() => void poll(), POLL_INTERVAL_MS);
    }, [poll, stopPolling]);

    const handleStart = useCallback(async () => {
        if (preview === null) return;

        setIsBusy(true);
        setError(null);

        try {
            const response: MediaPurgePreviewResponse = await mediaLibraryApi.startPurge(
                preview.total_assets,
                phrase,
            );
            setPreview(response.preview ?? preview);
            setProgress((response.progress as MediaPurgeProgress) ?? null);
            setPhase('running');
            startPolling();
        } catch (caught) {
            const failure = classifyStartFailure(caught);

            if (failure.kind === 'stale') {
                setPreview(failure.preview);
                setTyped('');
                setPhase('preview');
            }

            setError(failure.message);
        } finally {
            setIsBusy(false);
        }
    }, [phrase, preview, startPolling]);

    const nothingToDelete = preview !== null && preview.deletable_assets === 0;
    const confirmationMatches = phrase.length > 0 && typed.trim() === phrase;
    const isRunning = phase === 'running';
    const isDone = phase === 'done';

    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => {
                if (open === false && isRunning === false) onClose();
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <PurgeDialogBody
                    preview={preview}
                    phrase={phrase}
                    typed={typed}
                    progress={progress}
                    isBusy={isBusy}
                    isRunning={isRunning}
                    isDone={isDone}
                    nothingToDelete={nothingToDelete}
                    isConfirmStep={!isRunning && !isDone}
                    canStart={confirmationMatches && !nothingToDelete}
                    error={error}
                    onTypedChange={setTyped}
                    onStart={handleStart}
                    onClose={onClose}
                />
            </DialogContent>
        </Dialog>
    );
}

export default MediaPurgeDialog;
