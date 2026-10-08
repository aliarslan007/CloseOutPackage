import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { cn } from '@/lib/utils';
import * as React from 'react';
import { DocumentRow } from './document-row';
import type { DocumentCategory, ProjectDocument } from './types';
import { useDocumentUpload } from './use-document-upload';
import { ACCEPT_ATTR } from './utils';

export interface DocumentSectionProps {
    projectKey: number | string;
    category: DocumentCategory;
    documents: ProjectDocument[];
    canManage: boolean;
    registerUploadInput: (collection: string, input: HTMLInputElement | null) => void;
    onUploadStatusChange: (collection: string, isUploading: boolean, progress: number) => void;
}

export function DocumentSection({
    projectKey,
    category,
    documents,
    canManage,
    registerUploadInput,
    onUploadStatusChange,
}: Readonly<DocumentSectionProps>) {
    const [isDragOver, setIsDragOver] = React.useState(false);
    const {
        upload,
        isUploading,
        progress,
        pendingDelete,
        requestDelete,
        confirmDelete,
        cancelDelete,
        isDeleting,
    } = useDocumentUpload({ projectKey, category });

    const count = documents.length;

    React.useEffect(() => {
        onUploadStatusChange(category.collection, isUploading, progress);
    }, [category.collection, isUploading, onUploadStatusChange, progress]);

    const onInputChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        upload(event.target.files);
        // Reset so re-selecting the same file still fires a change event.
        event.target.value = '';
    };

    const onDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragOver(false);
        upload(event.dataTransfer.files);
    };

    return (
        <div>
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setIsDragOver(true);
                }}
                onDragLeave={() => setIsDragOver(false)}
                onDrop={onDrop}
                className={cn(
                    'rounded-lg border border-transparent transition-colors',
                    isDragOver && 'border-dashed border-primary bg-primary/5'
                )}
            >
                {count > 0 ? (
                    <ul className="no-scrollbar flex w-full flex-nowrap gap-2 overflow-x-auto overflow-y-hidden pb-1">
                        {documents.map((doc) => (
                            <DocumentRow
                                key={doc.id}
                                doc={doc}
                                canManage={canManage}
                                onRequestDelete={requestDelete}
                            />
                        ))}
                    </ul>
                ) : (
                    <div className="px-3 py-4 text-center">
                        <p className="text-[11px] font-medium text-muted-foreground">
                            No documents uploaded yet.
                        </p>
                        <p className="mt-0.5 text-[11px] text-muted-foreground/80">
                            {category.description}
                        </p>
                    </div>
                )}
            </div>

            <input
                ref={(input) => registerUploadInput(category.collection, input)}
                type="file"
                multiple
                accept={ACCEPT_ATTR}
                onChange={onInputChange}
                className="hidden"
                aria-hidden
                tabIndex={-1}
            />

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(next) => {
                    if (!next && !isDeleting) cancelDelete();
                }}
                title="Remove document"
                description={
                    pendingDelete
                        ? `Remove "${pendingDelete.name}" from this project? This cannot be undone.`
                        : ''
                }
                confirmLabel="Remove"
                variant="destructive"
                loading={isDeleting}
                onConfirm={confirmDelete}
            />
        </div>
    );
}
