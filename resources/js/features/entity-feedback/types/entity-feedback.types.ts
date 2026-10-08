export type FeedbackEntityType = 'change_order' | 'issue' | 'closeout_package';

export type FeedbackRoundStatus = 'open' | 'submitted' | 'resolved' | 'accepted';

export interface EntityFeedbackAuthor {
    id: number;
    name: string;
    email?: string;
    /** Spatie role name, e.g. "manager". Present when the relation was loaded. */
    role_name?: string | null;
    avatar_url?: string | null;
    profile_photo_url?: string | null;
}

export interface EntityFeedbackNoteItem {
    id: number;
    hash_id?: string;
    feedback_round_id: number;
    entity_type: FeedbackEntityType;
    entity_id: number;
    source_photo_id?: number | null;
    source_photo_type?: string | null;
    original_image_url?: string | null;
    annotated_media_id?: number | null;
    annotated_image_url?: string | null;
    replacement_media_id?: number | null;
    replacement_image_url?: string | null;
    annotation_layer?: Record<string, unknown> | null;
    requested_action?:
        | 'replace_photo'
        | 'add_new_photo'
        | 'delete_photo'
        | 'update_note'
        | 'general'
        | string
        | null;
    body: string;
    /** Set once the author corrected their own message. */
    edited_at?: string | null;
    /** Server-decided: the viewer wrote this message and may still correct it. */
    can_edit?: boolean;
    /** Server-decided: the viewer wrote the reply and may still correct it. */
    can_edit_answer?: boolean;
    created_by: number;
    author?: EntityFeedbackAuthor | null;
    manager_response?: string | null;
    /** The manager who wrote the reply above, when known. */
    resolved_by_user?: EntityFeedbackAuthor | null;
    is_resolved: boolean;
    resolved_at?: string | null;
    resolved_by?: number | null;
    /** Set when the manager published this answer to the customer. */
    response_sent_at?: string | null;
    is_response_sent?: boolean;
    /** Set when the customer took this issue off the list. */
    withdrawn_at?: string | null;
    withdrawn_by?: number | null;
    /** Server-decided: the customer withdrew this message's issue. */
    is_withdrawn?: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface EntityFeedbackRoundItem {
    id: number;
    hash_id?: string;
    entity_type: FeedbackEntityType;
    entity_id: number;
    round_number: number;
    status: FeedbackRoundStatus;
    opened_by: number;
    opened_by_user?: EntityFeedbackAuthor | null;
    submitted_at?: string | null;
    submitted_by?: number | null;
    submitted_by_user?: EntityFeedbackAuthor | null;
    /** The manager has read the submission and started work. */
    manager_acknowledged_at?: string | null;
    manager_acknowledged_by?: number | null;
    manager_acknowledged_by_user?: EntityFeedbackAuthor | null;
    assigned_to_user_id?: number | null;
    assigned_to_user?: EntityFeedbackAuthor | null;
    assigned_at?: string | null;
    assigned_by?: number | null;
    technician_submitted_at?: string | null;
    technician_submitted_by?: number | null;
    manager_instructions?: string | null;
    resolved_at?: string | null;
    resolved_by?: number | null;
    resolved_by_user?: EntityFeedbackAuthor | null;
    accepted_at?: string | null;
    accepted_by?: number | null;
    notes?: EntityFeedbackNoteItem[];
    /**
     * The server withheld the notes: this round is still the customer's private
     * draft. Staff must not be shown a single note until it is submitted.
     */
    notes_withheld?: boolean;
    /** Server-decided (admin only): the viewer may erase this whole round. */
    can_delete_round?: boolean;
    created_at?: string;
}

export interface PhotoActionOptions {
    replaceInChangeOrder?: boolean;
    addToChangeOrderPhotos?: boolean;
}

export interface SaveAnnotationPayload {
    entityType: FeedbackEntityType;
    entityId: number;
    /** Optional: a section-level note has no image to mark up. */
    originalImageUrl?: string | null;
    body: string;
    sourcePhotoId?: number | null;
    sourcePhotoType?: string | null;
    replyToNoteId?: number | null;
    requestedAction?: string | null;
    annotatedImageBlob?: Blob | null;
    annotationLayerJson?: string | null;
    replacementImageFile?: File | null;
    replaceInChangeOrder?: boolean;
    addToChangeOrderPhotos?: boolean;
}
