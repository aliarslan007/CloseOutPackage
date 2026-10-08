import { MAX_TEXT_LENGTH } from './annotation-defaults';
import type { Annotation, AnnotationLayer } from '../types/annotation.types';

/**
 * The canvas model (§6.2) uses camelCase; the server (§4.5) validates
 * snake_case keys. This module bridges the two directions.
 */
export type ApiAnnotationNode = Record<string, unknown>;

export interface ApiAnnotationLayer {
    base_width: number;
    nodes: ApiAnnotationNode[];
}

const NODE_KEYS: Array<[keyof Annotation | string, string]> = [
    ['id', 'id'],
    ['type', 'type'],
    ['zIndex', 'z_index'],
    ['opacity', 'opacity'],
    ['strokeColor', 'stroke_color'],
    ['fillColor', 'fill_color'],
    ['normStrokeWidth', 'norm_stroke_width'],
    ['normX', 'norm_x'],
    ['normY', 'norm_y'],
    ['normWidth', 'norm_width'],
    ['normHeight', 'norm_height'],
    ['borderRadius', 'border_radius'],
    ['dashed', 'dashed'],
    ['text', 'text'],
    ['normFontSize', 'norm_font_size'],
    ['textColor', 'text_color'],
    ['fontWeight', 'font_weight'],
    ['arrowheadStyle', 'arrowhead_style'],
    ['normPoints', 'norm_points'],
    ['dashPattern', 'dash_pattern'],
    ['normRadius', 'norm_radius'],
];

const SNAKE_TO_CAMEL: Record<string, string> = Object.fromEntries(
    NODE_KEYS.map(([camel, snake]) => [snake, camel])
);

function clampCoordinate(snake: string, value: unknown): unknown {
    if (typeof value !== 'number' || Number.isNaN(value)) {
        return value;
    }
    if (snake === 'norm_x' || snake === 'norm_width') {
        return Math.max(0, Math.min(1, value));
    }
    if (snake === 'norm_y' || snake === 'norm_height') {
        return Math.max(0, Math.min(5, value));
    }
    return value;
}

function sanitizePoints(value: unknown): unknown {
    if (!Array.isArray(value)) {
        return value;
    }
    return value.map((p) =>
        typeof p === 'number' && !Number.isNaN(p) ? Math.max(0, Math.min(5, p)) : p
    );
}

/** The server caps `text` at 500 chars — trim here so a stale draft cannot 422. */
function clampText(value: unknown): unknown {
    return typeof value === 'string' ? value.slice(0, MAX_TEXT_LENGTH) : value;
}

function serializeValue(snake: string, rawValue: unknown): unknown {
    if (snake === 'norm_points') {
        return sanitizePoints(rawValue);
    }
    if (snake === 'text') {
        return clampText(rawValue);
    }
    return clampCoordinate(snake, rawValue);
}

function serializeNode(node: Annotation): ApiAnnotationNode {
    const isLineBased = node.type === 'arrow' || node.type === 'dashed_line';
    const out: ApiAnnotationNode = {};
    for (const [camel, snake] of NODE_KEYS) {
        if (isLineBased && (snake === 'norm_x' || snake === 'norm_y')) {
            continue;
        }
        const rawValue = (node as unknown as Record<string, unknown>)[camel];
        if (rawValue !== undefined) {
            out[snake] = serializeValue(snake, rawValue);
        }
    }
    return out;
}

function parseNode(raw: ApiAnnotationNode): Annotation {
    const node: Record<string, unknown> = {};
    for (const [snake, value] of Object.entries(raw)) {
        const camel = SNAKE_TO_CAMEL[snake];
        node[camel ?? snake] = value;
    }
    return node as unknown as Annotation;
}

export function serializeLayer(layer: AnnotationLayer): ApiAnnotationLayer {
    return {
        base_width: layer.baseWidth,
        nodes: layer.nodes.map(serializeNode),
    };
}

/** Parses a server-stored layer; tolerates a raw node array (legacy/no wrapper). */
export function parseLayer(
    raw: ApiAnnotationLayer | ApiAnnotationNode[] | null | undefined
): AnnotationLayer {
    if (Array.isArray(raw)) {
        return { baseWidth: 1200, nodes: raw.map(parseNode) };
    }
    return {
        baseWidth: raw?.base_width ?? 1200,
        nodes: Array.isArray(raw?.nodes) ? raw.nodes.map(parseNode) : [],
    };
}
