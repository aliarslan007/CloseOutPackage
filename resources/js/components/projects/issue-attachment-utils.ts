import { preparePhotoForUpload } from '@/services/compression.service';

/**
 * Shared client-side rules for issue attachments (Report New Issue dialog
 * and post-create editing). Mirrors IssueAttachmentService on the backend —
 * keep both sides in sync.
 */
export const MAX_FILE_SIZE_MB = 5;
export const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;
export const MAX_ATTACHMENTS = 10;
export const ISSUE_ATTACHMENT_ACCEPT =
    'image/*,video/*,.mp4,.mov,.avi,.webm,.mkv,.heic,.heif';

const ALLOWED_EXTENSIONS = new Set([
    'jpg',
    'jpeg',
    'png',
    'webp',
    'heic',
    'heif',
    'mp4',
    'mov',
    'avi',
    'webm',
    'mkv',
]);
const VIDEO_EXTENSIONS = new Set(['mp4', 'mov', 'avi', 'webm', 'mkv']);

/**
 * First validation message for the attachments field, whether the server keyed
 * it `attachments` or `attachments.N`. The create dialog and the post-create
 * uploader surface the same errors, so they share this.
 */
export function firstAttachmentError(
    errors: Record<string, string | undefined>
): string | undefined {
    return Object.keys(errors)
        .filter((key) => key.startsWith('attachments'))
        .map((key) => errors[key])
        .find(Boolean);
}

export function isAllowedAttachment(file: File): boolean {
    if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
        return true;
    }
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
    return ALLOWED_EXTENSIONS.has(ext);
}

export function isVideoFile(file: File): boolean {
    return (
        file.type.startsWith('video/') ||
        VIDEO_EXTENSIONS.has(file.name.split('.').pop()?.toLowerCase() ?? '')
    );
}

/**
 * Validates one file and compresses photos on the fly so they fit the 5MB cap.
 */
export async function processSingleFile(
    file: File
): Promise<{ ok: true; file: File } | { ok: false; error: string }> {
    if (!isAllowedAttachment(file)) {
        return { ok: false, error: `"${file.name}" is not a supported image or video format.` };
    }

    if (isVideoFile(file)) {
        if (file.size > MAX_FILE_SIZE_BYTES) {
            const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
            return {
                ok: false,
                error: `"${file.name}" (${sizeMb}MB) exceeds the ${MAX_FILE_SIZE_MB}MB limit for videos.`,
            };
        }
        return { ok: true, file };
    }

    try {
        const prepared = await preparePhotoForUpload(file, {
            maxMb: 15,
            maxSideLength: 1920,
        });

        const finalFile = prepared.ok ? prepared.file : file;
        if (finalFile.size > MAX_FILE_SIZE_BYTES) {
            const sizeMb = (finalFile.size / (1024 * 1024)).toFixed(1);
            return {
                ok: false,
                error: `"${file.name}" (${sizeMb}MB) exceeds the ${MAX_FILE_SIZE_MB}MB limit after compression.`,
            };
        }
        return { ok: true, file: finalFile };
    } catch {
        if (file.size > MAX_FILE_SIZE_BYTES) {
            const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
            return {
                ok: false,
                error: `"${file.name}" (${sizeMb}MB) exceeds the ${MAX_FILE_SIZE_MB}MB limit.`,
            };
        }
        return { ok: true, file };
    }
}
