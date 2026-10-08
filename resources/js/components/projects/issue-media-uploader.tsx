import { MediaStagingPanel, useMediaStaging } from '@/features/media-edit';
import { router } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import {
    firstAttachmentError,
    ISSUE_ATTACHMENT_ACCEPT,
    MAX_ATTACHMENTS,
    processSingleFile,
} from './issue-attachment-utils';
import { IssueItem } from './issue-types';

export interface IssueMediaUploaderProps {
    projectId: number | string;
    issue: IssueItem;
    /** Slots still free on the issue (the cap minus what is already attached). */
    remainingSlots: number;
}

/**
 * Picks, annotates and uploads an issue's photos and videos.
 *
 * Files are held locally until the uploader presses Upload, so an image can be
 * marked up (or dropped) before anything is sent — one upload per edited image
 * instead of a round trip for the original and another for the annotation.
 */
export function IssueMediaUploader({
    projectId,
    issue,
    remainingSlots,
}: Readonly<IssueMediaUploaderProps>) {
    const staging = useMediaStaging();
    const [uploading, setUploading] = React.useState(false);
    const fileInputRef = React.useRef<HTMLInputElement>(null);

    const stagedCount = staging.items.length;
    const room = Math.max(0, remainingSlots - stagedCount);
    let pickLabel = 'Add photos or videos';
    if (room === 0) pickLabel = `Max ${MAX_ATTACHMENTS} attachments reached`;
    else if (stagedCount > 0) pickLabel = 'Add more files';

    const handlePick = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (files.length === 0) return;

        if (room <= 0) {
            toast.error(
                `Maximum ${MAX_ATTACHMENTS} attachments reached. Remove one before adding more.`
            );
            return;
        }

        const clamped = files.slice(0, room);
        if (clamped.length < files.length) {
            toast.warning(
                `Only ${clamped.length} of ${files.length} file(s) added — max ${MAX_ATTACHMENTS} per issue.`
            );
        }

        const valid: File[] = [];
        for (const file of clamped) {
            const result = await processSingleFile(file);
            if (result.ok) valid.push(result.file);
            else toast.error(result.error);
        }

        staging.add(valid);
    };

    const handleUpload = () => {
        if (stagedCount === 0) return;

        const formData = new FormData();
        staging.items.forEach((item) => formData.append('attachments[]', item.file));

        setUploading(true);
        router.post(
            route('projects.issues.attachments.store', [projectId, issue.hash_id || issue.id]),
            formData,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Media added to issue.');
                    staging.reset();
                },
                onError: (errors) => {
                    toast.error(
                        firstAttachmentError(errors) ?? 'Could not add media to this issue.'
                    );
                },
                onFinish: () => setUploading(false),
            }
        );
    };

    return (
        <div className="space-y-1.5">
            <MediaStagingPanel
                staging={staging}
                onUpload={handleUpload}
                isUploading={uploading}
                uploadNoun="file"
            />

            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept={ISSUE_ATTACHMENT_ACCEPT}
                onChange={handlePick}
                className="hidden"
                disabled={uploading}
            />
            <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                disabled={uploading || room === 0}
                className="w-full flex items-center justify-center gap-2 p-2.5 rounded-xl border border-dashed border-border/90 hover:border-primary/50 bg-card hover:bg-muted/40 transition-colors text-xs font-medium text-muted-foreground hover:text-foreground disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
                <Paperclip className="size-4" />
                <span>{pickLabel}</span>
            </button>

            {stagedCount === 0 && remainingSlots > 0 && (
                <p className="text-[10px] text-muted-foreground text-center">
                    No media attached yet — photos & videos up to {MAX_ATTACHMENTS} files, 5MB
                    each. Tap the pencil on a photo to mark it up first.
                </p>
            )}
        </div>
    );
}
