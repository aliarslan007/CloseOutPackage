import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Camera, MessageSquare, MessageSquarePlus } from 'lucide-react';
import { CustomerPhotoData, FeedbackNoteItem } from '../types';
import { CustomerPhotoCard } from './CustomerPhotoCard';

interface CustomerCategoryCardProps {
    categoryName: string;
    categorySelectionId?: number;
    categoryNote?: FeedbackNoteItem | null;
    canAddNote?: boolean;
    catPhotos: CustomerPhotoData[];
    allApprovedPhotos: CustomerPhotoData[];
    getPhotoNote?: (photoId: number, originalPhotoId?: number | null) => FeedbackNoteItem | null;
    /** Opens the annotate canvas for a photo. */
    onAnnotate?: (photo: CustomerPhotoData) => void;
    onSelectPhoto: (photoId: number) => void;
    onRequestPhotoNote?: (photo: CustomerPhotoData) => void;
    onRequestCategoryNote?: (categoryName: string, categorySelectionId?: number) => void;
}

export function CustomerCategoryCard({
    categoryName,
    categorySelectionId,
    categoryNote,
    canAddNote,
    catPhotos,
    allApprovedPhotos,
    getPhotoNote,
    onAnnotate,
    onSelectPhoto,
    onRequestPhotoNote,
    onRequestCategoryNote,
}: Readonly<CustomerCategoryCardProps>) {
    return (
        <Card className="overflow-hidden rounded-2xl border border-border/80 shadow-card text-xs">
            <CardHeader className="p-3 bg-muted/30 border-b border-border/60">
                <div className="flex items-center justify-between flex-wrap gap-2">
                    <CardTitle className="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-2">
                        <span className="flex size-5 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <Camera className="size-3" />
                        </span>
                        {categoryName}
                    </CardTitle>

                    <div className="flex items-center gap-2">
                        {categoryNote ? (
                            <button
                                type="button"
                                onClick={() => onRequestCategoryNote?.(categoryName, categorySelectionId)}
                                className="inline-flex items-center gap-1 rounded-md bg-amber-600 hover:bg-amber-700 px-2 py-0.5 text-[10px] font-bold text-white shadow-xs transition-colors"
                            >
                                <MessageSquare className="size-2.5" />
                                {categoryNote.is_resolved ? 'Section Note Resolved' : 'Section Note Drafted'}
                            </button>
                        ) : (
                            canAddNote &&
                            onRequestCategoryNote && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onRequestCategoryNote(categoryName, categorySelectionId)}
                                    className="h-6 px-2 text-[10px] font-medium text-muted-foreground hover:text-foreground gap-1"
                                >
                                    <MessageSquarePlus className="size-3" /> Add Section Note
                                </Button>
                            )
                        )}

                        <Badge variant="secondary" className="text-[10px] font-bold tabular-nums">
                            {catPhotos.length} Photo{catPhotos.length === 1 ? '' : 's'}
                        </Badge>
                    </div>
                </div>
            </CardHeader>

            {/* Category level note banner if present */}
            {categoryNote && (
                <div className="px-3.5 pt-2.5 pb-1">
                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-900 dark:text-amber-300 flex items-start justify-between gap-2">
                        <div className="space-y-0.5 min-w-0">
                            <span className="font-bold flex items-center gap-1 text-[11px]">
                                <MessageSquare className="size-3 text-amber-600 dark:text-amber-400" />
                                Section Request:
                            </span>
                            <p className="text-foreground/90 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">{categoryNote.body}</p>
                            {categoryNote.manager_response && (
                                <p className="mt-1 pt-1 border-t border-amber-500/20 text-emerald-700 dark:text-emerald-400 font-medium break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
                                    <span className="font-bold mr-1">
                                        {categoryNote.resolved_by_user?.name ?? 'Project Manager'}{' '}
                                        replied:
                                    </span>
                                    {categoryNote.manager_response}
                                </p>
                            )}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onRequestCategoryNote?.(categoryName, categorySelectionId)}
                            className="h-6 text-[10px] shrink-0 hover:bg-amber-500/20"
                        >
                            Edit
                        </Button>
                    </div>
                </div>
            )}

            <CardContent className="p-3.5">
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    {catPhotos.map((photo) => {
                        const index = allApprovedPhotos.findIndex((p) => p.id === photo.id);
                        const note = getPhotoNote?.(photo.id, photo.replaced_photo?.id) ?? null;

                        return (
                            <CustomerPhotoCard
                                key={photo.id}
                                photo={photo}
                                categoryName={categoryName}
                                feedbackNote={note}
                                canAddNote={canAddNote}
                                onClick={() => index !== -1 && onSelectPhoto(index)}
                                onRequestNote={onRequestPhotoNote}
                                onAnnotate={onAnnotate}
                            />
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

export default CustomerCategoryCard;

