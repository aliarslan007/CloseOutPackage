import axios from 'axios';
import type { SowImageGroup } from '../types/sow.types';

export interface SowImageLayoutPayload {
    groups: Array<{ id: number; sort_order: number }>;
    images: Array<{ id: number; sow_image_group_id: number | null; sort_order: number }>;
}

/**
 * Group order, image membership, and image order are persisted together by one
 * endpoint, so the board can never be left half-applied.
 */
export const sowImageGroupsApi = {
    async store(reportId: number, name: string): Promise<SowImageGroup> {
        const response = await axios.post<{ success: boolean; group: SowImageGroup }>(
            route('sow.image-groups.store', { report: reportId }),
            { name },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return response.data.group;
    },

    async update(reportId: number, groupId: number, name: string): Promise<SowImageGroup> {
        const response = await axios.put<{ success: boolean; group: SowImageGroup }>(
            route('sow.image-groups.update', { report: reportId, group: groupId }),
            { name },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return response.data.group;
    },

    async destroy(reportId: number, groupId: number): Promise<void> {
        await axios.delete(
            route('sow.image-groups.destroy', { report: reportId, group: groupId }),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
    },

    async saveLayout(reportId: number, payload: SowImageLayoutPayload): Promise<void> {
        await axios.patch(route('sow.images.layout', { report: reportId }), payload, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    },
};
