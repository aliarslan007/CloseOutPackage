import axios from 'axios';
import { PdfKind, usePdfGenerationStore } from '@/stores/pdf-generation.store';
import { useEffect, useRef, useState } from 'react';

interface PdfReadyNotificationItem {
    category?: string;
    package_id?: number | string | null;
    pdf_type?: string | null;
    createdAt?: string;
    generated_at?: string | null;
}

const POLL_INTERVAL_MS = 2500;
const MAX_WAIT_MS = 90_000;
/**
 * Tolerance for server/client clock skew so a notification created moments
 * before the polling session began is still treated as fresh.
 */
const CLOCK_SKEW_SLACK_MS = 30_000;

export interface PdfReadySignal {
    /** True once a fresh "PDF ready" notification for this package has landed. */
    ready: boolean;
    /** True once polling gave up after MAX_WAIT_MS without a signal. */
    expired: boolean;
    /** When the matched PDF was generated (ISO), so the UI can label the button. */
    generatedAt: string | null;
}

function isNotificationMatch(
    item: PdfReadyNotificationItem,
    packageId: number | string,
    pdfType: PdfKind,
    startedAt: number
): boolean {
    if (
        item.category !== 'pdf' ||
        item.pdf_type !== pdfType ||
        (item.package_id !== undefined && item.package_id !== null && String(item.package_id) !== String(packageId))
    ) {
        return false;
    }
    const created = new Date(item.createdAt ?? 0).getTime();
    return created >= startedAt - CLOCK_SKEW_SLACK_MS;
}

/**
 * Polls the notifications API while a PDF generation for the given package is
 * marked as pending and reports whether a fresh "PDF ready" notification has
 * landed.
 *
 * The pending signal lives in the pdf-generation store (not component state),
 * so polling survives Inertia page swaps — the Download button appears the
 * moment the queued PDF finishes, without a manual page refresh.
 */
export function usePdfReadySignal(packageId: number | string, pdfType: PdfKind): PdfReadySignal {
    const active = usePdfGenerationStore((state) => state.isPending(packageId, pdfType));
    const clearPending = usePdfGenerationStore((state) => state.clearPending);

    const [ready, setReady] = useState(false);
    const [expired, setExpired] = useState(false);
    const [generatedAt, setGeneratedAt] = useState<string | null>(null);
    const wasActiveRef = useRef(false);

    // Start a fresh cycle each time the pending flag turns on.
    useEffect(() => {
        if (active && !wasActiveRef.current) {
            setReady(false);
            setExpired(false);
            setGeneratedAt(null);
        }
        wasActiveRef.current = active;
    }, [active]);

    useEffect(() => {
        if (!active || ready || expired) {
            return;
        }

        const startedAt = Date.now();
        let cancelled = false;
        let interval: ReturnType<typeof setInterval> | undefined;
        let timeout: ReturnType<typeof setTimeout> | undefined;

        const stop = () => {
            if (interval !== undefined) {
                globalThis.clearInterval(interval);
            }
            if (timeout !== undefined) {
                globalThis.clearTimeout(timeout);
            }
        };

        const handleResponse = (items: PdfReadyNotificationItem[]) => {
            if (cancelled) {
                return;
            }
            const matched = items.find((item) => isNotificationMatch(item, packageId, pdfType, startedAt));
            if (matched) {
                stop();
                clearPending(packageId, pdfType);
                setGeneratedAt(matched.generated_at ?? matched.createdAt ?? null);
                setReady(true);
            }
        };

        const check = () => {
            axios
                .get(route('notifications.index'))
                .then((response) => {
                    const items = (response.data?.items ?? []) as PdfReadyNotificationItem[];
                    handleResponse(items);
                })
                .catch(() => {
                    // transient polling failure — keep the last known state
                });
        };

        check();
        interval = globalThis.setInterval(check, POLL_INTERVAL_MS);
        timeout = globalThis.setTimeout(() => {
            clearPending(packageId, pdfType);
            setExpired(true);
            stop();
        }, MAX_WAIT_MS);

        return () => {
            cancelled = true;
            stop();
        };
    }, [active, packageId, pdfType, ready, expired, clearPending]);

    return { ready, expired, generatedAt };
}
