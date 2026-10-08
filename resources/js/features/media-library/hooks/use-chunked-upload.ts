import axios from 'axios';
import { useCallback, useRef } from 'react';
import { toast } from 'sonner';
import { mediaLibraryApi } from '../api/media-library.api';
import { notifyAssetCompleted } from '../stores/upload-queue.store';
import type {
    MediaAsset,
    MediaLibraryConfig,
    QueuedFile,
} from '../types/media-library.types';
import { sliceFileIntoChunks } from '../utils/chunk-file';

export interface ChunkedUploadCallbacks {
    onProgress: (progress: number) => void;
    onStatusChange: (status: QueuedFile['status'], error?: string) => void;
    onInitiated: (assetId: number, token: string) => void;
    onCompleted: (asset: MediaAsset) => void;
}

const MAX_CHUNK_RETRIES = 3;
const RETRY_BACKOFF_MS = [1000, 2000, 4000];
const STATUS_ABORTED = 'aborted';
const CANCELLED_MESSAGE = 'Cancelled.';

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function parseErrorMessage(err: unknown): string {
    if (!axios.isAxiosError(err)) {
        return err instanceof Error ? err.message : 'Upload failed.';
    }

    const status = err.response?.status;
    const msg = (err.response?.data as { message?: string } | undefined)?.message;

    if (status === 401) {
        return 'Upload session expired. Please re-select this file.';
    }
    if (status === 409) {
        return msg ?? 'Upload conflict or already completed.';
    }
    if (status === 422) {
        return msg ?? 'Storage quota exceeded or invalid file.';
    }
    if (status === 429) {
        return 'Upload rate limit reached. Please wait a moment.';
    }
    if (status === 507) {
        return 'Server storage is temporarily unavailable.';
    }
    return msg ?? err.message;
}

export interface UploadControllers {
    isCancelled: () => boolean;
    isPaused?: () => boolean;
}

interface UploadContext {
    assetId: number;
    token: string;
    totalSize: number;
    totalChunks: number;
    isCancelled: () => boolean;
    isPaused: () => boolean;
    onProgress: (progress: number) => void;
}

async function waitWhilePaused(isPaused: () => boolean, isCancelled: () => boolean): Promise<boolean> {
    while (isPaused()) {
        if (isCancelled()) return false;
        await sleep(250);
    }
    return !isCancelled();
}

async function uploadSingleChunk(
    chunkBlob: Blob,
    index: number,
    baseLoaded: number,
    ctx: UploadContext,
): Promise<void> {
    for (let attempt = 0; attempt < MAX_CHUNK_RETRIES; attempt++) {
        const canProceed = await waitWhilePaused(ctx.isPaused, ctx.isCancelled);
        if (!canProceed) return;

        try {
            await mediaLibraryApi.uploadChunk(
                ctx.assetId,
                ctx.token,
                chunkBlob,
                index,
                ctx.totalChunks,
                (chunkLoaded) => {
                    const total = Math.min(99, Math.round(((baseLoaded + chunkLoaded) / ctx.totalSize) * 100));
                    ctx.onProgress(total);
                },
            );
            return;
        } catch (err: unknown) {
            if (ctx.isCancelled() || (axios.isAxiosError(err) && err.response?.status === 401)) {
                throw err;
            }
            if (attempt === MAX_CHUNK_RETRIES - 1) {
                throw err;
            }
            await sleep(RETRY_BACKOFF_MS[attempt] ?? 2000);
        }
    }
}

async function uploadAllChunks(chunks: Blob[], ctx: UploadContext): Promise<boolean> {
    let loadedBase = 0;

    for (let i = 0; i < chunks.length; i++) {
        const canProceed = await waitWhilePaused(ctx.isPaused, ctx.isCancelled);
        if (!canProceed) return false;

        const blob = chunks[i];
        await uploadSingleChunk(blob, i, loadedBase, ctx);

        if (ctx.isCancelled()) return false;

        loadedBase += blob.size;
        ctx.onProgress(Math.min(99, Math.round((loadedBase / ctx.totalSize) * 100)));
    }

    return true;
}

export function useChunkedUpload() {
    const abortedAssetsRef = useRef<Set<number>>(new Set());

    const abortAssetSilently = useCallback((assetId: number) => {
        if (!abortedAssetsRef.current.has(assetId)) {
            abortedAssetsRef.current.add(assetId);
            mediaLibraryApi.abortUpload(assetId).catch(() => {});
        }
    }, []);

    const uploadFile = useCallback(
        async (
            queuedFile: QueuedFile,
            config: MediaLibraryConfig,
            callbacks: ChunkedUploadCallbacks,
            controllers: UploadControllers,
        ): Promise<MediaAsset | null> => {
            const isPaused = controllers.isPaused ?? (() => false);
            const isCancelled = controllers.isCancelled;

            const { file, projectId, marketId } = queuedFile;
            const chunks = sliceFileIntoChunks(file, config.chunk_size_bytes);
            callbacks.onStatusChange('uploading');

            let assetId: number | null = null;

            try {
                const canStart = await waitWhilePaused(isPaused, isCancelled);
                if (!canStart) {
                    callbacks.onStatusChange(STATUS_ABORTED, CANCELLED_MESSAGE);
                    return null;
                }

                const init = await mediaLibraryApi.initiateUpload({
                    filename: file.name,
                    total_size: file.size,
                    total_chunks: chunks.length,
                    mime_type: file.type || 'application/octet-stream',
                    project_id: projectId,
                    market_id: marketId,
                });

                assetId = init.asset_id;
                callbacks.onInitiated(assetId, init.upload_token);

                if (isCancelled()) {
                    abortAssetSilently(assetId);
                    callbacks.onStatusChange(STATUS_ABORTED, CANCELLED_MESSAGE);
                    return null;
                }

                const ctx: UploadContext = {
                    assetId,
                    token: init.upload_token,
                    totalSize: file.size,
                    totalChunks: chunks.length,
                    isCancelled,
                    isPaused,
                    onProgress: callbacks.onProgress,
                };

                const completedChunks = await uploadAllChunks(chunks, ctx);
                if (!completedChunks || isCancelled()) {
                    abortAssetSilently(assetId);
                    callbacks.onStatusChange(STATUS_ABORTED, CANCELLED_MESSAGE);
                    return null;
                }

                const canFinalize = await waitWhilePaused(isPaused, isCancelled);
                if (!canFinalize) {
                    abortAssetSilently(assetId);
                    callbacks.onStatusChange(STATUS_ABORTED, CANCELLED_MESSAGE);
                    return null;
                }

                const asset = await mediaLibraryApi.finalizeUpload(assetId, init.upload_token);
                callbacks.onProgress(100);

                // With an inline (sync) queue the finalize response already
                // carries the verdict — a rejected upload (e.g. duplicate) is
                // a failure, not a completed one.
                if (asset.status === 'failed') {
                    const reason = asset.validation_error ?? 'Upload failed.';
                    callbacks.onStatusChange('failed', reason);
                    toast.error(reason);
                    return null;
                }

                callbacks.onStatusChange('done');
                callbacks.onCompleted(asset);
                notifyAssetCompleted(asset);
                return asset;
            } catch (err: unknown) {
                callbacks.onStatusChange('failed', parseErrorMessage(err));
                if (assetId) {
                    abortAssetSilently(assetId);
                }
                return null;
            }
        },
        [abortAssetSilently],
    );

    return { uploadFile };
}
