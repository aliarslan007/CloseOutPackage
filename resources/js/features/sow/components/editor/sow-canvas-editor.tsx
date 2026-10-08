import { Button } from '@/components/ui/button';
import { ImageOff, RotateCcw } from 'lucide-react';
import type Konva from 'konva';
import * as React from 'react';
import { Image as KonvaImage, Layer, Stage } from 'react-konva';
import useImage from 'use-image';
import type { SowEditorStore } from '../../stores/sow-editor.store';
import { SowAnnotationLayer } from './sow-annotation-layer';
import {
    SowCanvasToolHints,
    SowCanvasZoomControls,
    SowDashedPathDoneButton,
} from './sow-canvas-overlays';
import { SowCanvasPreviewLayer } from './sow-canvas-preview-layer';
import { computeFittedView, computeNextZoomView } from '../../hooks/use-canvas-zoom';
import { SowPropertiesPanel } from './sow-properties-panel';
import { SowTextInputOverlay } from './sow-text-input-overlay';
import { SowToolbar } from './sow-toolbar';
import { SowTransformerWrapper } from './sow-transformer-wrapper';
import { useCanvasDrawing } from './use-canvas-drawing';
import { useCanvasShortcuts } from './use-canvas-shortcuts';

const LOGICAL_WIDTH = 1200;

export interface SowCanvasEditorProps {
    imageId: string | number;
    imageUrl?: string | null;
    editor: SowEditorStore;
    readOnly?: boolean;
    /**
     * Stretch toolbar and canvas to fill the parent (dialog embed). Omit on
     * the SOW report pages, which keep their own viewport-derived height.
     */
    fill?: boolean;
    onStageReady?: (stage: Konva.Stage | null) => void;
}

