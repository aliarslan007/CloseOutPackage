import axios, { type AxiosError } from 'axios';

/**
 * Batch photo uploads for the package page. Posts through axios instead of
 * Inertia so an autosave/refresh visit can never interrupt an in-flight
 * upload (Inertia v2 serializes visits with maxConcurrent: 1, which is what
 * stalled 40-50 file batches).
 *
 * Error shape mirrors what getCategoryUploadErrorMessage() already expects
 * (errors.photo / errors.category_selection_id / errors.position / message),
 * so the existing toast wording is preserved unchanged.
 */

export interface UploadPhotoPayload {
    packageId: number;
    stage: string;
    file: File;
    categorySelectionId: number;
    position: number;
    gps?: { latitude: number; longitude: number; accuracy?: number };
    /** 'YYYY-MM-DD HH:mm:ss' */
    capturedAt?: string;
    notes?: string;
}

export interface UploadPhotoResult {
    id: number;
    position: number;
    status: string;
    package_category_selection_id: number;
}

interface ServerErrorPayload {
    message?: string;
    errors?: Record<string, string | string[]>;
}

const FALLBACK_UPLOAD_ERROR = 'Upload failed. Please check the image and try again.';
const CONNECTION_UPLOAD_ERROR = 'Upload failed. Please check your connection and try again.';

function firstValidationMessage(data: ServerErrorPayload): Record<string, string> | null {
    const errors: Record<string, string> = {};

    for (const [key, value] of Object.entries(data.errors ?? {})) {
        const first = Array.isArray(value) ? value[0] : value;
        if (typeof first === 'string' && first.length > 0) {
            errors[key] = first;
        }
    }

    return Object.keys(errors).length > 0 ? errors : null;
}

function axiosUploadError(error: AxiosError): Record<string, string> {
    const data = (error.response?.data ?? {}) as ServerErrorPayload;

    const validation = firstValidationMessage(data);
    if (validation !== null) {
        return validation;
    }

    if (typeof data.message === 'string' && data.message.length > 0) {
        return { message: data.message };
    }

    if (error.code === 'ECONNABORTED') {
        return { message: 'The upload timed out. Please check your connection and try again.' };
    }

    return { message: CONNECTION_UPLOAD_ERROR };
}

/**
 * Normalize an axios failure into the Inertia-style error dict the existing
 * UI code consumes. A thrown or rejected upload resolves to this shape.
 */
export function normalizeUploadError(error: unknown): Record<string, string> {
    if (axios.isAxiosError(error)) {
        return axiosUploadError(error);
    }

    return {
        message: error instanceof Error && error.message ? error.message : FALLBACK_UPLOAD_ERROR,
    };
}

function isRetryable(error: unknown): boolean {
    if (axios.isAxiosError(error)) {
        const status = error.response?.status;

        // Network failure (no response) or server-side hiccup: worth one retry.
        return status === undefined || (status >= 500 && status <= 599);
    }

    return false;
}

const RETRY_DELAY_MS = 1000;

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

export const photoUploadService = {
    async upload(payload: UploadPhotoPayload): Promise<UploadPhotoResult> {
        const formData = new FormData();
        formData.append('photo', payload.file);
        formData.append('category_selection_id', String(payload.categorySelectionId));
        formData.append('position', String(payload.position));
        if (payload.gps) {
            formData.append('gps_latitude', String(payload.gps.latitude));
            formData.append('gps_longitude', String(payload.gps.longitude));
            if (payload.gps.accuracy !== undefined) {
                formData.append('gps_accuracy', String(payload.gps.accuracy));
            }
        }
        formData.append(
            'captured_at',
            payload.capturedAt ?? new Date().toISOString().replace('T', ' ').slice(0, 19)
        );
        if (payload.notes) {
            formData.append('notes', payload.notes);
        }

        try {
            return await this.post(payload.packageId, payload.stage, formData);
        } catch (error) {
            if (!isRetryable(error)) {
                throw normalizeUploadError(error);
            }

            console.warn('Photo upload failed, retrying once…', error);
            await sleep(RETRY_DELAY_MS);

            try {
                return await this.post(payload.packageId, payload.stage, formData);
            } catch (retryError) {
                throw normalizeUploadError(retryError);
            }
        }
    },

    async post(packageId: number, stage: string, formData: FormData): Promise<UploadPhotoResult> {
        const response = await axios.post<{ success: boolean; data: UploadPhotoResult }>(
            route('photos.store', { package: packageId, stage }),
            formData,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );

        return response.data.data;
    },
};
