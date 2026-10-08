const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm', 'mkv'];

/**
 * Extension check as well as MIME: phones and older Android browsers hand back
 * an empty `type` for camera video, and offering those an Edit button would
 * drop the user into a canvas that cannot load them.
 */
export function isVideoFile(file: File): boolean {
    if (file.type.startsWith('video/')) return true;
    if (file.type.startsWith('image/')) return false;
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
    return VIDEO_EXTENSIONS.includes(ext);
}

/**
 * `quote.pdf`/`site-photo.jpg` → `site-photo-annotated.jpg`. A name with no
 * extension (or a dotfile) just gets the suffix appended, so nothing is lost.
 */
export function annotatedFileName(name: string): string {
    const dot = name.lastIndexOf('.');
    return dot > 0
        ? `${name.slice(0, dot)}-annotated${name.slice(dot)}`
        : `${name}-annotated`;
}

/** The canvas exports a Blob; uploads need a File. */
export function blobToFile(blob: Blob, name: string): File {
    return new File([blob], name, { type: blob.type || 'image/jpeg' });
}
