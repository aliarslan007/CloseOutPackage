import {
    mediaLibraryApi,
    useChunkedUpload,
    useMediaConfig,
    validateFileClient,
    type MediaAsset,
} from '@/features/media-library';
import * as React from 'react';
import { toast } from 'sonner';
import { sowMediaImportApi } from '../api/sow-media-import.api';
import { sowErrorMessage } from '../utils/sow-error';
import { useSowReports } from './use-sow-reports';

interface SowPhotoUploadProgress {
    done: number;
    total: number;
    label: string;
}

/** Files uploaded at once — enough to feel instant, few enough to dodge rate limits. */
const UPLOAD_CONCURRENCY = 3;

/**
 * Direct device → Media Library upload for SOW photos: validates each file,
 * runs it through the shared chunked pipeline, then bulk-attaches the
 * resulting assets via the same import endpoint the picker uses.
 *
 * The picker is multi-select. `remaining` is how many slots the report has
 * left: the selection is truncated to it, so picking 100 photos on an empty
 * report attaches the first 20 and tells the user the rest were dropped,
 * rather than failing the whole import at the server-side cap.
 */
export function useSowPhotoUpload(
    reportId: number,
    projectId?: number | null,
    remaining = 20,
) {
    const { config } = useMediaConfig();
    const { uploadFile } = useChunkedUpload();
    const { refresh } = useSowReports();
    const fileInputRef = React.useRef<HTMLInputElement>(null);
    const [progress, setProgress] = React.useState<SowPhotoUploadProgress | null>(null);

    const browseFiles = React.useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    const filterValidFiles = async (
        files: File[],
    ): Promise<{ valid: File[]; rejected: string[] }> => {
        if (!config) return { valid: [], rejected: [] };
        const valid: File[] = [];
        const rejected: string[] = [];
        for (const file of files) {
            const check = await validateFileClient(file, config);
            if (check.valid) valid.push(file);
            else rejected.push(check.error ?? `${file.name} is not a valid photo.`);
        }
        return { valid, rejected };
    };

    const uploadOne = async (file: File): Promise<number | null> => {
        if (!config) return null;
        const asset: MediaAsset | null = await uploadFile(
            {
                id: `sow-${reportId}-${file.size}-${file.name}`,
                file,
                status: 'uploading',
                progress: 0,
                retryCount: 0,
                projectId,
            },
            config,
            {
                onProgress: () => {},
                onStatusChange: () => {},
                onInitiated: () => {},
                onCompleted: () => {},
            },
            { isCancelled: () => false }
        );
        return asset?.id ?? null;
    };

    const waitUntilReady = async (assetIds: number[]): Promise<void> => {
        if (assetIds.length === 0) return;
        const deadline = Date.now() + 45_000;
        while (Date.now() < deadline) {
            try {
                const statuses = await Promise.all(
                    assetIds.map(async (id) => (await mediaLibraryApi.getMediaAsset(id)).status),
                );
                if (statuses.every((s) => s === 'ready' || s === 'failed')) return;
            } catch {
                return; // fail open — don't hang the UI on a status probe error
            }
            await new Promise((resolve) => setTimeout(resolve, 1_500));
        }
    };

    const importAssets = async (assetIds: number[]): Promise<void> => {
        try {
            const imported = await sowMediaImportApi.importMedia(reportId, assetIds);
            if (imported > 0) {
                toast.success(`${imported} photo${imported === 1 ? '' : 's'} added.`);
            }
        } catch (err) {
            toast.error(sowErrorMessage(err, 'Failed to add photos to this report.'));
        }
    };

    const chooseFiles = async (event: React.ChangeEvent<HTMLInputElement>): Promise<void> => {
        const selected = Array.from(event.target.files ?? []);
        event.target.value = ''; // allow re-picking the same files later
        if (selected.length === 0) return;

        const { valid, rejected } = await filterValidFiles(selected);
        if (rejected.length > 0) {
            toast.error(
                `${rejected.length} file${rejected.length === 1 ? '' : 's'} skipped — ${rejected[0]}`,
            );
        }
        if (valid.length === 0) return;

        // Truncate to the report's remaining capacity instead of letting the
        // server reject the whole batch.
        const chosen = valid.slice(0, Math.max(0, remaining));
        if (chosen.length === 0) {
            toast.error('No upload slots left — this report has reached its photo limit.');
            return;
        }
        const dropped = valid.length - chosen.length;
        if (dropped > 0) {
            toast.info(
                `${dropped} photo${dropped === 1 ? '' : 's'} skipped — this report only has room for ${chosen.length} more.`,
            );
        }

        setProgress({ done: 0, total: chosen.length, label: chosen[0]?.name ?? '' });

        const assetIds: (number | null)[] = new Array(chosen.length).fill(null);
        let next = 0;
        let done = 0;

        const worker = async (): Promise<void> => {
            while (next < chosen.length) {
                const index = next;
                next += 1;
                const file = chosen[index]!;
                const assetId = await uploadOne(file);
                assetIds[index] = assetId;
                if (assetId === null) {
                    toast.error(`${file.name} failed to upload. Skipped.`);
                }
                done += 1;
                setProgress({ done, total: chosen.length, label: file.name });
            }
        };

        await Promise.all(
            Array.from({ length: Math.min(UPLOAD_CONCURRENCY, chosen.length) }, worker),
        );

        const attached = assetIds.filter((id): id is number => id !== null);
        if (attached.length > 0) {
            setProgress({ done: chosen.length, total: chosen.length, label: 'Processing…' });
            // Wait for processing before attaching: a byte-identical re-upload
            // settles into a pointer row, and the server resolves it to the
            // canonical asset on import. Attaching first would capture a
            // still-processing id.
            await waitUntilReady(attached);
            await importAssets(attached);
        }

        setProgress(null);
        refresh();
    };

    return {
        fileInputRef,
        progress,
        isUploading: progress !== null,
        browseFiles,
        chooseFiles,
    };
}
