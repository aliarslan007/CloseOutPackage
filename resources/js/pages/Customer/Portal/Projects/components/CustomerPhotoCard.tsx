import { GpsBadge } from '@/components/shared/gps-badge';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import {
    Calendar,
    CheckCircle2,
    ExternalLink,
    MessageSquare,
    Pencil,
    User,
    ZoomIn,
} from 'lucide-react';
import React from 'react';
import { CustomerPhotoData, FeedbackNoteItem } from '../types';
import { formatDate } from '../utils';

interface CustomerPhotoCardProps {
    photo: CustomerPhotoData;
    categoryName: string;
    feedbackNote?: FeedbackNoteItem | null;
    canAddNote?: boolean;
    onClick: () => void;
    onRequestNote?: (photo: CustomerPhotoData) => void;
    /** Opens the annotate canvas so the customer can mark up the image. */
    onAnnotate?: (photo: CustomerPhotoData) => void;
}

function renderPhotoBadge(photo: CustomerPhotoData) {
    if (photo.is_approved || photo.status === 'approved') {
        return (
            <Badge
                variant="success"
                className="absolute top-2 right-2 text-[9px] bg-emerald-600 text-white border-0 shadow-sm"
            >
                ✓ Approved
            </Badge>
        );
    }

    if (photo.status === 'flagged' || photo.status === 'replacement_requested') {
        return (
            <Badge
                variant="warning"
                className="absolute top-2 right-2 text-[9px] bg-amber-600 text-white border-0 shadow-sm"
            >
                Needs Replacement
            </Badge>
        );
    }

    if (photo.status === 'rejected') {
        return (
            <Badge
                variant="destructive"
                className="absolute top-2 right-2 text-[9px] bg-rose-600 text-white border-0 shadow-sm"
            >
                Rejected
            </Badge>
        );
    }

    return (
        <Badge
            variant="secondary"
            className="absolute top-2 right-2 text-[9px] bg-blue-600 text-white border-0 shadow-sm"
        >
            Pending Review
        </Badge>
    );
}

function getCardBorderClasses(isResolved: boolean, isReplaced: boolean): string {
    if (isResolved) {
        return 'ring-2 ring-emerald-500/60 border-emerald-500/50 bg-emerald-500/5 dark:bg-emerald-950/20 shadow-sm hover:border-emerald-500';
    }
    if (isReplaced) {
        return 'ring-1 ring-emerald-500/40 border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-950/15';
    }
    return 'border-border/80 hover:border-primary/40';
}

