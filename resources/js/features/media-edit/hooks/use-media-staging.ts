import * as React from 'react';
import type { StageEditMode, StagedMediaItem } from '../types';
import { isVideoFile } from '../utils/staged-file';

let sequence = 0;

function nextId(): string {
    sequence += 1;
    return `staged-${sequence}`;
}

/**
 * Holds picked files client-side so an image can be annotated before upload,
 * then hands the final list to the host once the uploader commits.
 *
 * Object URLs are created here and revoked here — on removal, when a replaced
 * thumbnail is discarded, and on unmount. Nothing else may revoke them.
 *
 * The editor state lives here too, so a host only has to render
 * `<MediaStagingPanel staging={staging} />` and read `items` when it uploads.
 */
export function useMediaStaging() {
    const [items, setItems] = React.useState<StagedMediaItem[]>([]);
    const [editingId, setEditingId] = React.useState<string | null>(null);
    const urlsRef = React.useRef<string[]>([]);
    // Read current items for side-effect decisions (URL revocation) instead of
    // doing that work inside a setState updater, which React may re-run.
    const itemsRef = React.useRef<StagedMediaItem[]>(items);
    itemsRef.current = items;

    const track = React.useCallback((url: string): string => {
        urlsRef.current.push(url);
        return url;
    }, []);

    const add = React.useCallback(
        (files: File[]): void => {
            if (files.length === 0) return;
            const staged = files.map<StagedMediaItem>((file) => ({
                id: nextId(),
                file,
                previewUrl: track(URL.createObjectURL(file)),
                isVideo: isVideoFile(file),
                derivedFromId: null,
            }));
            setItems((prev) => [...prev, ...staged]);
        },
        [track]
    );

    const remove = React.useCallback((id: string): void => {
        const target = itemsRef.current.find((item) => item.id === id);
        if (target) URL.revokeObjectURL(target.previewUrl);
        setItems((prev) => prev.filter((item) => item.id !== id));
        setEditingId((prev) => (prev === id ? null : prev));
    }, []);

    const openEditor = React.useCallback((id: string): void => setEditingId(id), []);
    const closeEditor = React.useCallback((): void => setEditingId(null), []);

    /**
     * Swap the open image for its annotated version. `replace` drops the picked
     * original (and frees its preview), `copy` keeps it and adds the annotated
     * version right after — the same two choices the Media Library annotator
     * offers.
     */
    const applyEdit = React.useCallback(
        (mode: StageEditMode, editedFile: File): void => {
            const source = itemsRef.current.find((item) => item.id === editingId);
            if (!source) return;

            const edited: StagedMediaItem = {
                id: nextId(),
                file: editedFile,
                previewUrl: track(URL.createObjectURL(editedFile)),
                isVideo: false,
                derivedFromId: source.derivedFromId ?? source.id,
            };

            if (mode === 'replace') {
                URL.revokeObjectURL(source.previewUrl);
                setItems((prev) => prev.map((item) => (item.id === source.id ? edited : item)));
                return;
            }

            setItems((prev) => {
                const index = prev.findIndex((item) => item.id === source.id);
                if (index === -1) return prev;
                return [...prev.slice(0, index + 1), edited, ...prev.slice(index + 1)];
            });
        },
        [editingId, track]
    );

    const reset = React.useCallback((): void => {
        urlsRef.current.forEach((url) => URL.revokeObjectURL(url));
        urlsRef.current = [];
        setItems([]);
        setEditingId(null);
    }, []);

    React.useEffect(
        () => () => {
            urlsRef.current.forEach((url) => URL.revokeObjectURL(url));
            urlsRef.current = [];
        },
        []
    );

    const editingItem = items.find((item) => item.id === editingId) ?? null;

    return {
        items,
        add,
        remove,
        reset,
        editingItem,
        openEditor,
        closeEditor,
        applyEdit,
    };
}

export type MediaStaging = ReturnType<typeof useMediaStaging>;
