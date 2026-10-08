import type { MediaLibraryConfig, ValidationResult } from '../types/media-library.types';
import { formatFileSize } from './format-file-size';

function isJpeg(bytes: Uint8Array): boolean {
    return bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff;
}

function isPng(bytes: Uint8Array): boolean {
    return (
        bytes.length >= 8 &&
        bytes[0] === 0x89 &&
        bytes[1] === 0x50 &&
        bytes[2] === 0x4e &&
        bytes[3] === 0x47 &&
        bytes[4] === 0x0d &&
        bytes[5] === 0x0a &&
        bytes[6] === 0x1a &&
        bytes[7] === 0x0a
    );
}

function isGif(bytes: Uint8Array): boolean {
    return (
        bytes.length >= 4 &&
        bytes[0] === 0x47 &&
        bytes[1] === 0x49 &&
        bytes[2] === 0x46 &&
        bytes[3] === 0x38
    );
}

function isWebp(bytes: Uint8Array): boolean {
    if (bytes.length < 12) return false;
    const isRiff =
        bytes[0] === 0x52 && bytes[1] === 0x49 && bytes[2] === 0x46 && bytes[3] === 0x46;
    const isWebpSignature =
        bytes[8] === 0x57 && bytes[9] === 0x45 && bytes[10] === 0x42 && bytes[11] === 0x50;
    return isRiff && isWebpSignature;
}

function isHeicOrHeif(bytes: Uint8Array): boolean {
    if (bytes.length < 12) return false;
    const isFtyp =
        bytes[4] === 0x66 && bytes[5] === 0x74 && bytes[6] === 0x79 && bytes[7] === 0x70;
    return isFtyp;
}

export function detectImageMimeFromMagicBytes(bytes: Uint8Array): string | null {
    if (isJpeg(bytes)) return 'image/jpeg';
    if (isPng(bytes)) return 'image/png';
    if (isGif(bytes)) return 'image/gif';
    if (isWebp(bytes)) return 'image/webp';
    if (isHeicOrHeif(bytes)) return 'image/heic';
    return null;
}

export async function readFirstBytes(file: File, length = 16): Promise<Uint8Array> {
    const slice = file.slice(0, length);
    const buffer = await slice.arrayBuffer();
    return new Uint8Array(buffer);
}

export async function validateFileClient(
    file: File,
    config: MediaLibraryConfig,
): Promise<ValidationResult> {
    if (file.size <= 0) {
        return {
            valid: false,
            error: `${file.name} is empty.`,
        };
    }

    if (file.size > config.max_file_bytes) {
        const limitStr = formatFileSize(config.max_file_bytes);
        return {
            valid: false,
            error: `${file.name} (${formatFileSize(file.size)}) exceeds the maximum allowed limit of ${limitStr}.`,
        };
    }

    if (config.allowed_mimes.length > 0 && file.type) {
        const isMimeAllowed = config.allowed_mimes.includes(file.type.toLowerCase());
        if (!isMimeAllowed) {
            return {
                valid: false,
                error: `${file.name} has an unsupported format (${file.type}).`,
            };
        }
    }

    try {
        const bytes = await readFirstBytes(file, 16);
        const detectedMime = detectImageMimeFromMagicBytes(bytes);

        if (!detectedMime) {
            return {
                valid: false,
                error: `${file.name} is not a recognized or valid image file.`,
            };
        }

        if (config.allowed_mimes.length > 0 && !config.allowed_mimes.includes(detectedMime)) {
            return {
                valid: false,
                error: `${file.name} header does not match allowed image formats.`,
            };
        }
    } catch {
        return {
            valid: false,
            error: `Failed to inspect file contents for ${file.name}.`,
        };
    }

    return { valid: true };
}
