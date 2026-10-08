/**
 * @deprecated Superseded by `DocumentsHubCard` from `@/features/project-documents`.
 * Kept in the tree until the Project Documents Hub rollout is confirmed, then deleted.
 */
import type { ProjectRecDocument } from '@/components/projects/project-show-types';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { FileUp, FolderOpen, UploadCloud } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { RecDocumentTile } from './rec-document-tile';
import { REC_ACCEPT, validateRecFile } from './rec-document-utils';

export interface RecDocumentsCardProps {
    /** Project route key (hash_id || id). */
    projectKey: number | string;
    documents: ProjectRecDocument[];
    /** Admin or the assigned manager — controls upload/remove. Viewing is page-level. */
    canManage: boolean;
}

const TOAST_ID = 'rec-document-upload';

export function RecDocumentsCard({ projectKey, documents, canManage }: Readonly<RecDocumentsCardProps>) {
    const inputRef = React.useRef<HTMLInputElement>(null);
    const [isDragOver, setIsDragOver] = React.useState(false);
    const [pendingDelete, setPendingDelete] = React.useState<ProjectRecDocument | null>(null);
    const [deleting, setDeleting] = React.useState(false);
    const form = useForm({ document: null as File | null });

    const upload = (file: File) => {
        const error = validateRecFile(file);
        if (error) {
            toast.error(error, { id: TOAST_ID });
            return;
        }

        form.setData('document', file);
        toast.loading('Uploading REC document…', { id: TOAST_ID });
        form.post(route('projects.rec-documents.store', projectKey), {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                toast.success('REC document uploaded.', { id: TOAST_ID });
            },
            onError: () => {
                toast.error('Failed to upload the REC document. Please try again.', { id: TOAST_ID });
            },
        });
    };

    const onInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (file) upload(file);
    };

    const onDrop = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragOver(false);
        const file = e.dataTransfer.files?.[0];
        if (file) upload(file);
    };

    const confirmDelete = () => {
        if (!pendingDelete) return;
        setDeleting(true);
        router.delete(route('projects.rec-documents.destroy', [projectKey, pendingDelete.id]), {
            onSuccess: () => toast.success('REC document removed.'),
            onError: () => toast.error('Failed to remove the REC document.'),
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(null);
            },
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3 pb-3">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <FolderOpen className="size-4 text-primary" /> REC Documents
                    {documents.length > 0 && (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
                            {documents.length}
                        </span>
                    )}
                </CardTitle>
                {canManage && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => inputRef.current?.click()}
                        disabled={form.processing}
                        className="h-8 gap-1.5 text-xs shadow-sm"
                    >
                        <UploadCloud className={cn('size-3.5', form.processing && 'animate-pulse')} />
                        {form.processing ? 'Uploading…' : 'Upload REC'}
                    </Button>
                )}
            </CardHeader>

            <CardContent>
                {canManage && (
                    <div
                        onDragEnter={(e) => {
                            e.preventDefault();
                            setIsDragOver(true);
                        }}
                        onDragOver={(e) => e.preventDefault()}
                        onDragLeave={(e) => {
                            e.preventDefault();
                            setIsDragOver(false);
                        }}
                        onDrop={onDrop}
                        onClick={() => inputRef.current?.click()}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                inputRef.current?.click();
                            }
                        }}
                        role="button"
                        tabIndex={0}
                        aria-label="Upload a REC document"
                        className={cn(
                            'mb-3 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed px-4 py-5 text-center transition-colors focus-visible:outline-2 focus-visible:outline-ring',
                            isDragOver
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50 hover:bg-muted/30'
                        )}
                    >
                        <FileUp className="size-5 text-muted-foreground" />
                        <p className="text-xs font-medium text-foreground">
                            {isDragOver ? 'Drop to upload' : 'Drag & drop the REC, or click to browse'}
                        </p>
                        <p className="text-[11px] text-muted-foreground">PDF, JPG, PNG or WebP · up to 20 MB</p>
                    </div>
                )}

                {documents.length > 0 ? (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {documents.map((doc, index) => (
                            <RecDocumentTile
                                key={doc.id}
                                doc={doc}
                                index={index}
                                canManage={canManage}
                                onRequestDelete={setPendingDelete}
                            />
                        ))}
                    </div>
                ) : (
                    !canManage && (
                        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border/70 py-10 text-center">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <FolderOpen className="size-5" />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                No REC document has been shared yet.
                            </p>
                        </div>
                    )
                )}

                <input
                    ref={inputRef}
                    type="file"
                    accept={REC_ACCEPT}
                    onChange={onInputChange}
                    className="hidden"
                    aria-hidden
                    tabIndex={-1}
                />
            </CardContent>

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) setPendingDelete(null);
                }}
                title="Remove REC document"
                description={
                    pendingDelete
                        ? `Remove "${pendingDelete.name}" from this project? This cannot be undone.`
                        : ''
                }
                confirmLabel="Remove"
                variant="destructive"
                loading={deleting}
                onConfirm={confirmDelete}
            />
        </Card>
    );
}
