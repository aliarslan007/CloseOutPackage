export interface CompressionResult {
    file: File;
    originalSize: number;
    compressedSize: number;
    quality: number;
}

/**
 * Product rule: supervisors may pick photos up to this size. Anything bigger
 * is rejected up front; anything within it is compressed before upload.
 */
export const MAX_PHOTO_UPLOAD_MB = 7;

export const MIME_IMAGE_JPEG = 'image/jpeg';
export const MIME_IMAGE_PNG = 'image/png';
export const MIME_IMAGE_WEBP = 'image/webp';
export const MIME_IMAGE_HEIC = 'image/heic';

/**
 * Must stay in sync with the backend rule: mimes:jpg,jpeg,png,webp,heic
 * (StorePhotoRequest / ReplacePhotoRequest / StoreSitePhotoRequest).
 */
export const SUPPORTED_PHOTO_TYPES = [
    MIME_IMAGE_JPEG,
    MIME_IMAGE_PNG,
    MIME_IMAGE_WEBP,
    MIME_IMAGE_HEIC,
];

/**
 * Avatars are stricter: mimes:jpeg,png,jpg,webp at 2MB (ValidatesProfileFields).
 */
export const SUPPORTED_AVATAR_TYPES = [MIME_IMAGE_JPEG, MIME_IMAGE_PNG, MIME_IMAGE_WEBP];
export const MAX_AVATAR_UPLOAD_MB = 2;

const EXTENSIONS_BY_TYPE: Record<string, string[]> = {
    [MIME_IMAGE_JPEG]: ['jpg', 'jpeg'],
    [MIME_IMAGE_PNG]: ['png'],
    [MIME_IMAGE_WEBP]: ['webp'],
    [MIME_IMAGE_HEIC]: ['heic'],
};

const TYPE_LABELS: Record<string, string> = {
    [MIME_IMAGE_JPEG]: 'JPG',
    [MIME_IMAGE_PNG]: 'PNG',
    [MIME_IMAGE_WEBP]: 'WEBP',
    [MIME_IMAGE_HEIC]: 'HEIC',
};

function humanizeTypes(allowedTypes: string[]): string {
    const labels = allowedTypes.map((type) => TYPE_LABELS[type] ?? type);
    const last = labels.pop() ?? '';

    return labels.length > 0 ? `${labels.join(', ')} or ${last}` : last;
}

export type PreparedPhoto =
    | { ok: true; file: File }
    | { ok: false; reason: 'too-large' | 'unsupported-type' };

export interface PreparedPhotoOptions {
    /** Size ceiling in megabytes. Defaults to the package/site photo rule. */
    maxMb?: number;
    /** MIME allowlist. Defaults to the package/site photo types. */
    allowedTypes?: string[];
    /** Longest canvas edge produced by compression. */
    maxSideLength?: number;
}

function isSupportedPhoto(file: File, allowedTypes: string[]): boolean {
    if (file.type) {
        return allowedTypes.includes(file.type);
    }

    // Some mobile pickers send an empty MIME type — fall back to the extension.
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    return allowedTypes.some((type) => EXTENSIONS_BY_TYPE[type]?.includes(extension));
}

/**
 * Human-readable explanation for a rejected file, shared by every upload UI.
 */
export function rejectedPhotoMessage(
    file: File,
    reason: Exclude<PreparedPhoto, { ok: true }>['reason'],
    options: PreparedPhotoOptions = {},
): string {
    if (reason === 'too-large') {
        return `"${file.name}" is larger than ${options.maxMb ?? MAX_PHOTO_UPLOAD_MB}MB. Please choose a smaller image.`;
    }

    return `"${file.name}" is not a supported image. Please use ${humanizeTypes(options.allowedTypes ?? SUPPORTED_PHOTO_TYPES)}.`;
}

/**
 * Single gate for every image upload in the app: type check, size check,
 * then canvas compression. Camera captures pass through untouched via
 * `{ skip: true }` (they are already valid compressed JPEGs).
 */
export async function preparePhotoForUpload(
    rawFile: File,
    options: PreparedPhotoOptions & { skip?: boolean } = {},
): Promise<PreparedPhoto> {
    if (options.skip) {
        return { ok: true, file: rawFile };
    }

    const allowedTypes = options.allowedTypes ?? SUPPORTED_PHOTO_TYPES;

    if (!isSupportedPhoto(rawFile, allowedTypes)) {
        return { ok: false, reason: 'unsupported-type' };
    }

    if (rawFile.size > (options.maxMb ?? MAX_PHOTO_UPLOAD_MB) * 1024 * 1024) {
        return { ok: false, reason: 'too-large' };
    }

    const result = await CompressionService.compressImage(
        rawFile,
        options.maxSideLength ?? 1920,
    );

    return { ok: true, file: result.file };
}

export class CompressionService {
    public static async compressImage(
        file: File,
        maxSideLength: number = 1920,
        quality: number = 0.85,
    ): Promise<CompressionResult> {
        if (!file.type.startsWith('image/')) {
            return this.unchanged(file);
        }

        try {
            const dataUrl = await this.readAsDataUrl(file);
            const image = await this.loadImage(dataUrl);
            const blob = await this.canvasToBlob(this.renderToCanvas(image, maxSideLength), quality);

            const compressedFile = new File([blob], file.name, {
                type: MIME_IMAGE_JPEG,
                lastModified: Date.now(),
            });

            return {
                file: compressedFile,
                originalSize: file.size,
                compressedSize: compressedFile.size,
                quality,
            };
        } catch {
            return this.unchanged(file);
        }
    }

    private static unchanged(file: File): CompressionResult {
        return {
            file,
            originalSize: file.size,
            compressedSize: file.size,
            quality: 1,
        };
    }

    private static readAsDataUrl(file: File): Promise<string> {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = () => {
                if (typeof reader.result === 'string') {
                    resolve(reader.result);
                    return;
                }

                reject(new Error('Failed to read the image file.'));
            };

            reader.onerror = () => reject(new Error('Failed to read the image file.'));

            reader.readAsDataURL(file);
        });
    }

    private static loadImage(src: string): Promise<HTMLImageElement> {
        return new Promise((resolve, reject) => {
            const image = new Image();

            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('Failed to load the image.'));

            image.src = src;
        });
    }

    private static renderToCanvas(image: HTMLImageElement, maxSideLength: number): HTMLCanvasElement {
        const { width, height } = this.scaledDimensions(image.width, image.height, maxSideLength);

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');

        if (context !== null) {
            context.drawImage(image, 0, 0, width, height);
        }

        return canvas;
    }

    private static scaledDimensions(width: number, height: number, maxSideLength: number): { width: number; height: number } {
        if (width <= maxSideLength && height <= maxSideLength) {
            return { width, height };
        }

        if (width > height) {
            return {
                width: maxSideLength,
                height: Math.round((height * maxSideLength) / width),
            };
        }

        return {
            width: Math.round((width * maxSideLength) / height),
            height: maxSideLength,
        };
    }

    private static canvasToBlob(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
        return new Promise((resolve, reject) => {
            canvas.toBlob(
                (blob) => {
                    if (blob !== null) {
                        resolve(blob);
                        return;
                    }

                    reject(new Error('Failed to encode the compressed image.'));
                },
                MIME_IMAGE_JPEG,
                quality,
            );
        });
    }
}

export default CompressionService;
