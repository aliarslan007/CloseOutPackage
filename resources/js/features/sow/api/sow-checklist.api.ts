import axios from 'axios';
import type { ChecklistResponsePayload, SowChecklistEntry } from '../types/sow.types';

export interface ChecklistUpdateResult {
    success: boolean;
    checklist?: SowChecklistEntry[];
}

export const sowChecklistApi = {
    async update(reportId: number, payload: ChecklistResponsePayload): Promise<ChecklistUpdateResult> {
        const res = await axios.put<ChecklistUpdateResult>(
            route('sow.reports.checklist.update', { report: reportId }),
            payload,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        return res.data;
    },

    async createItem(
        reportId: number,
        payload: { label: string; has_explain_field?: boolean; is_required?: boolean }
    ): Promise<void> {
        await axios.post(
            route('sow.reports.checklist.items.store', { report: reportId }),
            payload,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
    },

    async updateItem(
        reportId: number,
        itemId: number,
        payload: { label: string; has_explain_field?: boolean; is_required?: boolean }
    ): Promise<void> {
        await axios.put(
            route('sow.reports.checklist.items.update', { report: reportId, item: itemId }),
            payload,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
    },

    async deleteItem(reportId: number, itemId: number): Promise<void> {
        await axios.delete(
            route('sow.reports.checklist.items.destroy', { report: reportId, item: itemId }),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
    },

    async reorderItems(
        reportId: number,
        items: Array<{ id: number; sort_order: number }>
    ): Promise<void> {
        await axios.post(
            route('sow.reports.checklist.items.reorder', { report: reportId }),
            { items },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
    },
};
