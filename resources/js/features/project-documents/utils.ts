import { FileImage, FileSpreadsheet, FileText, type LucideIcon } from 'lucide-react';
import { REC_COLLECTION } from './constants';
import type { DocumentCategory } from './types';

/** Client-side mirror of StoreProjectDocumentRequest — keep both in sync. */
export const MAX_DOCUMENT_SIZE_MB = 25;
export const MAX_DOCUMENT_SIZE_BYTES = MAX_DOCUMENT_SIZE_MB * 1024 * 1024;

export const ACCEPT_ATTR =
    'application/pdf,.pdf,' +
    'application/msword,.doc,' +
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document,.docx,' +
    'application/vnd.ms-excel,.xls,' +
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.xlsx,' +
    'application/vnd.ms-powerpoint,.ppt,' +
    'application/vnd.openxmlformats-officedocument.presentationml.presentation,.pptx,' +
    'text/plain,.txt';

const ALLOWED_EXTENSIONS = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt']);

export function extensionOf(name: string): string {
    return name.split('.').pop()?.toLowerCase() ?? '';
}

export interface FileTypeMeta {
    Icon: LucideIcon;
    colorClass: string;
}

/** File-type icon + colour used by the dense document rows. */
export function fileTypeMeta(name: string): FileTypeMeta {
    switch (extensionOf(name)) {
        case 'pdf':
            return { Icon: FileText, colorClass: 'text-red-500' };
        case 'doc':
        case 'docx':
            return { Icon: FileText, colorClass: 'text-blue-500' };
        case 'xls':
        case 'xlsx':
            return { Icon: FileSpreadsheet, colorClass: 'text-green-600' };
        case 'ppt':
        case 'pptx':
            return { Icon: FileImage, colorClass: 'text-orange-500' };
        default:
            return { Icon: FileText, colorClass: 'text-slate-400' };
    }
}

/** Returns an error string, or null when the file is acceptable. */
export function validateDocumentFile(file: File): string | null {
    if (!ALLOWED_EXTENSIONS.has(extensionOf(file.name))) {
        return `"${file.name}" is not a supported format. Upload a PDF, Word, Excel, PowerPoint, or text file.`;
    }

    if (file.size > MAX_DOCUMENT_SIZE_BYTES) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
        return `"${file.name}" (${sizeMb} MB) exceeds the ${MAX_DOCUMENT_SIZE_MB} MB limit.`;
    }

    return null;
}

/**
 * REC is served by its dedicated routes; every other category goes through the
 * generic ProjectDocumentController routes.
 */
export function documentStoreUrl(projectKey: number | string, category: DocumentCategory): string {
    return category.collection === REC_COLLECTION
        ? route('projects.rec-documents.store', projectKey)
        : route('projects.documents.store', [projectKey, category.slug]);
}

export function documentDestroyUrl(
    projectKey: number | string,
    category: DocumentCategory,
    mediaId: number
): string {
    return category.collection === REC_COLLECTION
        ? route('projects.rec-documents.destroy', [projectKey, mediaId])
        : route('projects.documents.destroy', [projectKey, category.slug, mediaId]);
}
