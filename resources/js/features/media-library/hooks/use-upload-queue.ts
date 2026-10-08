import { useCallback, useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { useUploadQueueStore } from '../stores/upload-queue.store';
import type { MediaAsset } from '../types/media-library.types';
import { validateFileClient } from '../utils/validate-file-client';
import { useChunkedUpload } from './use-chunked-upload';
import { useMediaConfig } from './use-media-config';

const MAX_CONCURRENT_UPLOADS = 2;
const inFlightIds = new Set<string>();
let activeUploadCount = 0;

export function useUploadQueue(onAssetCompleted?: (asset: MediaAsset) => void) {
    const { config } = useMediaConfig();
    const { uploadFile } = useChunkedUpload();

    const files = useUploadQueueStore((state) => state.files);
    const isPaused = useUploadQueueStore((state) => state.isPaused);
    const addFilesToStore = useUploadQueueStore((state) => state.addFiles);

    const onAssetCompletedRef = useRef(onAssetCompleted);
    useEffect(() => {
        onAssetCompletedRef.current = onAssetCompleted;
    }, [onAssetCompleted]);

    // Warn on tab close if uploads are actively in-flight
    useEffect(() => {
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            const hasActiveUploads = useUploadQueueStore
                .getState()
                .files.some((f) => f.status === 'uploading' || f.status === 'pending');
            if (hasActiveUploads) {
                e.preventDefault();
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, []);

    const processNext = useCallback(() => {
        const store = useUploadQueueStore.getState();
        if (store.isPaused) return;
        if (activeUploadCount >= MAX_CONCURRENT_UPLOADS) return;

        // Always inspect real-time store state to prevent stale closure loops
        const nextFile = store.files.find(
            (f) => f.status === 'pending' && !inFlightIds.has(f.id),
        );

        if (!nextFile) return;

        const fileId = nextFile.id;
        inFlightIds.add(fileId);
        activeUploadCount++;

        // Mark uploading immediately so subsequent ticks or components cannot re-pick it
        store.updateFile(fileId, { status: 'uploading' });

        uploadFile(
            nextFile,
            config,
            {
                onProgress: (progress) => {
                    useUploadQueueStore.getState().updateFile(fileId, { progress });
                },
                onStatusChange: (status, error) => {
                    useUploadQueueStore.getState().updateFile(fileId, { status, error });
                    if (status === 'failed' && error?.toLowerCase().includes('rate limit')) {
                        useUploadQueueStore.getState().pause();
                        toast.warning('Upload limit reached. Pausing queue for 45s, then automatically resuming...');
                        setTimeout(() => {
                            useUploadQueueStore.getState().retryFile(fileId);
                            useUploadQueueStore.getState().resume();
                        }, 45000);
                    }
                },
                onInitiated: (assetId, token) => {
                    useUploadQueueStore.getState().updateFile(fileId, { assetId, uploadToken: token });
                },
                onCompleted: (asset) => {
                    onAssetCompletedRef.current?.(asset);
                },
            },
            {
                isCancelled: () => !useUploadQueueStore.getState().files.some((f) => f.id === fileId && f.status !== 'aborted'),
                isPaused: () => useUploadQueueStore.getState().isPaused,
            },
        ).finally(() => {
            inFlightIds.delete(fileId);
            activeUploadCount = Math.max(0, activeUploadCount - 1);
            processNext();
        });
    }, [config, uploadFile]);

    const pendingCount = useUploadQueueStore(
        (state) => state.files.filter((f) => f.status === 'pending').length,
    );

    useEffect(() => {
        if (pendingCount > 0 && !isPaused) {
            const availableSlots = Math.max(1, MAX_CONCURRENT_UPLOADS - activeUploadCount);
            for (let i = 0; i < availableSlots; i++) {
                processNext();
            }
        }
    }, [pendingCount, isPaused, processNext]);

    const enqueueFiles = useCallback(
        async (
            fileList: FileList | File[],
            projectId?: number | null,
            marketId?: number | null,
        ): Promise<number> => {
            const rawFiles = Array.from(fileList);
            if (rawFiles.length === 0) return 0;

            const validFiles: File[] = [];
            const errors: string[] = [];

            for (const file of rawFiles) {
                const check = await validateFileClient(file, config);
                if (check.valid) {
                    validFiles.push(file);
                } else if (check.error) {
                    errors.push(check.error);
                }
            }

            if (errors.length > 0) {
                if (errors.length === 1) {
                    toast.error(errors[0]);
                } else {
                    toast.error(`${errors.length} files could not be queued (invalid type or size).`);
                }
            }

            if (validFiles.length > 0) {
                addFilesToStore(validFiles, projectId, marketId);
                toast.info(`Added ${validFiles.length} file(s) to the upload queue.`);
            }

            return validFiles.length;
        },
        [config, addFilesToStore],
    );

    return {
        files,
        isPaused,
        enqueueFiles,
    };
}
