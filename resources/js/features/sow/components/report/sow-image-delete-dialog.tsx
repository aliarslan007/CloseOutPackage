import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import * as React from 'react';
import { toast } from 'sonner';
import { sowImagesApi } from '../../api/sow-images.api';
import { useSowReports } from '../../hooks/use-sow-reports';
import type { SowAnnotatedImage } from '../../types/sow.types';
import { sowErrorMessage } from '../../utils/sow-error';

export interface SowImageDeleteDialogProps {
    reportId: number;
    /** The photo awaiting confirmation; null keeps the dialog closed. */
    image: SowAnnotatedImage | null;
    onClose: () => void;
}

/** Names the photo consistently across the strip and the grouped board. */
export function sowImageLabel(image: SowAnnotatedImage): string {
    return (
        image.page_title ?? image.media_asset?.original_filename ?? `Photo ${image.sort_order + 1}`
    );
}

/**
 * Confirms before removing a photo from a report. Every remove action routes
 * through this so a thumbnail click can never delete silently.
 */
export function SowImageDeleteDialog({
    reportId,
    image,
    onClose,
}: Readonly<SowImageDeleteDialogProps>) {
    const { refresh } = useSowReports();
    const [deleting, setDeleting] = React.useState(false);

    const handleConfirm = async () => {
        if (!image) return;

        setDeleting(true);
        try {
            await sowImagesApi.destroy(reportId, image.id);
            toast.success('Photo removed.');
            refresh();
            onClose();
        } catch (err) {
            toast.error(sowErrorMessage(err, 'Failed to remove photo.'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <ConfirmDialog
            open={image !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Remove photo"
            description={`Remove "${image ? sowImageLabel(image) : ''}" from this report? This cannot be undone.`}
            confirmLabel="Remove"
            variant="destructive"
            loading={deleting}
            onConfirm={() => void handleConfirm()}
        />
    );
}
