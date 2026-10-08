import { useState, useCallback } from 'react';
import { Loader2, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { mediaLibraryApi } from '../api/media-library.api';
import type { MediaAsset } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';

export interface MediaAssetDeleteDialogProps {
    asset: MediaAsset | null;
    isOpen: boolean;
    onClose: () => void;
    onDeleted: (assetId: number) => void;
}

export function MediaAssetDeleteDialog({
    asset,
    isOpen,
    onClose,
    onDeleted,
}: MediaAssetDeleteDialogProps) {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = useCallback(async () => {
        if (!asset) return;

        setIsDeleting(true);
        try {
            await mediaLibraryApi.deleteMediaAsset(asset.id);
            toast.success(`${asset.original_filename} removed from library.`);
            onDeleted(asset.id);
            onClose();
        } catch {
            toast.error('Failed to delete asset. Please try again.');
        } finally {
            setIsDeleting(false);
        }
    }, [asset, onDeleted, onClose]);

    if (!asset) return null;

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="h-5 w-5" />
                        Delete Media Asset
                    </DialogTitle>
                    <DialogDescription className="space-y-2 pt-2">
                        Are you sure you want to delete{' '}
                        <strong className="text-foreground">{asset.original_filename}</strong> (
                        {formatFileSize(asset.file_size_bytes)})?
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-border bg-muted/40 p-3 text-xs text-muted-foreground space-y-1">
                    <p className="font-semibold text-foreground">Important Note:</p>
                    <p>
                        This will remove the photo from your Media Library and reclaim your storage quota. Any
                        Verizon Closeout Packages that already imported this photo will safely retain their copy.
                    </p>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={onClose} disabled={isDeleting}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleDelete}
                        disabled={isDeleting}
                        className="gap-1.5"
                    >
                        {isDeleting ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Deleting...
                            </>
                        ) : (
                            'Confirm Delete'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
