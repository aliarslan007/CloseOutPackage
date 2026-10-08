import { sowImagesApi } from '../api/sow-images.api';
import type { CompositeStatus } from '../types/sow.types';
import * as React from 'react';

const POLL_MS = 3_000; // §9.14 — poll every 3s while anything is processing

/**
 * Polls the batch statuses endpoint while any image is PENDING/PROCESSING.
 * Returns a status map keyed by image id and a `settled` flag. When all
 * images settle, the caller typically refreshes server props once.
 */
export function useCompositeStatuses(reportId: number, imageIds: number[]) {
    const [statuses, setStatuses] = React.useState<Record<number, CompositeStatus>>({});
    const [settled, setSettled] = React.useState(false);

    React.useEffect(() => {
        if (imageIds.length === 0) return;

        let cancelled = false;

        const poll = async (): Promise<boolean> => {
            try {
                const rows = await sowImagesApi.compositeStatuses(reportId);
                if (cancelled) return false;

                const map: Record<number, CompositeStatus> = {};
                for (const row of rows) {
                    map[row.id] = row.composite_status;
                }
                setStatuses(map);

                const active = rows.some(
                    (row) =>
                        row.composite_status === 'pending' || row.composite_status === 'processing'
                );
                setSettled(!active);
                return active;
            } catch {
                if (!cancelled) setSettled(true); // endpoint unavailable — stop polling
                return false;
            }
        };

        const pollLoop = async (): Promise<void> => {
            let active = await poll();
            while (active && !cancelled) {
                await new Promise((resolve) => setTimeout(resolve, POLL_MS));
                if (cancelled) return;
                active = await poll();
            }
        };

        void pollLoop();

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reportId, imageIds.join(',')]);

    return { statuses, settled };
}
