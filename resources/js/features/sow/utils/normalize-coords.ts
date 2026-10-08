/** §6.5 — pixel ↔ normalized coordinate helpers. */

/** Position: normalized relative to image render dimensions. */
export const toNorm = (px: number, dim: number): number => parseFloat((px / dim).toFixed(6));

/**
 * Size: normalized relative to canvas render width (baseWidth).
 * Server reconstructs px = normValue × source_image_width.
 */
export const sizeToNorm = (px: number, baseWidth: number): number =>
    parseFloat((px / baseWidth).toFixed(6));

export const sizeToPixel = (norm: number, imageWidth: number): number =>
    Math.round(norm * imageWidth);
