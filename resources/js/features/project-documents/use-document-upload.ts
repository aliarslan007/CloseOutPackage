import { router } from '@inertiajs/react';
import axios from 'axios';
import * as React from 'react';
import { toast } from 'sonner';
import type { DocumentCategory } from './types';
import { documentDestroyUrl, documentStoreUrl, validateDocumentFile } from './utils';

export interface PendingDocumentDelete {
    id: number;
    name: string;
}

export interface UseDocumentUploadParams {
    projectKey: number | string;
    category: DocumentCategory;
}

export interface UseDocumentUploadResult {
    upload: (files: FileList | null) => void;
    isUploading: boolean;
    /** Aggregate 0-100 progress across the files in the current batch. */
    progress: number;
    pendingDelete: PendingDocumentDelete | null;
    requestDelete: (document: PendingDocumentDelete) => void;
    confirmDelete: () => void;
    cancelDelete: () => void;
    isDeleting: boolean;
}

/**
 * Upload + delete for a single document category.
 *
 * Files go through axios rather than an Inertia visit on purpose: the global
 * navigation progress bar stays out of a background transfer, each file
 * reports real progress (the page cannot look frozen on a slow link), and a
 * file never triggers a full page re-render. Once the batch finishes we ask
 * Inertia to refresh only `projectDocuments`.
 *
 * ponytail: files upload one at a time. A bandwidth-bound link gains nothing
 * from parallelism, and sequential keeps the progress number honest — swap to
 * Promise.all if per-file parallel progress ever becomes a requirement.
 */
export function useDocumentUpload({ projectKey, category }: UseDocumentUploadParams): UseDocumentUploadResult {
    const [isUploading, setIsUploading] = React.useState(false);
    const [progress, setProgress] = React.useState(0);
    const [pendingDelete, setPendingDelete] = React.useState<PendingDocumentDelete | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const refresh = React.useCallback(() => {
        // `reload` is async → no global progress bar; `only` keeps the payload
        // to the one prop that changed.
        router.reload({ only: ['projectDocuments'] });
    }, []);

    const upload = React.useCallback(
        (files: FileList | null) => {
            const valid = Array.from(files ?? []).filter((file) => {
                const error = validateDocumentFile(file);

                if (error) {
                    toast.error(error);
                    return false;
                }

                return true;
            });

            if (valid.length === 0) return;

            const url = documentStoreUrl(projectKey, category);
            setIsUploading(true);
            setProgress(0);

            const toastId = toast.loading(
                valid.length === 1 ? 'Uploading document…' : `Uploading ${valid.length} documents…`
            );

            void (async () => {
                let uploaded = 0;
                let failed = 0;

                for (const [index, file] of valid.entries()) {
                    const body = new FormData();
                    body.append('document', file);

                    try {
                        await axios.post(url, body, {
                            onUploadProgress: (event) => {
                                if (!event.total) return;
                                const fileFraction = event.loaded / event.total;
                                setProgress(Math.round(((index + fileFraction) / valid.length) * 100));
                            },
                        });
                        uploaded += 1;
                    } catch {
                        failed += 1;
                    }
                }

                setIsUploading(false);
                setProgress(0);

                if (uploaded === 0) {
                    toast.error('Upload failed. Check your connection and try again.', { id: toastId });
                    return;
                }

                toast.success(batchMessage(uploaded, failed, valid.length), { id: toastId });
                refresh();
            })();
        },
        [projectKey, category, refresh]
    );

    const confirmDelete = React.useCallback(() => {
        if (!pendingDelete) return;

        setIsDeleting(true);

        axios
            .delete(documentDestroyUrl(projectKey, category, pendingDelete.id))
            .then(() => toast.success('Document removed.'))
            .catch(() => toast.error('Failed to remove the document.'))
            .finally(() => {
                setIsDeleting(false);
                setPendingDelete(null);
                refresh();
            });
    }, [pendingDelete, projectKey, category, refresh]);

    const cancelDelete = React.useCallback(() => setPendingDelete(null), []);

    return {
        upload,
        isUploading,
        progress,
        pendingDelete,
        requestDelete: setPendingDelete,
        confirmDelete,
        cancelDelete,
        isDeleting,
    };
}

function batchMessage(uploaded: number, failed: number, total: number): string {
    if (failed > 0) return `${uploaded} uploaded, ${failed} failed.`;
    return total === 1 ? 'Document uploaded.' : `${total} documents uploaded.`;
}
