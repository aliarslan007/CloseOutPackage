import type { EntityFeedbackNoteItem } from '../types/entity-feedback.types';

// ---------------------------------------------------------------------------
// Shared input shapes
// ---------------------------------------------------------------------------

export interface FeedbackTargetExtractionOptions {
    notes?: EntityFeedbackNoteItem[] | null;
    /**
     * Count an answered issue as a flag on its target too. Off by default: a
     * section needs attention until somebody answers it. Pass it on BOTH sides of
     * a withdrawn/live split - the two sets have to be comparable, or an
     * answered-then-withdrawn issue vanishes from the withdrawn side and its
     * section keeps its highlight forever.
     */
    includeResolvedNotes?: boolean;
    photos?: Array<{
        id: number;
        package_category_selection_id?: number | null;
        package_stage_id?: number;
        category_selection?: {
            id?: number;
            template_picture_category_id?: number | null;
            template_picture_category?: { id?: number; name?: string } | null;
        } | null;
    }>;
    stages?: Array<{
        id?: number;
        stage: string;
        category_selections?: Array<{
            id?: number;
            template_picture_category_id?: number | null;
            template_picture_category?: { id?: number; name?: string } | null;
        }>;
    }>;
    templateSections?: Array<{
        id?: number;
        stage?: string;
        heading?: string;
        categories?: Array<{ id: number; name: string }>;
    }>;
}

export interface BuildItemNamesByKeyOptions {
    templateSections?: Array<{
        id?: number;
        heading?: string;
        categories?: Array<{ id: number; name: string }>;
    }>;
    stages?: Array<{
        category_selections?: Array<{
            id?: number;
            template_picture_category_id?: number | null;
            template_picture_category?: { id?: number; name?: string } | null;
        }>;
    }>;
    photos?: Array<{
        id?: number;
        package_category_selection_id?: number | null;
        category_selection?: {
            id?: number;
            template_picture_category_id?: number | null;
            template_picture_category?: { id?: number; name?: string } | null;
        } | null;
    }>;
}

// ---------------------------------------------------------------------------
// extractFeedbackTargetPhotoIds
// ---------------------------------------------------------------------------

/**
 * Returns the set of photo IDs an open feedback note targets.
 *
 * An issue the customer withdrew is not work and must not light up its photo for
 * staff: no highlight, no replace/delete tools on a picture they asked to leave
 * alone.
 */
export function extractFeedbackTargetPhotoIds(
    notes?: EntityFeedbackNoteItem[] | null
): Set<number> {
    const ids = new Set<number>();
    if (!notes) return ids;
    for (const note of notes) {
        const isCatOrSection =
            note.source_photo_type === 'category' || note.source_photo_type === 'section';
        if (
            !note.is_resolved &&
            !note.is_withdrawn &&
            note.source_photo_id != null &&
            !isCatOrSection
        ) {
            ids.add(note.source_photo_id);
        }
    }
    return ids;
}

// ---------------------------------------------------------------------------
// extractFeedbackTargetCategoryIds — helpers
// ---------------------------------------------------------------------------

type StageEntry = NonNullable<FeedbackTargetExtractionOptions['stages']>[number];
type PhotoEntry = NonNullable<FeedbackTargetExtractionOptions['photos']>[number];

/** Map rawId ↔ template_picture_category_id via stage category_selections. */
function addPairedIdsFromStages(catIds: Set<number>, rawId: number, stages: StageEntry[]): void {
    for (const stage of stages) {
        for (const cs of stage.category_selections ?? []) {
            if (cs.id === rawId && cs.template_picture_category_id != null) {
                catIds.add(cs.template_picture_category_id);
            }
            if (cs.template_picture_category_id === rawId && cs.id != null) {
                catIds.add(cs.id);
            }
        }
    }
}

/** Map rawId ↔ template_picture_category_id via photo records. */
function addPairedIdsFromPhotos(catIds: Set<number>, rawId: number, photos: PhotoEntry[]): void {
    for (const photo of photos) {
        const selId = photo.package_category_selection_id;
        const tmplId = photo.category_selection?.template_picture_category_id;
        if (selId === rawId && tmplId != null) {
            catIds.add(tmplId);
        }
        if (tmplId === rawId && selId != null) {
            catIds.add(selId);
        }
    }
}

/** Add rawId plus its paired IDs to the set. */
function addCategoryAndSelection(
    catIds: Set<number>,
    rawId: number,
    stages: StageEntry[],
    photos: PhotoEntry[]
): void {
    catIds.add(rawId);
    addPairedIdsFromStages(catIds, rawId, stages);
    addPairedIdsFromPhotos(catIds, rawId, photos);
}

type SectionEntry = NonNullable<FeedbackTargetExtractionOptions['templateSections']>[number];

/** Expand a section-or-category note into its constituent category IDs. */
function resolveSectionNote(
    catIds: Set<number>,
    targetId: number,
    stages: StageEntry[],
    photos: PhotoEntry[],
    templateSections: SectionEntry[]
): void {
    const matchedSection = templateSections.find((s) => s.id === targetId);
    if (matchedSection?.categories) {
        for (const cat of matchedSection.categories) {
            addCategoryAndSelection(catIds, cat.id, stages, photos);
        }
    }
    addCategoryAndSelection(catIds, targetId, stages, photos);
}

