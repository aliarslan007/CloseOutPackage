/** §6.2 — annotation data model. FREEHAND deliberately excluded from v1. */

export type AnnotationShape = 'arrow' | 'box' | 'dashed_line' | 'dot' | 'text_label';

export type EditorTool = AnnotationShape | 'select' | 'pan' | 'eraser';

export interface BaseAnnotation {
    id: string; // uuid v4 (crypto.randomUUID)
    type: AnnotationShape;
    zIndex: number; // compositing draws in zIndex ASC order
    strokeColor: string;
    fillColor: string;
    /** Fraction of baseWidth (e.g. 4px / 1200px = 0.0033). */
    normStrokeWidth: number;
    /** 0.2 – 1.0 (matches slider 20–100%). */
    opacity: number;
}

/** Point-anchored shapes carry normX/normY; line-based shapes do not. */
export interface PointAnchoredAnnotation extends BaseAnnotation {
    normX: number; // 0.0 – 1.0
    normY: number; // 0.0 – 1.0
}

export interface BoxAnnotation extends PointAnchoredAnnotation {
    type: 'box';
    normWidth: number;
    normHeight: number;
    text?: string | null;
    /** Fraction of baseWidth. */
    normFontSize: number;
    textColor: string;
    /** Only rendered when Imagick is available server-side. */
    borderRadius: number;
    dashed: boolean;
}

export interface ArrowAnnotation extends BaseAnnotation {
    type: 'arrow';
    /** [x0, y0, x1, y1] — position from normPoints only. */
    normPoints: number[];
    arrowheadStyle: 'solid' | 'open';
    dashed: boolean;
}

export interface DashedLineAnnotation extends BaseAnnotation {
    type: 'dashed_line';
    /** Multi-segment [x0, y0, x1, y1, …]. */
    normPoints: number[];
    dashPattern: [number, number];
}

export interface DotAnnotation extends PointAnchoredAnnotation {
    type: 'dot';
    /** Fraction of baseWidth. */
    normRadius: number;
}

export interface TextLabelAnnotation extends PointAnchoredAnnotation {
    type: 'text_label';
    text?: string | null;
    /** Fraction of baseWidth. */
    normFontSize: number;
    textColor: string;
    fontWeight: 'normal' | 'bold';
}

export type Annotation =
    BoxAnnotation | ArrowAnnotation | DashedLineAnnotation | DotAnnotation | TextLabelAnnotation;

/** The layer wrapper carries baseWidth so the server can scale size fields. */
export interface AnnotationLayer {
    baseWidth: number;
    nodes: Annotation[];
}
