import axios from 'axios';
import { serializeLayer } from '../utils/annotation-serializer';
import type { AnnotationLayer } from '../types/annotation.types';
import type {
    CompositeStatus,
    SowAnnotatedImage,
    UpdateImagePayload,
    UpdateImageResponse,
} from '../types/sow.types';

export const sowImagesApi = {
    async store(reportId: number, mediaAssetId: number): Promise<SowAnnotatedImage> {
        const response = await axios.post<{ success: boolean; annotated_image: SowAnnotatedImage }>(
            route('sow.images.store', { report: reportId }),
            { media_asset_id: mediaAssetId },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return response.data.annotated_image;
    },

    async update(
        reportId: number,
        imageId: number,
        payload: UpdateImagePayload
    ): Promise<UpdateImageResponse> {
        const response = await axios.put<UpdateImageResponse>(
            route('sow.images.update', { report: reportId, image: imageId }),
            payload,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return response.data;
    },

    /** Canvas editor save — serializes the camelCase layer to the §4.5 snake_case contract. */
    async saveAnnotations(
        reportId: number,
        imageId: number,
        layer: AnnotationLayer,
        annotationsUpdatedAt: string | null
    ): Promise<UpdateImageResponse> {
        const response = await axios.put<UpdateImageResponse>(
            route('sow.images.update', { report: reportId, image: imageId }),
            {
                layer: serializeLayer(layer),
                annotations_updated_at: annotationsUpdatedAt,
            },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return response.data;
    },

    async destroy(reportId: number, imageId: number): Promise<void> {
        await axios.delete(route('sow.images.destroy', { report: reportId, image: imageId }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    },

    async reorder(
        reportId: number,
        order: Array<{ id: number; sort_order: number }>
    ): Promise<void> {
        await axios.post(
            route('sow.images.reorder', { report: reportId }),
            { order },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
    },

    async compositeStatuses(
        reportId: number
    ): Promise<Array<{ id: number; composite_status: CompositeStatus }>> {
        const response = await axios.get<{
            statuses: Array<{ id: number; composite_status: CompositeStatus }>;
        }>(route('sow.images.statuses', { report: reportId }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return response.data.statuses;
    },
};