export function SowCanvasEditor({
    imageId: _imageId,
    imageUrl,
    editor,
    readOnly = false,
    fill = false,
    onStageReady,
}: Readonly<SowCanvasEditorProps>) {
    const [retryCount, setRetryCount] = React.useState(0);
    // Busting the URL cache on retry forces use-image to re-request after a failure.
    const retrySeparator = imageUrl?.includes('?') ? '&' : '?';
    const imageUrlWithRetry = retryCount === 0 ? (imageUrl ?? '') : `${imageUrl ?? ''}${retrySeparator}_retry=${retryCount}`;
    const [image, imageStatus] = useImage(imageUrlWithRetry);
    const containerRef = React.useRef<HTMLDivElement>(null);
    const stageRef = React.useRef<Konva.Stage>(null);
    const layerRef = React.useRef<Konva.Layer>(null);

    React.useEffect(() => {
        onStageReady?.(stageRef.current);
    }, [onStageReady, imageStatus]);

    const [containerSize, setContainerSize] = React.useState({ width: 0, height: 0 });
    const [editingTextId, setEditingTextId] = React.useState<string | null>(null);
    const [view, setView] = React.useState({ scale: 1, offsetX: 0, offsetY: 0 });

    const logicalHeight = React.useMemo(() => {
        if (!image || !image.width || !image.height) return 900;
        return Math.round((image.height / image.width) * LOGICAL_WIDTH);
    }, [image]);

    // Fit on container resize or image load
    React.useEffect(() => {
        const el = containerRef.current;
        if (!el) return;
        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (!entry) return;
            const { width, height } = entry.contentRect;
            setContainerSize({ width, height });
            setView(computeFittedView(width, height, LOGICAL_WIDTH, logicalHeight));
        });
        observer.observe(el);
        return () => observer.disconnect();
    }, [logicalHeight]);

    const {
        preview,
        setPreview,
        onPointerDown,
        onPointerMove,
        onPointerUp,
        onContextMenu,
        onDblClick,
        onWheel,
        commitDashed,
    } = useCanvasDrawing({
        readOnly,
        editor,
        baseWidth: LOGICAL_WIDTH,
        logicalHeight,
        containerSize,
        editingTextId,
        view,
        setView,
    });

    useCanvasShortcuts({
        readOnly,
        preview,
        setPreview,
        editor,
        commitDashed,
    });

    const selectedNode = React.useMemo(() => {
        if (!editor.selectedId) return null;
        return editor.layer.nodes.find((n) => n.id === editor.selectedId) ?? null;
    }, [editor.selectedId, editor.layer.nodes]);

    const editingNode = React.useMemo(() => {
        if (!editingTextId) return null;
        return editor.layer.nodes.find((n) => n.id === editingTextId) ?? null;
    }, [editingTextId, editor.layer.nodes]);

    const overlayRect = React.useMemo(() => {
        if (!editingNode || editingNode.type !== 'box') return null;
        const normW = editingNode.normWidth ?? 0.1;
        const normH = editingNode.normHeight ?? 0.05;
        const width = normW * LOGICAL_WIDTH * view.scale;
        const height = normH * LOGICAL_WIDTH * view.scale;
        const stroke = (editingNode.normStrokeWidth ?? 0.003) * LOGICAL_WIDTH * view.scale;
        const left = view.offsetX + editingNode.normX * LOGICAL_WIDTH * view.scale + stroke;
        const top = view.offsetY + editingNode.normY * LOGICAL_WIDTH * view.scale + stroke;
        return {
            rect: {
                left,
                top,
                width: Math.max(80, width - stroke * 2),
                height: Math.max(40, height - stroke * 2),
            },
            fontSize: (editingNode.normFontSize ?? 0.015) * LOGICAL_WIDTH * view.scale,
            textColor: editingNode.textColor ?? '#FFFFFF',
            borderRadius: editingNode.borderRadius ?? 4,
            initialValue: editingNode.text ?? '',
        };
    }, [editingNode, view]);

    // ponytail: fixed 100vh reserve for report-page chrome (topbar, header, tabs, photo bar); min-h only floors the tool, not a layout guarantee on short viewports
    const canvasHeightClass = fill ? 'h-full' : 'h-[calc(100vh-300px)] min-h-[360px] max-h-[860px]';

    const renderCanvasBody = () => {
        if (imageStatus === 'failed') {
            return (
                <div className="flex h-full flex-col items-center justify-center gap-3 text-sm text-muted-foreground">
                    <ImageOff className="size-6 text-muted-foreground/50" />
                    <p>Photo could not be loaded.</p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setRetryCount((n) => n + 1)}
                        className="h-8 cursor-pointer text-xs"
                    >
                        <RotateCcw className="size-3.5" /> Retry
                    </Button>
                </div>
            );
        }

        if (imageStatus === 'loading' || containerSize.width === 0) {
            return (
                <div className="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground">
                    <span className="size-2 animate-ping rounded-full bg-primary" />
                    Loading photo…
                </div>
            );
        }

        return (
            <Stage
                ref={stageRef}
                width={containerSize.width}
                height={containerSize.height}
                scaleX={view.scale}
                scaleY={view.scale}
                x={view.offsetX}
                y={view.offsetY}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onDblClick={onDblClick}
                onContextMenu={onContextMenu}
                onWheel={onWheel}
                style={{ touchAction: 'none' }}
            >
                <Layer listening={false}>
                    {image && (
                        <KonvaImage
                            name="sow-background-image"
                            image={image}
                            width={LOGICAL_WIDTH}
                            height={logicalHeight}
                        />
                    )}
                </Layer>

                <Layer ref={layerRef} listening={!readOnly}>
                    <SowAnnotationLayer
                        layer={editor.layer}
                        selectedId={readOnly ? null : editor.selectedId}
                        onSelect={(id) => {
                            if (editor.activeTool === 'eraser') {
                                editor.deleteNode(id);
                                editor.setSelectedId(null);
                            } else if (editor.activeTool === 'select') {
                                editor.setSelectedId(id);
                            }
                        }}
                        onUpdate={readOnly ? () => {} : editor.updateNode}
                        onEditText={readOnly ? () => {} : setEditingTextId}
                    />
                    {!readOnly && (
                        <SowTransformerWrapper
                            selectedNode={selectedNode}
                            layerRef={layerRef}
                            baseWidth={LOGICAL_WIDTH}
                            logicalHeight={logicalHeight}
                            onTransformEnd={editor.updateNode}
                        />
                    )}
                </Layer>

                {!readOnly && (
                    <SowCanvasPreviewLayer
                        preview={preview}
                        strokeWidth={Math.max(1, editor.activeStroke / view.scale)}
                        activeTool={editor.activeTool}
                    />
                )}
            </Stage>
        );
    };

    return (
        <div className={fill ? 'flex h-full min-h-0 gap-2.5' : 'space-y-2'}>
            <div className={fill ? 'flex min-h-0 flex-1 items-stretch gap-2.5' : 'flex items-start gap-2.5'}>
                {!readOnly && <SowToolbar className={fill ? 'h-full' : undefined} />}

                <div
                    ref={containerRef}
                    className={`relative flex-1 overflow-hidden rounded-lg border border-border bg-muted/50 shadow-xs ${canvasHeightClass}`}
                >
                    {renderCanvasBody()}

                    <SowCanvasZoomControls
                        onZoomIn={() => setView((prev) => computeNextZoomView(prev, 1.2))}
                        onZoomOut={() => setView((prev) => computeNextZoomView(prev, 1 / 1.2))}
                        onFit={() =>
                            setView(
                                computeFittedView(
                                    containerSize.width,
                                    containerSize.height,
                                    LOGICAL_WIDTH,
                                    logicalHeight
                                )
                            )
                        }
                    />

                    {!readOnly && (
                        <>
                            <SowCanvasToolHints
                                activeTool={editor.activeTool}
                                preview={preview}
                                onCommitDashed={commitDashed}
                            />
                            <SowDashedPathDoneButton
                                activeTool={editor.activeTool}
                                preview={preview}
                                onCommit={commitDashed}
                            />
                        </>
                    )}

                    {!readOnly && overlayRect && (
                        <SowTextInputOverlay
                            rect={overlayRect.rect}
                            fontSize={overlayRect.fontSize}
                            textColor={overlayRect.textColor}
                            borderRadius={overlayRect.borderRadius}
                            initialValue={overlayRect.initialValue}
                            onCommit={(text) => {
                                if (editingNode) editor.updateNode(editingNode.id, { text });
                                setEditingTextId(null);
                            }}
                            onCancel={() => setEditingTextId(null)}
                        />
                    )}
                </div>

                {!readOnly && (
                    <div className="hidden w-56 shrink-0 md:block">
                        <SowPropertiesPanel
                            baseWidth={LOGICAL_WIDTH}
                            onEditText={setEditingTextId}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}
