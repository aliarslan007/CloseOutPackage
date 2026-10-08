import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { usePdfReadySignal } from '@/hooks/use-pdf-ready-signal';
import { usePdfGenerationStore } from '@/stores/pdf-generation.store';
import { router } from '@inertiajs/react';
import { Eye, FileText, Mail, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export interface PackagePdfActionsProps {
    /** Route key (may be an obfuscated hash id). */
    packageId: number | string;
    /**
     * Numeric model id, used to match the "PDF ready" notification payload
     * (which always stores the numeric id). Falls back to packageId.
     */
    numericPackageId?: number | string;
    status?: string;
    finalPdfAvailable: boolean;
    partialPdfAvailable?: boolean;
    defaultEmail?: string;
    canManage: boolean;
    finalPdfGeneratedAt?: string | null;
    partialPdfGeneratedAt?: string | null;
}

type EmailTarget = 'final' | 'progress';

function formatTime(value?: string | null): string {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

interface EmailPdfDialogProps {
    target: EmailTarget;
    email: string;
    sending: boolean;
    onEmailChange: (value: string) => void;
    onClose: () => void;
    onSend: () => void;
}

/**
 * Collects the recipient for a PDF email. The message body comes from the
 * active template for the type — managers edit those in the Email Templates
 * module, so nothing template-related is exposed here.
 */
function EmailPdfDialog({
    target,
    email,
    sending,
    onEmailChange,
    onClose,
    onSend,
}: Readonly<EmailPdfDialogProps>) {
    const isFinal = target === 'final';
    const trimmed = email.trim();

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !sending) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <Mail className="size-4 text-primary" />
                        {isFinal ? 'Email Final PDF' : 'Email Progress PDF'}
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        Confirm or change the recipient and send the{' '}
                        {isFinal ? 'final' : 'progress'} PDF report. The email uses the active
                        template for this type.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-1.5">
                    <Label htmlFor="package-pdf-email" className="text-xs">
                        Recipient email
                    </Label>
                    <input
                        id="package-pdf-email"
                        type="email"
                        value={email}
                        onChange={(e) => onEmailChange(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                onSend();
                            }
                        }}
                        placeholder="recipient@example.com"
                        autoFocus
                        disabled={sending}
                        className="h-9 w-full rounded-lg border border-border/80 bg-muted/30 px-3 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:bg-card focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                    <p className="text-[11px] text-muted-foreground">
                        Pre-filled with the project engineer&apos;s address. Clear it to send
                        somewhere else.
                    </p>
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onClose}
                        disabled={sending}
                        className="h-9 text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={onSend}
                        loading={sending}
                        disabled={trimmed === ''}
                        className="h-9 gap-1.5 text-xs"
                    >
                        <Send className="size-3.5" />
                        {isFinal ? 'Send Final PDF' : 'Send Progress PDF'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function PackagePdfActions({
    packageId,
    numericPackageId,
    status,
    finalPdfAvailable,
    partialPdfAvailable = false,
    defaultEmail,
    canManage,
    finalPdfGeneratedAt = null,
    partialPdfGeneratedAt = null,
}: Readonly<PackagePdfActionsProps>) {
    const matchPackageId = numericPackageId ?? packageId;
    const [busy, setBusy] = useState<
        'final_pdf' | 'partial_pdf' | 'email_final' | 'email_progress' | null
    >(null);
    const [emailTarget, setEmailTarget] = useState<EmailTarget | null>(null);
    const [emailTo, setEmailTo] = useState('');
    const markPending = usePdfGenerationStore((state) => state.markPending);
    const clearPending = usePdfGenerationStore((state) => state.clearPending);
    const finalPending = usePdfGenerationStore((state) => state.isPending(matchPackageId, 'final'));
    const partialPending = usePdfGenerationStore((state) =>
        state.isPending(matchPackageId, 'partial'),
    );

    const {
        ready: finalSignal,
        expired: finalExpired,
        generatedAt: finalSignalGeneratedAt,
    } = usePdfReadySignal(matchPackageId, 'final');
    const {
        ready: partialSignal,
        expired: partialExpired,
        generatedAt: partialSignalGeneratedAt,
    } = usePdfReadySignal(matchPackageId, 'partial');

    const isApproved = status === 'approved';
    const downloadUrl =
        finalPdfAvailable || finalSignal ? route('packages.pdf.download', packageId) : null;
    const partialDownloadUrl =
        partialPdfAvailable || partialSignal
            ? route('packages.pdf.partial-download', packageId)
            : null;
    // A freshly-landed ready-signal always reflects the newest generation, so
    // it wins over the server prop (which is only as fresh as the last reload).
    const effectiveFinalGeneratedAt = finalSignal ? finalSignalGeneratedAt : finalPdfGeneratedAt;
    const effectivePartialGeneratedAt = partialSignal
        ? partialSignalGeneratedAt
        : partialPdfGeneratedAt;

    // If the server already reports the PDF available (e.g. we returned to the
    // page after generation completed), the in-flight flag is stale — drop it
    // so we don't keep polling or show a misleading timeout toast.
    useEffect(() => {
        if (finalPdfAvailable) {
            clearPending(matchPackageId, 'final');
        }
    }, [finalPdfAvailable, matchPackageId, clearPending]);

    useEffect(() => {
        if (partialPdfAvailable) {
            clearPending(matchPackageId, 'partial');
        }
    }, [partialPdfAvailable, matchPackageId, clearPending]);

    useEffect(() => {
        if (finalSignal) {
            toast.success('Final PDF is ready to download.');
        }
    }, [finalSignal]);

    useEffect(() => {
        if (partialSignal) {
            toast.success('Progress PDF is ready to download.');
        }
    }, [partialSignal]);

    useEffect(() => {
        if (finalExpired) {
            toast.error('Final PDF generation timed out. Please try again.');
        }
    }, [finalExpired]);

    useEffect(() => {
        if (partialExpired) {
            toast.error('Progress PDF generation timed out. Please try again.');
        }
    }, [partialExpired]);

    const generatePdf = (kind: 'final_pdf' | 'partial_pdf') => {
        setBusy(kind);
        markPending(matchPackageId, kind === 'final_pdf' ? 'final' : 'partial');
        const isFinal = kind === 'final_pdf';
        router.post(
            route(
                isFinal ? 'manager.packages.pdf.final' : 'manager.packages.pdf.partial',
                packageId
            ),
            { closeout_package_id: packageId },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        isFinal
                            ? 'Final PDF generation started.'
                            : 'Partial PDF generation started.'
                    ),
                onError: () => {
                    clearPending(matchPackageId, kind === 'final_pdf' ? 'final' : 'partial');
                    toast.error('Could not start PDF generation. Please try again.');
                },
                onFinish: () => setBusy(null),
            }
        );
    };

    const openEmailDialog = (target: EmailTarget) => {
        // Pre-fill with the project engineer's address; the manager can clear it.
        setEmailTo(defaultEmail ?? '');
        setEmailTarget(target);
    };

    const closeEmailDialog = () => {
        setEmailTarget(null);
        setEmailTo('');
    };

    const sendEmail = () => {
        if (emailTarget === null) {
            return;
        }

        const trimmed = emailTo.trim();

        if (trimmed === '') {
            toast.error('Please enter an email address.');
            return;
        }

        const isFinal = emailTarget === 'final';
        setBusy(isFinal ? 'email_final' : 'email_progress');
        router.post(
            route(
                isFinal ? 'manager.packages.pdf.email' : 'manager.packages.pdf.progress-email',
                packageId
            ),
            {
                email_to: trimmed,
                closeout_package_id: packageId,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        isFinal ? 'Final PDF email queued.' : 'Progress PDF email queued.'
                    );
                    closeEmailDialog();
                },
                onError: () => {
                    toast.error('Could not send the email. Please try again.');
                },
                onFinish: () => setBusy(null),
            }
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            {(canManage && isApproved) || downloadUrl ? (
                <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-border/70 bg-muted/20 p-1 shadow-sm">
                    {canManage && isApproved && (
                        <>
                            <Button
                                size="sm"
                                variant="default"
                                onClick={() => generatePdf('final_pdf')}
                                loading={busy === 'final_pdf' || finalPending}
                                title="Compile and store the official full close-out PDF package report."
                                className="h-9 px-3.5 font-medium"
                            >
                                <FileText className="size-3.5" /> Generate Final PDF
                            </Button>

                            {downloadUrl && (
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => openEmailDialog('final')}
                                    title="Send the generated final PDF report to a recipient via email."
                                    className="h-8 px-2.5 text-[11px]"
                                >
                                    <Mail className="size-3.5" /> Email Final PDF
                                </Button>
                            )}
                        </>
                    )}

                    {downloadUrl && (
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="h-8 px-2.5 text-[11px]"
                        >
                            <a
                                href={downloadUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                title={
                                    effectiveFinalGeneratedAt
                                        ? `View final PDF (Generated ${formatTime(effectiveFinalGeneratedAt)})`
                                        : 'View the official final close-out PDF package report.'
                                }
                            >
                                <Eye className="size-3.5" /> View Final PDF
                                {effectiveFinalGeneratedAt && (
                                    <span className="text-muted-foreground">
                                        ({formatTime(effectiveFinalGeneratedAt)})
                                    </span>
                                )}
                            </a>
                        </Button>
                    )}
                </div>
            ) : null}
            {canManage && (!isApproved || (partialDownloadUrl && !downloadUrl)) && (
                <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-border/70 bg-muted/20 p-1 shadow-sm">
                    {!isApproved && (
                        <>
                            <Button
                                size="sm"
                                variant="default"
                                onClick={() => generatePdf('partial_pdf')}
                                loading={busy === 'partial_pdf' || partialPending}
                                title="Compile a progress PDF preview of currently approved site photos."
                                className="h-9 px-3.5 font-medium"
                            >
                                <FileText className="size-3.5" /> Generate Partial PDF
                            </Button>

                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => openEmailDialog('progress')}
                                title="Email a progress PDF update to the project engineer."
                                className="h-8 px-2.5 text-[11px]"
                            >
                                <Mail className="size-3.5" /> Email Progress PDF
                            </Button>
                        </>
                    )}

                    {/* View Partial PDF is only relevant before the final report exists —
                        once the Final PDF is generated the partial preview is superseded. */}
                    {partialDownloadUrl && !downloadUrl && (
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="h-8 px-2.5 text-[11px]"
                        >
                            <a
                                href={partialDownloadUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                title={
                                    effectivePartialGeneratedAt
                                        ? `View progress PDF (Generated ${formatTime(effectivePartialGeneratedAt)})`
                                        : 'View the progress PDF report for this package.'
                                }
                            >
                                <Eye className="size-3.5" /> View Partial PDF
                                {effectivePartialGeneratedAt && (
                                    <span className="text-muted-foreground">
                                        ({formatTime(effectivePartialGeneratedAt)})
                                    </span>
                                )}
                            </a>
                        </Button>
                    )}
                </div>
            )}

            {emailTarget !== null && (
                <EmailPdfDialog
                    target={emailTarget}
                    email={emailTo}
                    sending={busy === 'email_final' || busy === 'email_progress'}
                    onEmailChange={setEmailTo}
                    onClose={closeEmailDialog}
                    onSend={sendEmail}
                />
            )}
        </div>
    );
}

export default PackagePdfActions;
