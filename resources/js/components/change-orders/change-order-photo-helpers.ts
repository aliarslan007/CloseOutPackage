import {
    useChunkedUpload,
    validateFileClient,
    type MediaAsset,
    type MediaLibraryConfig,
} from '@/features/media-library';
import { toast } from 'sonner';

// Pure helpers for change-order photo attachment: staging, dedupe, drag-payload
// parsing and library uploads. Kept out of the component so both stay focused.
// Only the symbols the component actually calls are exported.

export interface AttachedPhoto {
    id: number | string;
    photo_id?: number;
    media_asset_id?: number | null;
    original_name: string;
    mime_type?: string;
    size_bytes?: number;
    url: string;
}

export interface UploadProgress {
    done: number;
    total: number;
    label: string;
    pct: number;
}

const MEDIA_ASSET_ID_TYPE = 'text/x-media-asset-id';
const MEDIA_ASSET_IDS_TYPE = 'text/x-media-asset-ids';
const MEDIA_LIBRARY_FILE_ROUTE = 'media-library.file';
const JSON_PAYLOAD_TYPE = 'application/json';

export interface DroppedMediaAsset {
    id: number;
    original_name: string;
    url: string;
}

export function mediaAssetFileUrl(id: number, thumbUrl?: string | null, url?: string | null): string {
    return thumbUrl || url || (id ? route(MEDIA_LIBRARY_FILE_ROUTE, { asset: id }) : '');
}

/** Stable identity of a staged photo — drives dedupe against attached assets. */
export function photoKey(photo: AttachedPhoto): number | null {
    if (typeof photo.media_asset_id === 'number' && photo.media_asset_id > 0) {
        return photo.media_asset_id;
    }
    return typeof photo.id === 'number' && photo.id > 0 ? photo.id : null;
}

/** Stages a library asset; `previewUrl` overrides for freshly uploaded files. */
export function mediaAssetToPhoto(asset: MediaAsset, previewUrl?: string): AttachedPhoto {
    return {
        id: `asset-${asset.id}`,
        media_asset_id: asset.id,
        original_name: asset.original_filename,
        mime_type: asset.mime_type,
        size_bytes: asset.file_size_bytes,
        url: previewUrl || mediaAssetFileUrl(asset.id, asset.thumb_url, asset.url),
    };
}

/** Keeps only files that pass client-side validation, toasting each rejection. */
export async function collectValidFiles(files: File[], config: MediaLibraryConfig): Promise<File[]> {
    const valid: File[] = [];

    for (const file of files) {
        const check = await validateFileClient(file, config);
        if (check.valid) {
            valid.push(file);
        } else {
            toast.error(check.error ?? `${file.name} is not a valid photo.`);
        }
    }

    return valid;
}

function libraryAssetName(id: number): string {
    return `library-asset-${id}.jpg`;
}

export function hasMediaOrFileDrop(types: readonly string[]): boolean {
    return (
        types.includes(MEDIA_ASSET_ID_TYPE) ||
        types.includes(MEDIA_ASSET_IDS_TYPE) ||
        types.includes(JSON_PAYLOAD_TYPE) ||
        types.includes('Files')
    );
}

function parseJsonAssetPayload(jsonRaw: string): DroppedMediaAsset | null {
    if (!jsonRaw) {
        return null;
    }
    try {
        const parsed = JSON.parse(jsonRaw);
        if (parsed && typeof parsed.id === 'number') {
            return {
                id: parsed.id,
                original_name: parsed.filename || `photo-${parsed.id}.jpg`,
                url: parsed.url || '',
            };
        }
    } catch {
        return null;
    }
    return null;
}

function parseIdListPayload(multipleRaw: string): number[] {
    if (!multipleRaw) {
        return [];
    }
    try {
        const ids = JSON.parse(multipleRaw);
        if (!Array.isArray(ids)) {
            return [];
        }
        return ids.map((id) => Number(id)).filter((numId) => numId > 0);
    } catch {
        return [];
    }
}

function parseSingleIdPayload(singleRaw: string): number | null {
    if (!singleRaw) {
        return null;
    }
    const numId = Number(singleRaw);
    return numId > 0 ? numId : null;
}

export function parseDroppedMediaAssets(dataTransfer: DataTransfer): DroppedMediaAsset[] {
    // Check application/json payload first (sent by MediaPickerItem)
    const fromJson = parseJsonAssetPayload(dataTransfer.getData(JSON_PAYLOAD_TYPE));
    if (fromJson) {
        return [fromJson];
    }

    // Check multiple IDs
    const ids = parseIdListPayload(dataTransfer.getData(MEDIA_ASSET_IDS_TYPE));
    if (ids.length > 0) {
        return ids.map((id) => ({ id, original_name: libraryAssetName(id), url: '' }));
    }

    // Check single ID
    const singleId = parseSingleIdPayload(dataTransfer.getData(MEDIA_ASSET_ID_TYPE));
    if (singleId !== null) {
        return [{ id: singleId, original_name: libraryAssetName(singleId), url: '' }];
    }

    return [];
}

export function dropzoneContainerClass(isDragOver: boolean, hasPhotos: boolean): string {
    if (isDragOver) {
        return 'border-dashed border-primary bg-primary/10 ring-2 ring-primary/20';
    }
    if (hasPhotos) {
        return 'border-border/80 bg-muted/10 p-3';
    }
    return 'border-dashed border-border/80 bg-muted/20 p-6 text-center';
}

export async function uploadThroughLibrary(
    files: File[],
    config: MediaLibraryConfig,
    projectId: number,
    uploadFile: ReturnType<typeof useChunkedUpload>['uploadFile'],
    onFileStart: (name: string) => void,
    onFileProgress: (pct: number) => void,
    onFileDone: () => void
): Promise<{ assets: MediaAsset[]; failures: number }> {
    const assets: MediaAsset[] = [];
    let failures = 0;

    for (const file of files) {
        onFileStart(file.name);
        const asset: MediaAsset | null = await uploadFile(
            {
                id: `co-${projectId}-${file.size}-${file.name}`,
                file,
                status: 'uploading',
                progress: 0,
                retryCount: 0,
                projectId,
            },
            config,
            {
                onProgress: onFileProgress,
                onStatusChange: () => {},
                onInitiated: () => {},
                onCompleted: () => {},
            },
            { isCancelled: () => false }
        );

        if (asset) {
            assets.push(asset);
            onFileDone();
        } else {
            failures += 1;
        }
    }

    return { assets, failures };
}