/** Expand a photo note into its constituent category IDs. */
function resolvePhotoNote(
    catIds: Set<number>,
    notePhotoId: number,
    stages: StageEntry[],
    photos: PhotoEntry[]
): void {
    const photo = photos.find((p) => p.id === notePhotoId);
    if (!photo) return;
    if (photo.package_category_selection_id != null) {
        addCategoryAndSelection(catIds, photo.package_category_selection_id, stages, photos);
    }
    if (photo.category_selection?.template_picture_category_id != null) {
        addCategoryAndSelection(
            catIds,
            photo.category_selection.template_picture_category_id,
            stages,
            photos
        );
    }
}

/**
 * Returns the set of category IDs (both template-level and selection-level) that
 * have at least one open feedback note targeting them. A withdrawn issue targets
 * nothing: the customer cancelled the change, so the section loses its flag.
 */
export function extractFeedbackTargetCategoryIds(
    options: FeedbackTargetExtractionOptions
): Set<number> {
    const catIds = new Set<number>();
    const {
        notes,
        includeResolvedNotes = false,
        photos = [],
        stages = [],
        templateSections = [],
    } = options;
    if (!notes?.length) return catIds;

    for (const note of notes) {
        if (note.is_withdrawn || (note.is_resolved && !includeResolvedNotes)) continue;
        if (note.source_photo_id == null) continue;

        const isCatOrSection =
            note.source_photo_type === 'category' || note.source_photo_type === 'section';

        if (isCatOrSection) {
            resolveSectionNote(catIds, note.source_photo_id, stages, photos, templateSections);
        } else {
            resolvePhotoNote(catIds, note.source_photo_id, stages, photos);
        }
    }

    return catIds;
}

// ---------------------------------------------------------------------------
// buildItemNamesByKey — helpers
// ---------------------------------------------------------------------------

/**
 * Targets live in several id spaces whose autoincrements overlap - template
 * sections, template categories and per-package category selections all collide
 * on a bare number key (category 2 "Grounding" vs section 2
 * "Construction / Installation"). Keys mirror threadKeyFor(): `${type}:${id}`.
 */
type NameMap = Record<string, string>;
const nameKey = (type: string, id: number): string => `${type}:${id}`;
type StageNameEntry = NonNullable<BuildItemNamesByKeyOptions['stages']>[number];

/** Seed the name map from template sections and their categories. */
function seedNamesFromSections(
    map: NameMap,
    sections: NonNullable<BuildItemNamesByKeyOptions['templateSections']>
): void {
    for (const section of sections) {
        if (section.id != null && section.heading) {
            map[nameKey('section', section.id)] = section.heading;
        }
        for (const cat of section.categories ?? []) {
            map[nameKey('category', cat.id)] = cat.name;
        }
    }
}

/** Add both cs.id → name and template_picture_category_id → name entries. */
function recordCsName(
    map: NameMap,
    cs: NonNullable<StageNameEntry['category_selections']>[number],
    name: string
): void {
    if (cs.id != null) {
        map[nameKey('category', cs.id)] = name;
    }
    if (cs.template_picture_category_id != null) {
        map[nameKey('category', cs.template_picture_category_id)] = name;
    }
}

/** Fill the name map from stage category_selections. */
function seedNamesFromStages(map: NameMap, stages: StageNameEntry[]): void {
    for (const stage of stages) {
        for (const cs of stage.category_selections ?? []) {
            const tmplId = cs.template_picture_category_id;
            const name =
                cs.template_picture_category?.name ??
                (tmplId == null ? undefined : map[nameKey('category', tmplId)]);
            if (name) {
                recordCsName(map, cs, name);
            }
        }
    }
}

/** Fill the name map from photo records (fallback for selection IDs not yet covered). */
function seedNamesFromPhotos(
    map: NameMap,
    photos: NonNullable<BuildItemNamesByKeyOptions['photos']>
): void {
    for (const photo of photos) {
        const name = photo.category_selection?.template_picture_category?.name;
        if (!name) {
            continue;
        }
        // Notes may quote either spelling of "the category this photo is in".
        for (const id of [photo.package_category_selection_id, photo.category_selection?.id]) {
            if (id != null && !map[nameKey('category', id)]) {
                map[nameKey('category', id)] = name;
            }
        }
        // And a photo thread is named by the section its image belongs to, so two
        // marked-up photos from two sections are not both just "Marked-up photo".
        if (photo.id != null) {
            map[nameKey('photo', photo.id)] = name;
        }
    }
}

/**
 * Build a `${type}:${id}` -> human name map for every feedback thread target
 * (section or category). Used by manager/tech panels to label conversations.
 * Type-scoped keys are what keep overlapping id spaces from cross-talking.
 */
export function buildItemNamesByKey(options: BuildItemNamesByKeyOptions): NameMap {
    const map: NameMap = {};
    seedNamesFromSections(map, options.templateSections ?? []);
    seedNamesFromStages(map, options.stages ?? []);
    seedNamesFromPhotos(map, options.photos ?? []);
    return map;
}
