import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { MediaStagingPanel, useMediaStaging } from '@/features/media-edit';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Loader2, Paperclip } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import {
    firstAttachmentError,
    ISSUE_ATTACHMENT_ACCEPT,
    MAX_ATTACHMENTS,
    MAX_FILE_SIZE_BYTES,
    MAX_FILE_SIZE_MB,
    processSingleFile,
} from './issue-attachment-utils';
import { ISSUE_TYPE_LABELS, ISSUE_TYPES, IssueTypeValue } from './issue-types';

export interface ReportIssueDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    projectId: number | string;
}

interface IssueForm {
    type: IssueTypeValue;
    description: string;
    verizon_guidance: string;
}

/** Mirrors StoreIssueHistoryRequest — the backend rejects anything longer. */
const DESCRIPTION_MAX = 3000;

/**
 * The DOM `maxLength` is the primary cap, but paste and IME composition can
 * overshoot it in some engines and the overshoot only surfaces as a 422 after
 * the fact. Clamping here too makes the invariant unconditional: PHP counts
 * fewer characters than JS does for the same string, so a value capped at 3000
 * UTF-16 units can never come back over the limit.
 */
function clampToLimit(value: string): string {
    return value.length > DESCRIPTION_MAX ? value.slice(0, DESCRIPTION_MAX) : value;
}

/** Counter turns amber here, so the limit is felt before it is hit. */
const DESCRIPTION_WARN_RATIO = 0.9;

/** Muted while there is room, amber as the limit approaches, red at the cap. */
function descriptionCounterClass(length: number): string {
    if (length >= DESCRIPTION_MAX) {
        return 'font-semibold text-destructive';
    }

    if (length >= DESCRIPTION_MAX * DESCRIPTION_WARN_RATIO) {
        return 'font-medium text-amber-600 dark:text-amber-400';
    }

    return 'text-muted-foreground';
}

