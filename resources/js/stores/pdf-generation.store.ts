import { create } from 'zustand';

export type PdfKind = 'final' | 'partial';

export type PdfGenerationKey = `${string}:${PdfKind}`;

interface PendingGeneration {
    startedAt: number;
}

interface PdfGenerationState {
    pending: Partial<Record<PdfGenerationKey, PendingGeneration>>;
    markPending: (packageId: number | string, kind: PdfKind) => void;
    clearPending: (packageId: number | string, kind: PdfKind) => void;
    isPending: (packageId: number | string, kind: PdfKind) => boolean;
}

const keyFor = (packageId: number | string, kind: PdfKind): PdfGenerationKey => `${packageId}:${kind}`;

/**
 * Tracks which close-out packages have a queued PDF generation in flight.
 *
 * Module-level state (not component state) so the "waiting for PDF" signal
 * survives Inertia page swaps/remounts — the review page keeps polling for
 * the ready notification even after the generate request round-trips.
 */
export const usePdfGenerationStore = create<PdfGenerationState>()((set, get) => ({
    pending: {},
    markPending: (packageId, kind) =>
        set((state) => ({
            pending: {
                ...state.pending,
                [keyFor(packageId, kind)]: { startedAt: Date.now() },
            },
        })),
    clearPending: (packageId, kind) =>
        set((state) => {
            const next = { ...state.pending };
            delete next[keyFor(packageId, kind)];
            return { pending: next };
        }),
    isPending: (packageId, kind) => Boolean(get().pending[keyFor(packageId, kind)]),
}));

export default usePdfGenerationStore;
