import React, { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { EmptyCard } from '@/components/shared/empty-card';
import { LightboxPhoto, PhotoLightbox } from '@/components/shared/photo-lightbox';
import { Camera, Layers } from 'lucide-react';
import { cn } from '@/lib/utils';
import { CustomerPhotoData, FeedbackNoteItem, StageGroup } from '../types';
import { CustomerCategoryCard } from './CustomerCategoryCard';

interface CustomerStagePhotosProps {
    allApprovedPhotos: CustomerPhotoData[];
    stageGroups: StageGroup[];
    activeStageKey?: string;
    canAddNote?: boolean;
    getPhotoNote?: (photoId: number, originalPhotoId?: number | null) => FeedbackNoteItem | null;
    /** Opens the annotate canvas for a photo. */
    onAnnotate?: (photo: CustomerPhotoData) => void;
    getCategoryNote?: (categoryId: number) => FeedbackNoteItem | null;
    onRequestPhotoNote?: (photo: CustomerPhotoData) => void;
    onRequestCategoryNote?: (categoryName: string, categorySelectionId?: number) => void;
}

export function CustomerStagePhotos({
    allApprovedPhotos,
    stageGroups,
    activeStageKey,
    canAddNote,
    getPhotoNote,
    onAnnotate,
    getCategoryNote,
    onRequestPhotoNote,
    onRequestCategoryNote,
}: Readonly<CustomerStagePhotosProps>) {
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

    const filteredGroups = useMemo(() => {
        if (!activeStageKey) return stageGroups;
        return stageGroups.filter((g) => g.key === activeStageKey);
    }, [stageGroups, activeStageKey]);

    const lightboxPhotos: LightboxPhoto[] = useMemo(() => {
        return allApprovedPhotos.map((photo) => {
            const catName =
                photo.category_selection?.user_heading ||
                photo.category_selection?.template_picture_category?.name ||
                photo.category ||
                'Site Photo';

            return {
                id: photo.id,
                url: photo.original_url || photo.url || photo.photo_url || '',
                category: catName,
                notes: photo.notes || photo.category_selection?.user_notes || undefined,
                captured_at: photo.captured_at || photo.created_at || undefined,
                uploader_name: photo.uploader?.name || undefined,
                latitude: photo.gps_latitude ?? undefined,
                longitude: photo.gps_longitude ?? undefined,
            };
        });
    }, [allApprovedPhotos]);

    if (filteredGroups.length === 0) {
        return (
            <EmptyCard
                title="No photos for this stage"
                description="Site photos for this construction stage will appear here as they are captured by the field team."
                icon={<Camera className="size-10 text-muted-foreground" />}
            />
        );
    }

    return (
        <div className="space-y-4">
            {filteredGroups.map((stageGroup) => (
                <div key={stageGroup.key} className="space-y-3.5">
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="outline"
                            className={cn(
                                'rounded-full text-xs font-bold px-3.5 py-1 uppercase tracking-wide gap-1.5',
                                stageGroup.badgeColor
                            )}
                        >
                            <Layers className="size-3.5" />
                            {stageGroup.heading}
                        </Badge>
                        <div className="h-px flex-1 bg-border/80" />
                    </div>

                    {Array.from(stageGroup.categories.entries()).map(([catName, catPhotos]) => {
                        // A section note may be addressed by either id: the
                        // template picture category, or the package's own
                        // category selection. Both are mapped to the same
                        // category, so prefer the template id and fall back.
                        // The trailing ?? normalises a null last term to
                        // undefined, which is what the card accepts.
                        const categorySelectionId =
                            catPhotos[0]?.category_selection?.template_picture_category?.id ??
                            catPhotos[0]?.category_selection?.template_picture_category_id ??
                            catPhotos[0]?.category_selection?.id ??
                            catPhotos[0]?.package_category_selection_id ??
                            undefined;
                        const catNote =
                            categorySelectionId != null && getCategoryNote
                                ? getCategoryNote(categorySelectionId)
                                : null;

                        return (
                            <CustomerCategoryCard
                                key={catName}
                                categoryName={catName}
                                categorySelectionId={categorySelectionId}
                                categoryNote={catNote}
                                canAddNote={canAddNote}
                                catPhotos={catPhotos}
                                allApprovedPhotos={allApprovedPhotos}
                                getPhotoNote={getPhotoNote}
                                onAnnotate={onAnnotate}
                                onSelectPhoto={(idx) => setLightboxIndex(idx)}
                                onRequestPhotoNote={onRequestPhotoNote}
                                onRequestCategoryNote={onRequestCategoryNote}
                            />
                        );
                    })}
                </div>
            ))}

            {lightboxIndex !== null && (
                <PhotoLightbox
                    photos={lightboxPhotos}
                    index={lightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                />
            )}
        </div>
    );
}

export default CustomerStagePhotos;