export function ReportIssueDialog({
    open,
    onOpenChange,
    projectId,
}: Readonly<ReportIssueDialogProps>) {
    const [compressing, setCompressing] = React.useState(false);
    const [clientError, setClientError] = React.useState<string | null>(null);
    // Files wait here until submit, so an image can be marked up before it is
    // sent — the form data itself no longer carries the File list.
    const staging = useMediaStaging();

    const form = useForm<IssueForm>({
        type: 'lock',
        description: '',
        verizon_guidance: '',
    });

    const fileInputRef = React.useRef<HTMLInputElement>(null);

    const descriptionLength = form.data.description.length;

    React.useEffect(() => {
        if (!open) {
            setClientError(null);
        }
    }, [open]);

    const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files || e.target.files.length === 0) return;
        const selectedFiles = Array.from(e.target.files);
        e.target.value = '';
        setClientError(null);

        const availableSlots = MAX_ATTACHMENTS - staging.items.length;
        if (availableSlots <= 0) {
            const msg = `Maximum ${MAX_ATTACHMENTS} attachments reached. Remove existing files before adding more.`;
            setClientError(msg);
            toast.error(msg);
            return;
        }

        const filesToProcess = selectedFiles.slice(0, availableSlots);
        if (selectedFiles.length > availableSlots) {
            toast.warning(
                `Only ${availableSlots} more attachment(s) could be added (max ${MAX_ATTACHMENTS}).`
            );
        }

        setCompressing(true);
        const validProcessedFiles: File[] = [];
        const rejectedErrors: string[] = [];

        try {
            for (const file of filesToProcess) {
                const result = await processSingleFile(file);
                if (result.ok) {
                    validProcessedFiles.push(result.file);
                } else {
                    rejectedErrors.push(result.error);
                }
            }

            staging.add(validProcessedFiles);

            if (rejectedErrors.length > 0) {
                const errorMsg = rejectedErrors.join(' ');
                setClientError(errorMsg);
                toast.error(errorMsg);
            }
        } finally {
            setCompressing(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (compressing || form.processing) return;
        setClientError(null);

        const oversizedFile = staging.items.find(
            (item) => item.file.size > MAX_FILE_SIZE_BYTES
        )?.file;
        if (oversizedFile) {
            const sizeMb = (oversizedFile.size / (1024 * 1024)).toFixed(1);
            const msg = `"${oversizedFile.name}" (${sizeMb}MB) exceeds the ${MAX_FILE_SIZE_MB}MB limit. Please remove it before submitting.`;
            setClientError(msg);
            toast.error(msg);
            return;
        }

        form.transform((data) => ({
            ...data,
            attachments: staging.items.map((item) => item.file),
        }));
        form.post(route('projects.issues.store', projectId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                staging.reset();
                setClientError(null);
                onOpenChange(false);
            },
            onError: (errors) => {
                const attErr = Object.keys(errors)
                    .filter((k) => k.startsWith('attachments'))
                    .map((k) => errors[k])
                    .find(Boolean);
                if (attErr) {
                    toast.error(attErr);
                }
            },
        });
    };

    // `attachments` is no longer form state (the Files live in staging), but the
    // server still validates it, so its errors arrive under that key.
    const backendAttachmentError = firstAttachmentError(
        form.errors as Record<string, string | undefined>
    );

    const activeAttachmentError = clientError || backendAttachmentError;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto no-scrollbar">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <div>
                            <DialogTitle className="text-base font-bold">
                                Report Site Issue
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                Log a blocker or access impediment found at the site.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={handleSubmit} noValidate className="space-y-4">
                    <FormField
                        label="Issue Category"
                        htmlFor="dialog_type"
                        required
                        error={form.errors.type}
                    >
                        <select
                            id="dialog_type"
                            name="type"
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value as IssueTypeValue)}
                            className="h-9 w-full rounded-lg border border-input bg-card px-3 text-xs font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            required
                        >
                            {ISSUE_TYPES.map((type) => (
                                <option key={type} value={type}>
                                    {ISSUE_TYPE_LABELS[type]}
                                </option>
                            ))}
                        </select>
                    </FormField>

                    <FormField
                        label="Problem Description"
                        htmlFor="dialog_description"
                        required
                        error={form.errors.description}
                    >
                        <Textarea
                            id="dialog_description"
                            name="description"
                            value={form.data.description}
                            onChange={(e) => {
                                /* Clear on edit. Otherwise a "too long" error
                                   survives the user fixing the text and sits
                                   under a counter that now reads in range —
                                   which reads as the server disagreeing with
                                   the client. */
                                form.clearErrors('description');
                                form.setData(
                                    'description',
                                    clampToLimit(e.target.value)
                                );
                            }}
                            placeholder="Describe the issue in detail (e.g., Gate locked, key code mismatch, tower access blocked)..."
                            maxLength={DESCRIPTION_MAX}
                            className="min-h-[100px] text-xs resize-none"
                            required
                            error={Boolean(form.errors.description)}
                        />
                        <div className="flex justify-end mt-1">
                            <span className={cn('text-[10px] tabular-nums', descriptionCounterClass(descriptionLength))}>
                                {descriptionLength.toLocaleString()} /{' '}
                                {DESCRIPTION_MAX.toLocaleString()}
                            </span>
                        </div>
                    </FormField>

                    <FormField
                        label="Verizon Guidance (Optional)"
                        htmlFor="dialog_verizon_guidance"
                        error={form.errors.verizon_guidance}
                    >
                        <Input
                            id="dialog_verizon_guidance"
                            name="verizon_guidance"
                            value={form.data.verizon_guidance}
                            onChange={(e) => {
                                form.clearErrors('verizon_guidance');
                                form.setData(
                                    'verizon_guidance',
                                    clampToLimit(e.target.value)
                                );
                            }}
                            placeholder="e.g. Call NOC at 800-xxx-xxxx or wait for escort"
                            maxLength={DESCRIPTION_MAX}
                            className="h-9 text-xs"
                            error={Boolean(form.errors.verizon_guidance)}
                        />
                    </FormField>

                    {/* Attach Photos or Videos */}
                    <div className="space-y-1.5">
                        <label
                            htmlFor="dialog_attachments"
                            className="text-xs font-semibold text-foreground flex items-center justify-between cursor-pointer"
                        >
                            <span>Photos & Videos (Optional)</span>
                            <span className="text-[10px] text-muted-foreground font-normal">
                                Max 10 files (5MB each)
                            </span>
                        </label>

                        <input
                            id="dialog_attachments"
                            name="dialog_attachments"
                            type="file"
                            ref={fileInputRef}
                            onChange={handleFileChange}
                            multiple
                            accept={ISSUE_ATTACHMENT_ACCEPT}
                            className="hidden"
                        />

                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="w-full flex items-center justify-center gap-2 p-3 rounded-xl border border-dashed border-border/90 hover:border-primary/50 bg-card hover:bg-muted/40 transition-colors text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            <Paperclip className="size-4" />
                            <span>Upload Photos or Videos</span>
                        </button>

                        {/* Error banner for attachments */}
                        {activeAttachmentError && (
                            <div className="rounded-lg border border-destructive/25 bg-destructive/10 p-2.5 flex items-start gap-2 text-xs font-medium text-destructive animate-in fade-in">
                                <AlertCircle className="size-4 shrink-0 mt-0.5" />
                                <span className="flex-1">{activeAttachmentError}</span>
                            </div>
                        )}

                        {/* Staged files — annotate an image with the pencil, or drop it */}
                        <MediaStagingPanel staging={staging} />
                    </div>

                    {/* Progress indicator during compression/upload */}
                    {(compressing || form.processing) && (
                        <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-primary/10 border border-primary/20 text-primary text-xs font-medium animate-in fade-in duration-150">
                            <Loader2 className="size-4 animate-spin text-primary shrink-0" />
                            <span className="font-semibold">
                                {compressing
                                    ? 'Compressing photos on the fly...'
                                    : 'Reporting issue & uploading media, please wait...'}
                            </span>
                        </div>
                    )}

                    <DialogFooter className="gap-2 sm:gap-0 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing || compressing}
                            className="text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={
                                form.processing || compressing || !form.data.description.trim()
                            }
                            className="text-xs bg-foreground text-background hover:bg-foreground/90 font-semibold"
                        >
                            {form.processing || compressing ? (
                                <span className="flex items-center gap-1.5">
                                    <Loader2 className="size-3.5 animate-spin" />
                                    <span>{compressing ? 'Compressing...' : 'Reporting...'}</span>
                                </span>
                            ) : (
                                'Report Issue'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
