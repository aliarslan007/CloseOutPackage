import React, { useEffect, useState } from 'react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Pause, RotateCcw, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { useUploadQueueStore } from '../stores/upload-queue.store';
import type { QueuedFile } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';

export interface UploadProgressItemProps {
    file: QueuedFile;
}

export const UploadProgressItem = React.memo(function UploadProgressItem({
    file,
}: Readonly<UploadProgressItemProps>) {
    const cancelFile = useUploadQueueStore((state) => state.cancelFile);
    const retryFile = useUploadQueueStore((state) => state.retryFile);
    const removeFile = useUploadQueueStore((state) => state.removeFile);
    const isPaused = useUploadQueueStore((state) => state.isPaused);

    const [previewUrl, setPreviewUrl] = useState<string | null>(file.previewUrl ?? null);

    // Lazily create object URL only while item is mounted in DOM, and cleanly revoke on unmount
    useEffect(() => {
        if (file.previewUrl || !file.file.type.startsWith('image/')) return;
        let url: string | null = null;
        try {
            url = URL.createObjectURL(file.file);
            setPreviewUrl(url);
        } catch {
            setPreviewUrl(null);
        }

        return () => {
            if (url) {
                URL.revokeObjectURL(url);
            }
        };
    }, [file.file, file.previewUrl]);

    const isUploading = file.status === 'uploading';
    const isDone = file.status === 'done';
    const isFailed = file.status === 'failed';
    const isPending = file.status === 'pending';
    const isActivelyPaused = isUploading && isPaused;

    return (
        <div className="flex items-center gap-3 rounded-lg border border-border bg-card p-2.5 text-card-foreground shadow-xs transition-colors">
            <div className="relative h-10 w-10 flex-shrink-0 overflow-hidden rounded-md border border-border bg-muted flex items-center justify-center">
                {previewUrl ? (
                    <img
                        src={previewUrl}
                        alt={file.file.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <span className="text-[10px] font-bold uppercase text-muted-foreground">
                        {file.file.name.split('.').pop()?.slice(0, 4) || 'FILE'}
                    </span>
                )}
            </div>

            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex items-center justify-between gap-2">
                    <p className="truncate text-xs font-medium text-foreground" title={file.file.name}>
                        {file.file.name}
                    </p>
                    <span className="flex-shrink-0 text-[11px] text-muted-foreground">
                        {formatFileSize(file.file.size)}
                    </span>
                </div>

                {isUploading && (
                    <div className="flex items-center gap-2">
                        <Progress value={file.progress} className="h-1.5 flex-1" />
                        <span
                            className={`text-[10px] font-medium w-16 text-right truncate ${
                                isActivelyPaused ? 'text-amber-500' : 'text-primary'
                            }`}
                        >
                            {isActivelyPaused ? `Paused ${file.progress}%` : `${file.progress}%`}
                        </span>
                    </div>
                )}

                {isPending && (
                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                        <Clock className="h-3 w-3 text-muted-foreground/70" />
                        <span>Queued</span>
                    </div>
                )}

                {isDone && (
                    <div className="flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2 className="h-3 w-3" />
                        <span>Uploaded</span>
                    </div>
                )}

                {isFailed && (
                    <div className="flex items-center gap-1 text-[11px] text-destructive truncate" title={file.error}>
                        <AlertCircle className="h-3 w-3 flex-shrink-0" />
                        <span className="truncate">{file.error || 'Upload failed'}</span>
                    </div>
                )}
            </div>

            <div className="flex items-center gap-1">
                {isUploading && (
                    <div className="p-1">
                        {isActivelyPaused ? (
                            <Pause className="h-4 w-4 text-amber-500" aria-label="Paused" />
                        ) : (
                            <Loader2 className="h-4 w-4 animate-spin text-primary" aria-label="Uploading" />
                        )}
                    </div>
                )}

                {isFailed && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                        onClick={() => retryFile(file.id)}
                        aria-label={`Retry uploading ${file.file.name}`}
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                    </Button>
                )}

                {(isUploading || isPending) && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-muted-foreground hover:text-foreground"
                        onClick={() => cancelFile(file.id)}
                        aria-label={`Cancel upload of ${file.file.name}`}
                    >
                        <X className="h-3.5 w-3.5" />
                    </Button>
                )}

                {(isDone || file.status === 'aborted') && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-muted-foreground hover:text-foreground"
                        onClick={() => removeFile(file.id)}
                        aria-label={`Dismiss ${file.file.name}`}
                    >
                        <X className="h-3.5 w-3.5" />
                    </Button>
                )}
            </div>
        </div>
    );
});
