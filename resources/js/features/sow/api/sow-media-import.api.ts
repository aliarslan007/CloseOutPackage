import axios from 'axios';

export const sowMediaImportApi = {
    /** Bulk-import 1–50 photos from the Media Library into the SOW report. */
    async importMedia(reportId: number, mediaAssetIds: number[]): Promise<number> {
        const response = await axios.post<{ success: boolean; imported: number }>(
            route('sow.images.import-media', { report: reportId }),
            { media_asset_ids: mediaAssetIds },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return response.data.imported;
    },
};
