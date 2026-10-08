import type Konva from 'konva';

export interface ExportCanvasOptions {
    mimeType?: string;
    quality?: number;
    pixelRatio?: number;
    width?: number;
    height?: number;
}

/**
 * Exports a Konva stage to an image Blob.
 * Captures the exact logical bounds (0, 0, width, height) of the image and annotations,
 * preventing viewport clipping, dimension mismatch, or cutoff shapes.
 * Safely restores previous viewport position, scale, and transformer visibility.
 */
export async function exportStageToBlob(
    stage: Konva.Stage,
    options: ExportCanvasOptions = {}
): Promise<Blob> {
    const {
        mimeType = 'image/jpeg',
        quality = 0.95,
    } = options;

    const originalScale = { x: stage.scaleX(), y: stage.scaleY() };
    const originalPos = { x: stage.x(), y: stage.y() };
    const originalSize = { width: stage.width(), height: stage.height() };

    // Find the background image node to get the exact logical bounds
    const imageNode = (stage.findOne('.sow-background-image') ?? stage.findOne('Image')) as Konva.Image | undefined;
    const width = options.width ?? (imageNode ? imageNode.width() : 1200);
    const height = options.height ?? (imageNode ? imageNode.height() : 900);

    // Calculate optimal pixelRatio matching original image's native resolution
    let pixelRatio = options.pixelRatio ?? 2;
    if (imageNode && typeof imageNode.image === 'function') {
        const imgObj = imageNode.image() as HTMLImageElement | undefined;
        if (imgObj && typeof imgObj.naturalWidth === 'number' && imgObj.naturalWidth > 0) {
            const nativeRatio = imgObj.naturalWidth / width;
            pixelRatio = Math.min(3, Math.max(1, Number(nativeRatio.toFixed(2))));
        }
    }

    // Temporarily hide transformer anchors/bounding boxes so they are never baked into pixels
    const transformer = stage.findOne('Transformer') as Konva.Transformer | undefined;
    const wasTransformerVisible = transformer ? transformer.visible() : false;
    if (transformer) {
        transformer.visible(false);
    }

    try {
        // Size and align stage to 1:1 logical image dimensions for lossless capture
        stage.position({ x: 0, y: 0 });
        stage.scale({ x: 1, y: 1 });
        stage.size({ width, height });
        stage.draw();

        return await new Promise<Blob>((resolve, reject) => {
            stage.toBlob({
                x: 0,
                y: 0,
                width,
                height,
                mimeType,
                quality,
                pixelRatio,
                callback: (blob) => {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Failed to export canvas image.'));
                    }
                },
            });
        });
    } finally {
        // Restore transformer visibility, position, scale, and stage viewport size
        if (transformer && wasTransformerVisible) {
            transformer.visible(true);
        }
        stage.position(originalPos);
        stage.scale(originalScale);
        stage.size(originalSize);
        stage.draw();
    }
}