export function CustomerPhotoCard({
    photo,
    categoryName,
    feedbackNote,
    canAddNote,
    onClick,
    onRequestNote,
    onAnnotate,
}: Readonly<CustomerPhotoCardProps>) {
    const displayUrl = photo.thumb_url || photo.url || photo.photo_url || '';
    const isResolved = Boolean(
        feedbackNote?.is_resolved || feedbackNote?.resolved_at || feedbackNote?.manager_response
    );
    const isReplaced = Boolean(photo.replaced_photo || photo.replaced_by_photo_id);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onClick();
        }
    };

    return (
        <button
            type="button"
            onClick={onClick}
            onKeyDown={handleKeyDown}
            className={cn(
                'group flex flex-col justify-between h-full w-full text-left rounded-xl border bg-card overflow-hidden p-2 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                getCardBorderClasses(isResolved, isReplaced)
            )}
        >
            <div className="w-full">
                <div className="relative aspect-[4/3] w-full h-44 sm:h-48 rounded-lg overflow-hidden bg-black/5 dark:bg-white/5 shrink-0">
                    <img
                        src={displayUrl}
                        alt={categoryName}
                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]"
                        loading="lazy"
                    />
                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
                        <ZoomIn className="size-6" />
                    </div>

                    {/* Full size in a new tab. An anchor, not the card's own button, so
                    it opens the raw photo without triggering annotate. */}
                    {displayUrl && (
                        <a
                            href={displayUrl}
                            target="_blank"
                            rel="noreferrer"
                            onClick={(e) => e.stopPropagation()}
                            title="Open photo full size in a new tab"
                            aria-label="Open photo full size in a new tab"
                            className="absolute top-2 right-2 z-20 inline-flex size-7 items-center justify-center rounded-md bg-black/55 text-white opacity-0 backdrop-blur-xs transition-opacity hover:bg-black/75 focus-visible:opacity-100 group-hover:opacity-100"
                        >
                            <ExternalLink className="size-3.5" />
                        </a>
                    )}

                    {renderPhotoBadge(photo)}

                    {/* Feedback Note Trigger / Indicator */}
                    {feedbackNote ? (
                        <span
                            role="button"
                            tabIndex={0}
                            onClick={(e) => {
                                e.stopPropagation();
                                onRequestNote?.(photo);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.stopPropagation();
                                    e.preventDefault();
                                    onRequestNote?.(photo);
                                }
                            }}
                            className={cn(
                                'absolute top-2 left-2 z-10 inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[9px] font-bold text-white shadow-md backdrop-blur-xs transition-colors',
                                isResolved
                                    ? 'bg-emerald-600 hover:bg-emerald-700'
                                    : 'bg-amber-600 hover:bg-amber-700'
                            )}
                        >
                            {isResolved ? (
                                <>
                                    <CheckCircle2 className="size-2.5" /> Resolved in Revision
                                </>
                            ) : (
                                <>
                                    <MessageSquare className="size-2.5" /> Change Requested
                                </>
                            )}
                        </span>
                    ) : (
                        canAddNote &&
                        (onAnnotate ?? onRequestNote) && (
                            <span
                                role="button"
                                tabIndex={0}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    e.preventDefault();
                                    (onAnnotate ?? onRequestNote)?.(photo);
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.stopPropagation();
                                        e.preventDefault();
                                        (onAnnotate ?? onRequestNote)?.(photo);
                                    }
                                }}
                                className="absolute top-2 left-2 z-10 inline-flex items-center gap-1 rounded-md bg-black/60 hover:bg-amber-600 px-2 py-0.5 text-[9px] font-medium text-white opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-all shadow-sm backdrop-blur-xs"
                            >
                                {onAnnotate ? (
                                    <>
                                        <Pencil className="size-2.5" /> Mark / Annotate
                                    </>
                                ) : (
                                    <>
                                        <MessageSquare className="size-2.5" /> Request Change
                                    </>
                                )}
                            </span>
                        )
                    )}

                    {isReplaced && (
                        <span className="absolute bottom-2 right-2 z-10 inline-flex items-center gap-1 rounded-md bg-emerald-700/90 px-1.5 py-0.5 text-[8px] font-bold text-white shadow-sm">
                            Updated Photo
                        </span>
                    )}

                    {photo.gps_latitude != null && photo.gps_longitude != null && (
                        <GpsBadge
                            latitude={photo.gps_latitude}
                            longitude={photo.gps_longitude}
                            className="absolute bottom-2 left-2"
                        />
                    )}
                </div>

                <div className="space-y-1">
                    {photo.uploader?.name && (
                        <p className="text-[10px] text-muted-foreground flex items-center gap-1 truncate">
                            <User className="size-3 shrink-0 text-primary" />
                            Captured by {photo.uploader.name}
                        </p>
                    )}
                    {(photo.captured_at || photo.created_at) && (
                        <p className="text-[10px] text-muted-foreground flex items-center gap-1">
                            <Calendar className="size-3 shrink-0 text-primary" />
                            {formatDate(photo.captured_at || photo.created_at)}
                        </p>
                    )}
                    {photo.notes && (
                        <div className="pt-0.5">
                            <p
                                className="text-[11px] text-foreground font-medium line-clamp-2 bg-muted/40 rounded px-1.5 py-0.5 border border-border/50 break-words [overflow-wrap:anywhere]"
                                title={photo.notes}
                            >
                                <span className="text-muted-foreground text-[10px] uppercase font-bold mr-1">
                                    Note:
                                </span>
                                {photo.notes}
                            </p>
                        </div>
                    )}

                    {/* In-place customer request & manager resolution note */}
                    {feedbackNote && (
                        <div className="pt-1">
                            <div
                                className={cn(
                                    'rounded-md p-2 text-[10px] space-y-1.5 border',
                                    isResolved
                                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-950 dark:text-emerald-200'
                                        : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-300'
                                )}
                            >
                                <div>
                                    <span className="font-bold text-muted-foreground mr-1">
                                        Your Request:
                                    </span>
                                    <p className="line-clamp-2 text-foreground/90 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
                                        {feedbackNote.body}
                                    </p>
                                </div>
                                {feedbackNote.manager_response && (
                                    <div className="pt-1.5 border-t border-emerald-500/20">
                                        <span className="font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                            <CheckCircle2 className="size-3 shrink-0" />
                                            {feedbackNote.resolved_by_user?.name ?? 'Project Manager'}{' '}
                                            replied:
                                        </span>
                                        <p className="mt-0.5 text-foreground/90 font-normal break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
                                            {feedbackNote.manager_response}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </button>
    );
}

export default CustomerPhotoCard;
