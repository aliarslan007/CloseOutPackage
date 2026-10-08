/** One document row in the Project Documents Hub, shaped by ProjectDocumentPresenter. */
export interface ProjectDocument {
    id: number;
    name: string;
    mime_type: string;
    size_bytes: number;
    uploaded_by?: string | null;
    uploaded_at?: string | null;
    view_url: string;
    download_url: string;
}

/** Documents keyed by media collection name (e.g. `rec_documents`). */
export type ProjectDocumentsMap = Record<string, ProjectDocument[]>;

/** A single hub section: media collection, route slug, and display copy. */
export interface DocumentCategory {
    /** Media collection name — also the key into ProjectDocumentsMap. */
    collection: string;
    /** Route segment for the generic document routes. */
    slug: string;
    label: string;
    description: string;
}
