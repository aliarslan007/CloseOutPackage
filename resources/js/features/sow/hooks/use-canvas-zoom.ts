import * as React from 'react';

export interface ViewTransform {
    scale: number;
    offsetX: number;
    offsetY: number;
}

/**
 * §9.7 — two-finger pinch zoom + two-finger pan for touch input. Single
 * pointer stays free for drawing. Consumers feed pointer events in and apply
 * the returned view transform.
 */
export function useCanvasZoom() {
    const pointers = React.useRef(new Map<number, { x: number; y: number }>());
    const pinchStart = React.useRef<{
        distance: number;
        midX: number;
        midY: number;
        scale: number;
        offsetX: number;
        offsetY: number;
    } | null>(null);

    const addPointer = (id: number, x: number, y: number) => {
        pointers.current.set(id, { x, y });
    };

    const movePointer = (id: number, x: number, y: number) => {
        const pointer = pointers.current.get(id);
        if (pointer) {
            pointer.x = x;
            pointer.y = y;
        }
    };

    const removePointer = (id: number) => {
        pointers.current.delete(id);
    };

    /** Number of currently tracked pointers — >1 means a zoom gesture is active. */
    const pointerCount = (): number => pointers.current.size;

    /**
     * Call on pointer move with the current view. Returns an updated view
     * while two pointers are active, otherwise null.
     */
    const gesture = (view: ViewTransform): ViewTransform | null => {
        const points = [...pointers.current.values()];
        if (points.length < 2) {
            pinchStart.current = null;
            return null;
        }

        const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
        const midX = (points[0].x + points[1].x) / 2;
        const midY = (points[0].y + points[1].y) / 2;

        if (!pinchStart.current) {
            pinchStart.current = {
                distance,
                midX,
                midY,
                scale: view.scale,
                offsetX: view.offsetX,
                offsetY: view.offsetY,
            };
            return null;
        }

        const start = pinchStart.current;
        const nextScale = Math.min(4, Math.max(0.1, start.scale * (distance / start.distance)));
        // Keep the mid-point stable under the fingers.
        const logicalX = (start.midX - start.offsetX) / start.scale;
        const logicalY = (start.midY - start.offsetY) / start.scale;

        return {
            scale: nextScale,
            offsetX: midX - logicalX * nextScale,
            offsetY: midY - logicalY * nextScale,
        };
    };

    return { addPointer, movePointer, removePointer, pointerCount, gesture };
}

export function computeNextZoomView(
    prev: ViewTransform,
    factor: number,
    pointer?: { x: number; y: number },
    minScale = 0.5,
    maxScale = 4
): ViewTransform {
    const nextScale = Math.min(maxScale, Math.max(minScale, prev.scale * factor));
    if (!pointer) return { ...prev, scale: nextScale };
    const logicalX = (pointer.x - prev.offsetX) / prev.scale;
    const logicalY = (pointer.y - prev.offsetY) / prev.scale;
    return {
        scale: nextScale,
        offsetX: pointer.x - logicalX * nextScale,
        offsetY: pointer.y - logicalY * nextScale,
    };
}

export function computeFittedView(
    containerWidth: number,
    containerHeight: number,
    baseWidth: number,
    logicalHeight: number,
    maxScale = 4
): ViewTransform {
    const scale = Math.min(containerWidth / baseWidth, containerHeight / logicalHeight, maxScale);
    return {
        scale,
        offsetX: (containerWidth - baseWidth * scale) / 2,
        offsetY: (containerHeight - logicalHeight * scale) / 2,
    };
}

/** Per-pixel zoom sensitivity — tuned so a trackpad gesture is a gentle nudge. */
const WHEEL_ZOOM_SENSITIVITY = 0.0012;
/** Rough pixel equivalents for line/page delta modes (used by some browsers). */
const WHEEL_DELTA_LINE = 16;
const WHEEL_DELTA_PAGE = 400;
/** Caps a single runaway event so one flick cannot teleport the zoom level. */
const MAX_WHEEL_DELTA = 300;

/**
 * Trackpads emit many small wheel deltas while a mouse wheel sends one large
 * delta per notch, so a fixed per-event factor (formerly ×1.1) made trackpad
 * zoom far too fast and hard to land. Scale the factor exponentially with the
 * normalized delta instead: small gestures nudge the zoom, larger ones move it
 * faster, and repeated events compose smoothly and linearly.
 */
function wheelDeltaUnit(deltaMode: number): number {
    if (deltaMode === 1) return WHEEL_DELTA_LINE;
    if (deltaMode === 2) return WHEEL_DELTA_PAGE;
    return 1;
}

export function wheelZoomFactor(evt: WheelEvent): number {
    const pixelDelta = evt.deltaY * wheelDeltaUnit(evt.deltaMode);
    const delta = Math.max(-MAX_WHEEL_DELTA, Math.min(MAX_WHEEL_DELTA, pixelDelta));
    return Math.exp(-delta * WHEEL_ZOOM_SENSITIVITY);
}
