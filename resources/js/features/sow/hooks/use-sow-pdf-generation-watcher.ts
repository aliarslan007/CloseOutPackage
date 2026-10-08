import type { SowPdfGenerationStatus } from '@/features/sow/types/sow.types';
import { router } from '@inertiajs/react';
import * as React from 'react';

const POLL_INTERVAL_MS = 3_500;
/**
 * Stop polling after 3 minutes so a dead worker cannot leave a tab re-fetching
 * forever; the page then just shows the real server-side status.
 * ponytail: plain interval poll — swap for broadcast/websockets if instant updates are ever needed.
 */
const MAX_WAIT_MS = 180_000;

/**
 * Keeps the SOW buttons out of the "stuck on Generating…" state.
 *
 * The PDF is rendered by a queued job, so the `report` prop the page holds is a
 * snapshot: it only learns `ready`/`failed` when it is re-fetched (the bell
 * notification does not refresh Inertia props). While the status is
 * `generating`, this re-fetches the `report` prop on an interval, which flips
 * Download / Retry automatically — no manual page refresh.
 *
 * No ready/failed callbacks on purpose: the bell notification and the button
 * labels already report those.
 */
export function useSowPdfGenerationWatcher(status: SowPdfGenerationStatus): void {
    const isGenerating = status === 'generating';

    React.useEffect(() => {
        if (!isGenerating) {
            return undefined;
        }

        let inFlight = false;

        // Partial reload — the job only touches `report`; checklist/can are unaffected.
        const reload = () => {
            if (inFlight) return;
            inFlight = true;
            router.reload({
                only: ['report'],
                onFinish: () => {
                    inFlight = false;
                },
            });
        };

        reload();
        const interval = globalThis.setInterval(reload, POLL_INTERVAL_MS);
        const timeout = globalThis.setTimeout(() => globalThis.clearInterval(interval), MAX_WAIT_MS);

        return () => {
            globalThis.clearInterval(interval);
            globalThis.clearTimeout(timeout);
        };
    }, [isGenerating]);
}
