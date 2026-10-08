import { CustomerPhotoData, StageGroup } from './types';

export function formatDate(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function organizeStagePhotos(photos: CustomerPhotoData[]): StageGroup[] {
    const stagesMap: Record<string, Map<string, CustomerPhotoData[]>> = {
        pre_construction: new Map(),
        construction: new Map(),
        post_completion: new Map(),
        other: new Map(),
    };

    photos.forEach((photo) => {
        const stageVal =
            photo.stage?.stage ||
            photo.category_selection?.template_picture_category?.section?.stage ||
            photo.category_selection?.template_picture_category?.stage ||
            'other';

        let targetKey = 'other';
        if (stageVal.includes('pre') || stageVal === 'pre_construction') {
            targetKey = 'pre_construction';
        } else if (stageVal.includes('post') || stageVal === 'post_completion') {
            targetKey = 'post_completion';
        } else if (stageVal.includes('const') || stageVal === 'construction') {
            targetKey = 'construction';
        }

        const categoryName =
            photo.category_selection?.user_heading ||
            photo.category_selection?.template_picture_category?.name ||
            photo.category ||
            'General Pictures';

        const categoryMap = stagesMap[targetKey] || stagesMap.other;
        if (!categoryMap.has(categoryName)) {
            categoryMap.set(categoryName, []);
        }
        categoryMap.get(categoryName)?.push(photo);
    });

    const definitions: Array<{
        key: 'pre_construction' | 'construction' | 'post_completion' | 'other';
        heading: string;
        badgeColor: string;
    }> = [
        {
            key: 'pre_construction',
            heading: 'PRE CONSTRUCTION',
            badgeColor: 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400',
        },
        {
            key: 'construction',
            heading: 'CONSTRUCTION / INSTALLATION',
            badgeColor: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400',
        },
        {
            key: 'post_completion',
            heading: 'POST COMPLETION',
            badgeColor: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            key: 'other',
            heading: 'ADDITIONAL DELIVERABLE PHOTOS',
            badgeColor: 'border-purple-500/30 bg-purple-500/10 text-purple-600 dark:text-purple-400',
        },
    ];

    return definitions
        .map((def) => ({
            ...def,
            categories: stagesMap[def.key],
        }))
        .filter((group) => group.categories.size > 0);
}
