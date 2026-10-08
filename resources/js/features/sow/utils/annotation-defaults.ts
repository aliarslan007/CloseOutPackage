import type { Annotation, AnnotationShape } from '../types/annotation.types';
import { sizeToNorm } from './normalize-coords';

export const WHITE_COLOR = '#FFFFFF';
export const BLACK_COLOR = '#000000';

export const PURE_YELLOW_COLOR = '#FFFF00';

/** §6.1 — color swatches (red, yellow, green, black, white). */
export const COLOR_SWATCHES = [
    '#E53E3E',
    PURE_YELLOW_COLOR,
    '#38A169',
    BLACK_COLOR,
    WHITE_COLOR,
] as const;

/**
 * Mirrors the backend `layer.nodes.*.text` rule (max:200) so callouts
 * have sufficient room for site instructions without covering the photo.
 */
export const MAX_TEXT_LENGTH = 200;
export const MAX_BOXES_PER_PHOTO = 5;

export const DEFAULT_COLOR = '#E53E3E';
export const DEFAULT_STROKE_WIDTH_PX = 4; // slider range 1–8 px (at baseWidth)
export const DEFAULT_OPACITY = 1;
export const DEFAULT_FONT_SIZE_PX = 26;
export const MIN_OPACITY = 0.2;
export const MAX_OPACITY = 1;

/**
 * Perceived luminance calculation (ITU-R BT.709) to select high-contrast halo color.
 * On dark colors (e.g. black), returns white '#FFFFFF'. On bright/saturated colors (red, yellow, green, white), returns black '#000000'.
 */
export function getContrastHaloColor(hexColor: string): string {
    const cleanHex = hexColor.replace('#', '');
    if (cleanHex.length === 6) {
        const r = Number.parseInt(cleanHex.substring(0, 2), 16);
        const g = Number.parseInt(cleanHex.substring(2, 4), 16);
        const b = Number.parseInt(cleanHex.substring(4, 6), 16);
        const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
        return luminance < 0.25 ? '#FFFFFF' : '#000000';
    }
    return '#000000';
}

const clamp = (value: number, min: number, max: number): number =>
    Math.min(max, Math.max(min, value));

/**
 * Build a fresh annotation of the given type, positioned at the given
 * normalized point (defaults applied from the active editor settings).
 */
export function defaultAnnotation(
    type: AnnotationShape,
    baseWidth: number,
    options: {
        id: string;
        normX: number;
        normY: number;
        color: string;
        strokeWidthPx: number;
        opacity: number;
    }
): Annotation {
    const { id, color, opacity } = options;
    const strokeWidth = sizeToNorm(options.strokeWidthPx, baseWidth);

    switch (type) {
        case 'box':
            return {
                id,
                type: 'box',
                zIndex: 0,
                strokeColor: color,
                fillColor: WHITE_COLOR,
                normStrokeWidth: strokeWidth,
                opacity,
                normX: options.normX,
                normY: options.normY,
                normWidth: 0,
                normHeight: 0,
                text: '',
                normFontSize: sizeToNorm(DEFAULT_FONT_SIZE_PX, baseWidth),
                textColor: BLACK_COLOR,
                borderRadius: 2,
                dashed: false,
            };
        case 'arrow':
            return {
                id,
                type: 'arrow',
                zIndex: 0,
                strokeColor: color,
                fillColor: color,
                normStrokeWidth: strokeWidth,
                opacity,
                normPoints: [options.normX, options.normY, options.normX, options.normY],
                arrowheadStyle: 'solid',
                dashed: false,
            };
        case 'dashed_line':
            return {
                id,
                type: 'dashed_line',
                zIndex: 0,
                strokeColor: color,
                fillColor: color,
                normStrokeWidth: strokeWidth,
                opacity,
                normPoints: [options.normX, options.normY, options.normX, options.normY],
                dashPattern: [8, 6],
            };
        case 'dot':
            return {
                id,
                type: 'dot',
                zIndex: 0,
                strokeColor: color,
                fillColor: color,
                normStrokeWidth: strokeWidth,
                opacity,
                normX: options.normX,
                normY: options.normY,
                normRadius: sizeToNorm(6, baseWidth),
            };
        case 'text_label':
            return {
                id,
                type: 'text_label',
                zIndex: 0,
                strokeColor: color,
                fillColor: color,
                normStrokeWidth: strokeWidth,
                opacity,
                normX: options.normX,
                normY: options.normY,
                text: '',
                normFontSize: sizeToNorm(DEFAULT_FONT_SIZE_PX, baseWidth),
                textColor: '#FFFFFF',
                fontWeight: 'bold',
            };
    }
}

export const clampOpacity = (value: number): number => clamp(value, MIN_OPACITY, MAX_OPACITY);

export const clampStrokeWidth = (value: number): number => clamp(value, 1, 8);
