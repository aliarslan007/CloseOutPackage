import { mediaLibraryApi, type MediaAsset } from '@/features/media-library';
import * as React from 'react';
import { toast } from 'sonner';

/**
 * Opens the canvas editor for a photo that is *already* in the Media Library.
 *
 * Library picks and finished device uploads are assets, not local files, so
 * there is nothing left to stage — they get annotated in place via
 * `MediaCanvasEditorDialog`, which needs the full record (clean source image and
 * stored layer) that the change-order photo lists do not carry. Hence the fetch
 * on open.
 */
export function useMediaAssetEditor() {
    const [asset, setAsset] = React.useState<MediaAsset | null>(null);
    const [isOpen, setIsOpen] = React.useState(false);

    const open = React.useCallback(async (assetId: number): Promise<void> => {
        setIsOpen(true);
        try {
            setAsset(await mediaLibraryApi.getMediaAsset(assetId));
        } catch {
            setIsOpen(false);
            toast.error('Could not open this photo for editing.');
        }
    }, []);

    const close = React.useCallback((): void => {
        setIsOpen(false);
        setAsset(null);
    }, []);

    return { asset, isOpen, open, close };
}
