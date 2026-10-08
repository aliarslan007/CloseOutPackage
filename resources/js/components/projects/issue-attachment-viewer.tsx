import { FeedbackImageHoverOverlay, type EntityFeedbackNoteItem } from '@/features/entity-feedback';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import {
    ChevronLeft,
    ChevronRight,
    FileVideo,
    ImageIcon,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { IssueAttachment } from './issue-types';

export interface IssueAttachmentViewerProps {
    attachments: IssueAttachment[];
    className?: string;
    variant?: 'default' | 'hero';
    /** When provided (edit mode), shows a remove button for the current item. */
    onDelete?: (attachment: IssueAttachment) => void;
    onAnnotate?: (attachment: IssueAttachment) => void;
    getAttachmentNote?: (attachmentId: number) => EntityFeedbackNoteItem | null;
}

export function isVideoAttachment(att: IssueAttachment): boolean {
    if (att.mime_type?.startsWith('video/')) return true;
    const lower = att.file_name?.toLowerCase() || '';
    return (
        lower.endsWith('.mp4') ||
        lower.endsWith('.mov') ||
        lower.endsWith('.webm') ||
        lower.endsWith('.avi')
    );
}

export function IssueAttachmentViewer({
    attachments,
    className,
    variant = 'default',
    onDelete,
    onAnnotate,
    getAttachmentNote,
}: Readonly<IssueAttachmentViewerProps>) {
    const [selectedImage, setSelectedImage] = React.useState<IssueAttachment | null>(null);
    const [currentIndex, setCurrentIndex] = React.useState(0);

    // Prioritize videos first, then photos
    const sortedAttachments = React.useMemo(() => {
        if (!attachments || attachments.length === 0) return [];
        const videos = attachments.filter((a) => isVideoAttachment(a));
        const photos = attachments.filter((a) => !isVideoAttachment(a));
        return [...videos, ...photos];
    }, [attachments]);

    // Keep currentIndex within bounds if attachments list changes
    React.useEffect(() => {
        if (currentIndex >= sortedAttachments.length) {
            setCurrentIndex(0);
        }
    }, [sortedAttachments.length, currentIndex]);

    if (!attachments || attachments.length === 0 || sortedAttachments.length === 0) {
        return null;
    }

    if (variant === 'hero') {
        const total = sortedAttachments.length;
        const currentAtt = sortedAttachments[currentIndex] || sortedAttachments[0];
        const isVideo = isVideoAttachment(currentAtt);
        const mediaUrl = currentAtt.url || currentAtt.original_url || currentAtt.preview_url || '';

        const handlePrev = (e: React.MouseEvent) => {
            e.stopPropagation();
            setCurrentIndex((prev) => (prev <= 0 ? total - 1 : prev - 1));
        };

        const handleNext = (e: React.MouseEvent) => {
            e.stopPropagation();
            setCurrentIndex((prev) => (prev >= total - 1 ? 0 : prev + 1));
        };

        return (
            <div className={cn('space-y-2', className)}>
                {/* Main Media Carousel Display Box */}
                <div className="relative rounded-2xl overflow-hidden border border-border/80 bg-black/95 shadow-xs h-[220px] sm:h-[240px] w-full flex items-center justify-center group">
                    {isVideo ? (
                        <video
                            key={currentAtt.id}
                            src={mediaUrl}
                            autoPlay
                            loop
                            muted
                            playsInline
                            controls
                            preload="auto"
                            className="w-full h-full object-contain"
                        />
                    ) : (
                        <div className="w-full h-full relative group/hero">
                            <button
                                type="button"
                                onClick={() => setSelectedImage(currentAtt)}
                                className="w-full h-full block relative focus:outline-none cursor-pointer"
                                title={`Enlarge ${currentAtt.file_name}`}
                            >
                                <img
                                    src={mediaUrl}
                                    alt={currentAtt.file_name}
                                    className="w-full h-full object-cover transition-transform group-hover/hero:scale-101"
                                    loading="lazy"
                                />
                            </button>
                            {onAnnotate && (
                                <FeedbackImageHoverOverlay
                                    note={getAttachmentNote?.(currentAtt.id)}
                                    onAnnotate={() => onAnnotate(currentAtt)}
                                />
                            )}
                        </div>
                    )}

                    {/* Carousel Navigation Arrows (shown if multiple items) */}
                    {total > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={handlePrev}
                                className="absolute left-2 top-1/2 -translate-y-1/2 size-8 rounded-full bg-black/60 hover:bg-black/85 text-white flex items-center justify-center backdrop-blur-xs transition-all shadow-md z-20 cursor-pointer"
                                title="Previous media"
                            >
                                <ChevronLeft className="size-4.5" />
                            </button>

                            <button
                                type="button"
                                onClick={handleNext}
                                className="absolute right-2 top-1/2 -translate-y-1/2 size-8 rounded-full bg-black/60 hover:bg-black/85 text-white flex items-center justify-center backdrop-blur-xs transition-all shadow-md z-20 cursor-pointer"
                                title="Next media"
                            >
                                <ChevronRight className="size-4.5" />
                            </button>
                        </>
                    )}
                </div>

                {/* Media Metadata & Pagination Info Bar */}
                <div className="px-1 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                    <div className="flex items-center gap-1.5 min-w-0">
                        {isVideo ? (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold text-[10px] uppercase border border-blue-500/20 shrink-0">
                                <FileVideo className="size-3" />
                                Video
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-[10px] uppercase border border-emerald-500/20 shrink-0">
                                <ImageIcon className="size-3" />
                                Photo
                            </span>
                        )}
                        <span className="truncate font-medium text-foreground text-[11px]">
                            {currentAtt.file_name}
                        </span>
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0">
                        {total > 1 && (
                            <span className="text-[11px] font-bold text-foreground bg-muted/60 px-2 py-0.5 rounded-full border border-border/50">
                                {currentIndex + 1} / {total}
                            </span>
                        )}
                        {onDelete && (
                            <button
                                type="button"
                                onClick={() => onDelete(currentAtt)}
                                title="Remove this attachment"
                                className="size-6 rounded-md flex items-center justify-center text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Lightbox Dialog */}
                {selectedImage && (
                    <Dialog open={Boolean(selectedImage)} onOpenChange={() => setSelectedImage(null)}>
                        <DialogContent className="max-w-4xl p-2 bg-background/95 backdrop-blur-md border-border/80 overflow-hidden">
                            <div className="relative flex flex-col items-center justify-center min-h-[300px]">
                                <img
                                    src={selectedImage.url || selectedImage.original_url}
                                    alt={selectedImage.file_name}
                                    className="max-h-[75vh] w-auto max-w-full object-contain rounded-lg"
                                />
                                <div className="w-full flex items-center justify-between px-2 pt-2 text-xs text-muted-foreground">
                                    <span className="font-semibold text-foreground truncate">
                                        {selectedImage.file_name}
                                    </span>
                                    <a
                                        href={selectedImage.url || selectedImage.original_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary hover:underline font-medium text-xs ml-2"
                                    >
                                        Open Original
                                    </a>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        );
    }

    // Default compact thumbnail grid for discussion comments
    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex flex-wrap gap-2 pt-1">
                {sortedAttachments.map((att) => {
                    const video = isVideoAttachment(att);
                    const mediaUrl = att.url || att.original_url || att.preview_url || '';
                    const thumbUrl =
                        att.thumb_url || att.preview_url || att.url || att.original_url || '';

                    if (video) {
                        return (
                            <div
                                key={att.id}
                                className="relative rounded-xl overflow-hidden border border-border/80 bg-black/90 max-w-[280px]"
                            >
                                <video
                                    src={mediaUrl}
                                    controls
                                    preload="metadata"
                                    className="max-h-[180px] w-full object-contain rounded-xl"
                                />
                                <div className="p-1.5 bg-background/90 text-[10px] text-muted-foreground flex items-center justify-between gap-2 border-t border-border/40">
                                    <span className="truncate flex items-center gap-1 font-medium text-foreground">
                                        <FileVideo className="size-3 text-blue-500 shrink-0" />
                                        {att.file_name}
                                    </span>
                                </div>
                            </div>
                        );
                    }

                    return (
                        <div
                            key={att.id}
                            className="relative group rounded-xl overflow-hidden border border-border/80 bg-muted/30"
                        >
                            <button
                                type="button"
                                onClick={() => setSelectedImage(att)}
                                className="block relative focus:outline-none"
                                title={`View ${att.file_name}`}
                            >
                                <img
                                    src={thumbUrl}
                                    alt={att.file_name}
                                    className="size-20 sm:size-24 object-cover rounded-xl transition-transform group-hover:scale-105"
                                    loading="lazy"
                                />
                            </button>
                            {onAnnotate && (
                                <FeedbackImageHoverOverlay
                                    note={getAttachmentNote?.(att.id)}
                                    onAnnotate={() => onAnnotate(att)}
                                />
                            )}
                        </div>
                    );
                })}
            </div>

            {/* Image Full-View Modal */}
            {selectedImage && (
                <Dialog open={Boolean(selectedImage)} onOpenChange={() => setSelectedImage(null)}>
                    <DialogContent className="max-w-4xl p-2 bg-background/95 backdrop-blur-md border-border/80 overflow-hidden">
                        <div className="relative flex flex-col items-center justify-center min-h-[300px]">
                            <img
                                src={selectedImage.url || selectedImage.original_url}
                                alt={selectedImage.file_name}
                                className="max-h-[75vh] w-auto max-w-full object-contain rounded-lg"
                            />
                            <div className="w-full flex items-center justify-between px-2 pt-2 text-xs text-muted-foreground">
                                <span className="font-semibold text-foreground truncate">
                                    {selectedImage.file_name}
                                </span>
                                <a
                                    href={selectedImage.url || selectedImage.original_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-primary hover:underline font-medium text-xs ml-2"
                                >
                                    Open Original
                                </a>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
