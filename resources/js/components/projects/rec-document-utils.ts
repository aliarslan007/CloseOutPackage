/**
 * @deprecated Part of the legacy REC card. Superseded by `@/features/project-documents/utils`;
 * delete together with `rec-document-card.tsx`.
 */
import type { ProjectRecDocument } from '@/components/projects/project-show-types';

/** Client-side mirror of StoreRecDocumentRequest — keep both in sync. */
export const MAX_REC_SIZE_MB = 20;
export const MAX_REC_SIZE_BYTES = MAX_REC_SIZE_MB * 1024 * 1024;
export const REC_ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp';
const ALLOWED_EXTENSIONS = new Set(['pdf', 'jpg', 'jpeg', 'png', 'webp']);

export function isPdf(doc: Pick<ProjectRecDocument, 'mime_type' | 'name'>): boolean {
    if (doc.mime_type) {
        return doc.mime_type === 'application/pdf';
    }
    return extensionOf(doc.name) === 'pdf';
}

export function extensionOf(name: string): string {
    return name.split('.').pop()?.toLowerCase() ?? '';
}

export function isAllowedRec(file: File): boolean {
    if (file.type === 'application/pdf' || file.type.startsWith('image/')) {
        return ALLOWED_EXTENSIONS.has(extensionOf(file.name));
    }
    return ALLOWED_EXTENSIONS.has(extensionOf(file.name));
}

/** Returns an error string, or null when the file is acceptable. */
export function validateRecFile(file: File): string | null {
    if (!isAllowedRec(file)) {
        return `"${file.name}" is not a supported format. Upload a PDF, JPG, PNG, or WebP.`;
    }
    if (file.size > MAX_REC_SIZE_BYTES) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
        return `"${file.name}" (${sizeMb} MB) exceeds the ${MAX_REC_SIZE_MB} MB limit.`;
    }
    return null;
}

/** "2026, September, 22" style timestamp already formatted server-side; passthrough fallback. */
export function recFileLabel(doc: Pick<ProjectRecDocument, 'name'>): string {
    return doc.name?.trim() || 'Untitled document';
}
