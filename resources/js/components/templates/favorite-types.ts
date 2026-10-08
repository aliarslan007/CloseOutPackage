/**
 * Shared types for favorite templates. Single source of truth used by the
 * Favorites page, the builder's favorites dialog, and the "use favorite"
 * package creation flow.
 */

export interface FavoriteTemplateRef {
    id: number;
    name: string;
    market?: { id: number; name: string } | null;
}

/**
 * Lean projection of a favorite (backend: TemplateQuery::favoriteOptions).
 */
export interface FavoriteOption {
    id: number;
    name: string;
    template_id: number;
    category_count: number;
    created_at?: string;
    template?: FavoriteTemplateRef | null;
}

/**
 * A single saved page-3 category selection inside a favorite snapshot.
 */
export type FavoriteSnapshotItem = {
    category_id: number;
    is_selected?: boolean;
    picture_count?: number;
    user_heading?: string | null;
    user_notes?: string | null;
};

/**
 * Full favorite as passed to the package builder (backend: favoritesFor),
 * including the complete snapshot so selections can be re-applied.
 */
export interface FavoriteData {
    id: number;
    name: string;
    template_id: number;
    snapshot: FavoriteSnapshotItem[];
    created_at?: string;
    template?: FavoriteTemplateRef | null;
}
