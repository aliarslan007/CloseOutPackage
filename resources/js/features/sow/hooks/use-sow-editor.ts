import { sowImagesApi } from '../api/sow-images.api';
import { useSowEditorStore } from '../stores/sow-editor.store';
import { getCsrfToken } from '../utils/get-csrf-token';
import { parseLayer, serializeLayer } from '../utils/annotation-serializer';
import type { ApiAnnotationLayer } from '../utils/annotation-serializer';
import axios from 'axios';
import * as React from 'react';
import { toast } from 'sonner';

const AUTOSAVE_DEBOUNCE_MS = 2_000; // Debounced 2s idle auto-save
const STALE_MESSAGE = 'Someone else updated this image. Refresh to load the latest version.';

const draftKey = (imageId: number): string => `sow_draft_${imageId}`; // §9.18

export interface UseSowEditorOptions {
    reportId: number;
    imageId: number;
    /** Raw server-stored layer (snake_case). */
    initialLayer: ApiAnnotationLayer | null | undefined;
    initialUpdatedAt: string | null;
    /** Called after a successful save so the page can refresh props. */
    onSaved?: () => void;
}

/** Editor API shared with the photo header (Save button + status live there). */
export type SowEditorApi = ReturnType<typeof useSowEditor>;

function errorMessage(err: unknown): string {
    if (axios.isAxiosError(err)) {
        return (err.response?.data?.message as string | undefined) ?? 'Failed to save annotations.';
    }
    return 'Failed to save annotations.';
}

export function useSowEditor({
    reportId,
    imageId,
    initialLayer,
    initialUpdatedAt,
    onSaved,
}: UseSowEditorOptions) {
    const store = useSowEditorStore();
    const saveCounter = React.useRef(0); // §6.3 Yellow #4 — race-safe autosave counter
    const layerRef = React.useRef(store.layer);
    const [draftAvailable, setDraftAvailable] = React.useState(false);
    const draftLayerRef = React.useRef<ApiAnnotationLayer | null>(null);

    // Load the server state once per image (parse snake_case → camelCase).
    React.useEffect(() => {
        store.setLayer(parseLayer(initialLayer));
        store.markSaved(initialUpdatedAt ?? '');
        saveCounter.current = 0;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [imageId]);

    // §9.18 — mirror dirty edits to localStorage; offer restore when a draft
    // is newer than the last server save (e.g. after a tab/session crash).
    React.useEffect(() => {
        if (!store.isDirty) return;
        localStorage.setItem(
            draftKey(imageId),
            JSON.stringify({
                layer: serializeLayer(store.layer),
                updatedAt: new Date().toISOString(),
            })
        );
    }, [store.layer, store.isDirty, imageId]);

    React.useEffect(() => {
        const raw = localStorage.getItem(draftKey(imageId));
        if (!raw) return;
        try {
            const draft = JSON.parse(raw) as { layer: ApiAnnotationLayer; updatedAt?: string };
            const draftNewer =
                !initialUpdatedAt ||
                (draft.updatedAt
                    ? Date.parse(draft.updatedAt) > Date.parse(initialUpdatedAt)
                    : true);
            if (draftNewer) {
                draftLayerRef.current = draft.layer;
                setDraftAvailable(true);
            } else {
                localStorage.removeItem(draftKey(imageId));
            }
        } catch {
            localStorage.removeItem(draftKey(imageId)); // corrupt draft — discard
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [imageId]);

    React.useEffect(() => {
        layerRef.current = store.layer;
    }, [store.layer]);

    const persist = React.useCallback(async (): Promise<boolean> => {
        if (!store.isDirty) {
            return true;
        }
        if (store.isSaving) {
            return false;
        }
        const snap = ++saveCounter.current;
        store.markSaving();
        try {
            const response = await sowImagesApi.saveAnnotations(
                reportId,
                imageId,
                layerRef.current,
                store.lastSavedAt
            );
            if (saveCounter.current === snap) {
                store.markSaved(response.annotations_updated_at ?? '');
                localStorage.removeItem(draftKey(imageId));
                onSaved?.();
            }
            return true;
        } catch (err) {
            store.markSaveFailed();
            if (axios.isAxiosError(err) && err.response?.status === 409) {
                toast.error(STALE_MESSAGE, {
                    action: {
                        label: 'Refresh',
                        onClick: () => window.location.reload(),
                    },
                });
            } else {
                toast.error(errorMessage(err));
            }
            return false;
        }
    }, [reportId, imageId, onSaved, store]);

    // Autosave: debounced 2s timer after editing stops while dirty
    React.useEffect(() => {
        if (!store.isDirty || store.isSaving) return;
        const timer = setTimeout(() => {
            void persist();
        }, AUTOSAVE_DEBOUNCE_MS);
        return () => clearTimeout(timer);
    }, [store.layer, store.isDirty, store.isSaving, persist]);

    // §9.8 / Yellow #2 — keepalive fetch on unload (sendBeacon is POST-only).
    React.useEffect(() => {
        const handleUnload = () => {
            if (!store.isDirty) return;
            fetch(route('sow.images.update', { report: reportId, image: imageId }), {
                method: 'PUT',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    layer: serializeLayer(layerRef.current),
                    annotations_updated_at: store.lastSavedAt,
                }),
            });
        };
        window.addEventListener('beforeunload', handleUnload);
        return () => window.removeEventListener('beforeunload', handleUnload);
    }, [reportId, imageId, store.isDirty, store.lastSavedAt]);

    const restoreDraft = () => {
        if (draftLayerRef.current) {
            store.setLayer(parseLayer(draftLayerRef.current));
            store.markDirty();
            localStorage.removeItem(draftKey(imageId));
            setDraftAvailable(false);
        }
    };

    const discardDraft = () => {
        localStorage.removeItem(draftKey(imageId));
        draftLayerRef.current = null;
        setDraftAvailable(false);
    };

    return {
        ...store,
        save: persist,
        draftAvailable,
        restoreDraft,
        discardDraft,
    };
}
