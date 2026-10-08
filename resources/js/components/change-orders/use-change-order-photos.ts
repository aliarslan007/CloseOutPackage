import {
    DEFAULT_CHANGE_ORDER_PHOTO_LIMIT,
    type ChangeOrder,
    type ChangeOrderPhoto,
} from '@/components/change-orders/change-order-types';
import { uploadThroughLibrary } from '@/components/change-orders/change-order-photo-helpers';
import { useMediaStaging } from '@/features/media-edit';
import { useChunkedUpload, useMediaConfig, validateFileClient } from '@/features/media-library';
import { ROLES } from '@/lib/permissions';
import type { PageProps } from '@/types/inertia';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import * as React from 'react';
import { toast } from 'sonner';

export interface ChangeOrderPhotoProgress {
    done: number;
    total: number;
    label: string;
    pct: number;
}

/**
 * Owns everything the change-order photo section needs that is not markup:
 * device uploads through the Media Library chunked pipeline, attaching picked
 * library assets, and detaching a photo. Both entry points converge on the
 * same attach call, and the server copies assets onto the private disk so
 * staff, the customer portal and the no-auth token page can all render them.
 *
 * Picked files wait in staging so a photo can be marked up before it is sent;
 * the uploader commits the whole batch with one Upload action.
 */
export function useChangeOrderPhotos(
    changeOrder: ChangeOrder,
    photoLimit: number = DEFAULT_CHANGE_ORDER_PHOTO_LIMIT
) {
    const { config } = useMediaConfig();
    const { uploadFile } = useChunkedUpload();
    const roles = usePage<PageProps>().props.auth?.roles ?? [];
    const staging = useMediaStaging();

    // Server enforces ChangeOrderPolicy::update (admin/manager + draft); the
    // UI gate only hides what would 403 anyway.
    const canManage =
        (roles.includes(ROLES.ADMIN) || roles.includes(ROLES.MANAGER)) &&
        changeOrder.status === 'draft';

    const photos = changeOrder.photos ?? [];
    const limit = Math.max(1, photoLimit);
    const remainingSlots = Math.max(0, limit - photos.length);
    const isAtLimit = remainingSlots === 0;
    // Slots left once the files still waiting to be uploaded are counted.
    const room = Math.max(0, remainingSlots - staging.items.length);
    const fileInputRef = React.useRef<HTMLInputElement>(null);
    const [progress, setProgress] = React.useState<ChangeOrderPhotoProgress | null>(null);
    const [photoToRemove, setPhotoToRemove] = React.useState<ChangeOrderPhoto | null>(null);
    const [isRemoving, setIsRemoving] = React.useState(false);

    const attach = React.useCallback(
        async (assetIds: number[]): Promise<void> => {
            await axios.post(
                route('projects.change-orders.photos.store', {
                    project: changeOrder.project_id,
                    change_order: changeOrder.id,
                }),
                { media_asset_ids: assetIds }
            );
            router.reload({ only: ['changeOrder'] });
        },
        [changeOrder.id, changeOrder.project_id]
    );

    const chooseFiles = React.useCallback(
        async (event: React.ChangeEvent<HTMLInputElement>): Promise<void> => {
            const files = Array.from(event.target.files ?? []);
            event.target.value = ''; // allow re-picking the same file later
            if (files.length === 0 || !config) return;

            if (room === 0) {
                toast.error(`Photo limit reached — a change order can include at most ${limit} photos.`);
                return;
            }

            // Keep only what fits so the upload call never exceeds the cap;
            // the rest of the selection is skipped up front.
            const candidates = files.slice(0, room);
            if (candidates.length < files.length) {
                toast.warning(
                    `Only ${candidates.length} of ${files.length} photo(s) were added — a change order can include at most ${limit} photos.`
                );
            }

            const valid: File[] = [];
            for (const file of candidates) {
                const check = await validateFileClient(file, config);
                if (check.valid) valid.push(file);
                else toast.error(check.error ?? `${file.name} is not a valid photo.`);
            }
            if (valid.length === 0) return;

            staging.add(valid);
        },
        [config, limit, room, staging]
    );

    const uploadStaged = React.useCallback(async (): Promise<void> => {
        const files = staging.items.map((item) => item.file);
        if (files.length === 0 || !config) return;

        setProgress({ done: 0, total: files.length, label: files[0].name, pct: 0 });

        const result = await uploadThroughLibrary(
            files,
            config,
            changeOrder.project_id,
            uploadFile,
            (name) => setProgress((prev) => (prev ? { ...prev, label: name, pct: 0 } : prev)),
            (pct) => setProgress((prev) => (prev ? { ...prev, pct } : prev)),
            () => setProgress((prev) => (prev ? { ...prev, done: prev.done + 1 } : prev))
        );

        setProgress(null);
        // ponytail: one shot per batch — a partial failure drops the failed
        // files from staging (re-pick to retry) instead of tracking which ones
        // succeeded, which would re-upload duplicates on every retry.
        staging.reset();

        const assetIds = result.assets.map((asset) => asset.id);
        let failures = result.failures;

        if (assetIds.length > 0) {
            try {
                await attach(assetIds);
                toast.success(`Uploaded ${assetIds.length} photo(s) to this change order.`);
            } catch {
                failures += assetIds.length;
            }
        }

        if (failures > 0) {
            toast.error(
                failures === files.length
                    ? 'Upload failed. Please check the files and try again.'
                    : `${failures} of ${files.length} photo(s) could not be added. The rest were saved.`
            );
        }
    }, [attach, changeOrder.project_id, config, staging, uploadFile]);

    const confirmRemove = React.useCallback(async (): Promise<void> => {
        if (photoToRemove === null) return;
        setIsRemoving(true);
        try {
            await axios.delete(
                route('projects.change-orders.photos.destroy', {
                    project: changeOrder.project_id,
                    change_order: changeOrder.id,
                    photo: photoToRemove.id,
                })
            );
            toast.success('Photo removed.');
            setPhotoToRemove(null);
            router.reload({ only: ['changeOrder'] });
        } catch {
            toast.error('Failed to remove the photo.');
        } finally {
            setIsRemoving(false);
        }
    }, [changeOrder.id, changeOrder.project_id, photoToRemove]);

    const browseFiles = React.useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    return {
        canManage,
        photos,
        remainingSlots,
        room,
        isAtLimit,
        fileInputRef,
        progress,
        isUploading: progress !== null,
        staging,
        chooseFiles,
        uploadStaged,
        browseFiles,
        attach,
        photoToRemove,
        requestRemove: setPhotoToRemove,
        cancelRemove: React.useCallback(() => setPhotoToRemove(null), []),
        confirmRemove,
        isRemoving,
    };
}
