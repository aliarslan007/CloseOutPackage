/** How the annotated result relates to the image it was drawn on. */
export type StageEditMode = 'replace' | 'copy';

/**
 * One file waiting to be uploaded. Files sit here (not on the server) so the
 * uploader can annotate an image before a single byte goes over the wire.
 */
export interface StagedMediaItem {
    id: string;
    file: File;
    /** Object URL for the thumbnail and the canvas; revoked by the staging hook. */
    previewUrl: string;
    isVideo: boolean;
    /**
     * Id of the staged image this was annotated from — null for an untouched
     * pick. Distinguishes an edited copy from the original it sits beside.
     */
    derivedFromId: string | null;
}
