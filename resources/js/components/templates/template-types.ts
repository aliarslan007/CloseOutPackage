export interface TemplateCategoryItem {
    id: number;
    category_preset_id?: number | null;
    name: string;
    description?: string | null;
    default_picture_count: number;
    is_required: boolean;
    sort_order: number;
}

export type StageValue = 'pre_construction' | 'construction' | 'post_completion';

export interface TemplateSectionItem {
    id: number;
    stage: StageValue;
    heading: string;
    admin_notes?: string | null;
    sort_order: number;
    categories: TemplateCategoryItem[];
}

export interface TemplateChecklistItem {
    id: number;
    label: string;
    has_explain_field: boolean;
    allows_na?: boolean;
    sort_order: number;
}

export interface TemplateNoteImage {
    id: number;
    file_name: string;
    url: string;
    thumb_url: string;
}

export interface TemplateDetail {
    id: number;
    hash_id?: string;
    name: string;
    description?: string | null;
    additional_site_notes?: string | null;
    additional_note_images?: TemplateNoteImage[];
    is_active: boolean;
    inactive_reason?: string | null;
    market_id: number;
    market?: { id: number; name: string } | null;
    sections: TemplateSectionItem[];
    checklist_items: TemplateChecklistItem[];
}

export const STAGE_LABELS: Record<StageValue, string> = {
    pre_construction: 'Pre Construction',
    construction: 'Construction / Installation',
    post_completion: 'Post Completion',
};

export function nextSortOrder(items: Array<{ sort_order: number }>): number {
    if (items.length === 0) return 0;
    return Math.max(...items.map((item) => item.sort_order)) + 1;
}
