export function sliceFileIntoChunks(file: File | Blob, chunkSizeBytes: number): Blob[] {
    const chunks: Blob[] = [];
    const totalSize = file.size;

    if (totalSize === 0) {
        chunks.push(file.slice(0, 0));
        return chunks;
    }

    let start = 0;
    while (start < totalSize) {
        const end = Math.min(start + chunkSizeBytes, totalSize);
        chunks.push(file.slice(start, end));
        start = end;
    }

    return chunks;
}
