export type MediaAssetStatus = 'processing' | 'ready' | 'failed';

export type MediaPickerScope = 'project' | 'market' | 'all';

export type QueueFileStatus = 'pending' | 'uploading' | 'done' | 'failed' | 'aborted';

export interface MediaLibraryProject {
    id: number;
    name: string;
    market_id?: number | null;
}

export interface TeamSupervisor {
    id: number;
    name: string;
    email: string;
    role_name?: string | null;
    profile_photo_url?: string | null;
    assets_count: number;
}

export interface MediaLibraryConfig {
    allowed_mimes: string[];
    max_file_bytes: number;
    chunk_size_bytes: number;
}

export interface MediaAssetPhotoPivot {
    id: number;
    closeout_package_id: number;
    package_stage_id: number;
    package_category_selection_id: number;
    position: number;
    status: string;
}

export interface MediaAssetUploaderRole {
    id: number;
    name: string;
}

export interface MediaAssetUploader {
    id: number;
    name: string;
    email?: string;
    role_name?: string | null;
    roles?: MediaAssetUploaderRole[];
    profile_photo_url?: string | null;
}

export interface MediaAsset {
    id: number;
    user_id: number;
    market_id: number | null;
    project_id: number | null;
    source_asset_id?: number | null;
    annotation_layer?: {
        base_width: number;
        nodes: Record<string, unknown>[];
    } | null;
    original_filename: string;
    mime_type: string;
    file_size_bytes: number;
    total_chunks: number;
    gps_latitude: number | null;
    gps_longitude: number | null;
    gps_accuracy: number | null;
    captured_at: string | null;
    status: MediaAssetStatus;
    validation_error: string | null;
    created_at: string;
    updated_at: string;
    url?: string | null;
    thumb_url?: string | null;
    source_image_url?: string | null;
    source_asset?: MediaAsset | null;
    owner?: MediaAssetUploader | null;
    user?: MediaAssetUploader | null;
    market?: {
        id: number;
        name: string;
        code?: string;
    };
    project?: {
        id: number;
        name: string;
        market_id: number;
    };
    photos?: MediaAssetPhotoPivot[];
}

export interface InitiateUploadPayload {
    filename: string;
    total_size: number;
    total_chunks: number;
    mime_type: string;
    captured_at?: string | null;
    project_id?: number | null;
    market_id?: number | null;
}

export interface InitiateUploadResponse {
    asset_id: number;
    upload_token: string;
}

export interface QueuedFile {
    id: string;
    file: File;
    status: QueueFileStatus;
    progress: number;
    error?: string;
    assetId?: number;
    uploadToken?: string;
    retryCount: number;
    projectId?: number | null;
    marketId?: number | null;
    previewUrl?: string;
}

export interface ValidationResult {
    valid: boolean;
    error?: string;
}

export interface MediaQuotaStats {
    used_bytes: number;
    limit_bytes: number | null;
    percentage: number;
    is_unlimited: boolean;
}

export interface MediaStatusCounts {
    total: number;
    ready: number;
    processing: number;
    failed: number;
}

export interface MediaSystemRoleUsage {
    role: string;
    label: string;
    used_bytes: number;
    limit_bytes: number | null;
}

export interface MediaSystemUsage {
    total_bytes: number;
    by_role: MediaSystemRoleUsage[];
}

export interface MediaStatsResponse {
    quota: {
        used_bytes: number;
        limit_bytes: number | null;
    };
    counts: MediaStatusCounts;
    /** System-wide usage with a per-role breakdown. Admins only. */
    system?: MediaSystemUsage | null;
}

export interface CursorPaginatedResponse<T> {
    data: T[];
    path: string;
    per_page: number;
    next_cursor: string | null;
    next_page_url: string | null;
    prev_cursor: string | null;
    prev_page_url: string | null;
}

export interface MediaPurgePreview {
    total_assets: number;
    total_bytes: number;
    deletable_assets: number;
    deletable_bytes: number;
    uploaders: number;
    protected_assets: number;
    protected_bytes: number;
    protected_reasons: {
        annotated_images: number;
        report_covers: number;
    };
    in_progress_assets: number;
    orphan_chunk_folders: number;
}

export interface MediaPurgeProgress {
    state: 'running' | 'completed';
    requested_by: { id: number; name: string } | null;
    requested_at: string;
    expected_total: number;
    /** True when the cache lost the counters mid-run; the log holds the truth. */
    accounting_reset: boolean;
    processed: number;
    deleted: number;
    deleted_bytes: number;
    protected_skipped: number;
    in_progress_skipped: number;
    failed: number;
    failures: Array<{ asset_id: number; error: string }>;
    orphan_chunk_folders_removed: number;
    finished_at: string | null;
}

export interface MediaPurgePreviewResponse {
    preview: MediaPurgePreview;
    confirmation_phrase: string;
    progress: Partial<MediaPurgeProgress>;
}
