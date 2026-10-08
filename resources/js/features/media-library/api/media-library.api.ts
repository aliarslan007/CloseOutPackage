import axios from 'axios';
import type { ApiAnnotationLayer } from '@/features/sow/utils/annotation-serializer';
import type {
    CursorPaginatedResponse,
    InitiateUploadPayload,
    InitiateUploadResponse,
    MediaAsset,
    MediaLibraryConfig,
    MediaPurgePreviewResponse,
    MediaPurgeProgress,
    MediaStatsResponse,
    TeamSupervisor,
} from '../types/media-library.types';

export const mediaLibraryApi = {
    async getConfig(): Promise<MediaLibraryConfig> {
        const response = await axios.get<MediaLibraryConfig>(route('media-library.config'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return response.data;
    },

    async getProjects(): Promise<Array<{ id: number; name: string; market_id?: number }>> {
        const response = await axios.get<{ data: Array<{ id: number; name: string; market_id?: number }> }>(
            route('media-library.projects'),
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        return response.data.data ?? [];
    },

    async initiateUpload(payload: InitiateUploadPayload): Promise<InitiateUploadResponse> {
        const response = await axios.post<InitiateUploadResponse>(
            route('media-library.upload.initiate'),
            payload,
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        return response.data;
    },

    async uploadChunk(
        assetId: number,
        token: string,
        chunk: Blob,
        chunkIndex: number,
        totalChunks: number,
        onProgress?: (loadedBytes: number) => void,
    ): Promise<void> {
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('chunk_index', String(chunkIndex));
        formData.append('total_chunks', String(totalChunks));

        await axios.post(route('media-library.upload.chunk', { asset: assetId }), formData, {
            headers: {
                Authorization: `Bearer ${token}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
            onUploadProgress: (progressEvent) => {
                if (onProgress && progressEvent.loaded) {
                    onProgress(progressEvent.loaded);
                }
            },
        });
    },

    async finalizeUpload(assetId: number, token: string): Promise<MediaAsset> {
        const response = await axios.post<{ data?: MediaAsset; asset_id?: number; status?: string }>(
            route('media-library.upload.finalize', { asset: assetId }),
            {},
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        const data = response.data;
        if (data && typeof data === 'object' && 'data' in data && data.data) {
            return data.data;
        }

        const fallback = (data ?? {}) as Record<string, unknown>;
        return {
            id: typeof fallback.asset_id === 'number' ? fallback.asset_id : assetId,
            user_id: 0,
            market_id: null,
            project_id: null,
            original_filename: '',
            mime_type: 'image/jpeg',
            file_size_bytes: 0,
            total_chunks: 1,
            gps_latitude: null,
            gps_longitude: null,
            gps_accuracy: null,
            captured_at: null,
            status: typeof fallback.status === 'string' ? (fallback.status as MediaAsset['status']) : 'processing',
            validation_error: null,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            url: null,
            thumb_url: null,
        };
    },

    async abortUpload(assetId: number): Promise<void> {
        await axios.delete(route('media-library.upload.abort', { asset: assetId }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    },

    async getStats(userId?: number): Promise<MediaStatsResponse> {
        const params = typeof userId === 'number' ? { user_id: userId } : {};
        const response = await axios.get<MediaStatsResponse>(route('media-library.stats', params), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return response.data;
    },

    async getTeamSupervisors(): Promise<TeamSupervisor[]> {
        const response = await axios.get<{ data: TeamSupervisor[] }>(route('media-library.team'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return response.data.data ?? [];
    },

    async getMediaAssets(
        params: Record<string, unknown> = {},
    ): Promise<CursorPaginatedResponse<MediaAsset>> {
        const response = await axios.get<CursorPaginatedResponse<MediaAsset>>(
            route('media-library.index', params),
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        return response.data;
    },

    async getPickerAssets(
        params: Record<string, unknown> = {},
    ): Promise<CursorPaginatedResponse<MediaAsset>> {
        const response = await axios.get<CursorPaginatedResponse<MediaAsset>>(
            route('media-library.picker', params),
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        return response.data;
    },

    async getMediaAsset(assetId: number): Promise<MediaAsset> {
        const response = await axios.get<{ data: MediaAsset }>(
            route('media-library.show', { asset: assetId }),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        return response.data.data;
    },

    async deleteMediaAsset(assetId: number): Promise<void> {
        await axios.delete(route('media-library.destroy', { asset: assetId }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    },

    async assignMediaAsset(payload: {
        media_asset_id?: number;
        media_asset_ids?: number[];
        category_selection_id: number;
    }): Promise<{ data: unknown; count?: number }> {
        const response = await axios.post<{ data: unknown; count?: number }>(
            route('media-library.assign'),
            payload,
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        return response.data;
    },

    async annotateMediaAsset(
        assetId: number,
        imageBlob: Blob,
        saveMode: 'copy' | 'overwrite' = 'copy',
        layer?: ApiAnnotationLayer | null,
    ): Promise<MediaAsset> {
        const formData = new FormData();
        formData.append('image', imageBlob, 'annotated.jpg');
        formData.append('save_mode', saveMode);
        if (layer) {
            formData.append('layer', JSON.stringify(layer));
        }

        const response = await axios.post<{ success: boolean; data: MediaAsset }>(
            route('media-library.annotate', { asset: assetId }),
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        return response.data.data;
    },
    /** Dry run for the admin-only system purge. Read-only. */
    async getPurgePreview(): Promise<MediaPurgePreviewResponse> {
        const response = await axios.get<MediaPurgePreviewResponse>(route('media-library.purge.preview'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return response.data;
    },

    /**
     * Starts the purge. `expectedTotal` must match what the preview reported, or
     * the server refuses with 409 — the admin confirms numbers they actually saw.
     */
    async startPurge(expectedTotal: number, confirmation: string): Promise<MediaPurgePreviewResponse> {
        const response = await axios.post<MediaPurgePreviewResponse>(
            route('media-library.purge.store'),
            { expected_total: expectedTotal, confirmation },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        return response.data;
    },

    async getPurgeStatus(): Promise<{ progress: Partial<MediaPurgeProgress> }> {
        const response = await axios.get<{ progress: Partial<MediaPurgeProgress> }>(
            route('media-library.purge.status'),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        return response.data;
    },
};
