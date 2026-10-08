import React, { useCallback, useRef, useState } from 'react';
import type { QueuedFile } from '../types/media-library.types';
import { UploadProgressItem } from './UploadProgressItem';

export interface VirtualizedUploadListProps {
    files: QueuedFile[];
}

const ITEM_HEIGHT = 68;
const CONTAINER_HEIGHT = 280;
const OVERSCAN = 4;

export function VirtualizedUploadList({ files }: Readonly<VirtualizedUploadListProps>) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const [scrollTop, setScrollTop] = useState(0);

    const handleScroll = useCallback((e: React.UIEvent<HTMLDivElement>) => {
        setScrollTop(e.currentTarget.scrollTop);
    }, []);

    // For smaller batches (<= 15 items), render standard vertical flow
    if (files.length <= 15) {
        return (
            <div className="max-h-72 overflow-y-auto p-3 space-y-2">
                {files.map((file) => (
                    <UploadProgressItem key={file.id} file={file} />
                ))}
            </div>
        );
    }

    const totalHeight = files.length * ITEM_HEIGHT;
    const startIndex = Math.max(0, Math.floor(scrollTop / ITEM_HEIGHT) - OVERSCAN);
    const endIndex = Math.min(
        files.length,
        Math.ceil((scrollTop + CONTAINER_HEIGHT) / ITEM_HEIGHT) + OVERSCAN,
    );

    const visibleItems = files.slice(startIndex, endIndex);

    return (
        <section
            ref={containerRef}
            onScroll={handleScroll}
            aria-label="Upload queue items"
            style={{ maxHeight: `${CONTAINER_HEIGHT}px` }}
            className="overflow-y-auto p-3"
        >
            <div style={{ height: `${totalHeight}px`, position: 'relative' }}>
                {visibleItems.map((file, idx) => {
                    const actualIndex = startIndex + idx;
                    return (
                        <div
                            key={file.id}
                            style={{
                                position: 'absolute',
                                top: `${actualIndex * ITEM_HEIGHT}px`,
                                left: 0,
                                right: 0,
                                height: `${ITEM_HEIGHT - 6}px`,
                            }}
                        >
                            <UploadProgressItem file={file} />
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
