import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SowReportStatusBadge } from '@/features/sow/components/report/sow-report-status-badge';
import type {
    SowAnnotatedImage,
    SowChecklistEntry,
    SowReport,
    SowReportAbilities,
} from '@/features/sow/types/sow.types';
import { cn } from '@/lib/utils';
import { CheckCircle2, CheckSquare, Download, FileText, Loader2, Lock, RotateCcw, Send, Undo2 } from 'lucide-react';

export interface SowReviewTabProps {
    report: SowReport;
    checklist: SowChecklistEntry[];
    images: SowAnnotatedImage[];
    can: SowReportAbilities;
    isPublishing: boolean;
    isSubmitting: boolean;
    isReopening: boolean;
    isRegenerating?: boolean;
    onPublish: () => void;
    onSubmit: () => void;
    onReopen: () => void;
    onRegenerate?: () => void;
}

export function SowReviewTab({
    report,
    checklist,
    images,
    can,
    isPublishing,
    isSubmitting,
    isReopening,
    isRegenerating = false,
    onPublish,
    onSubmit,
    onReopen,
    onRegenerate,
}: Readonly<SowReviewTabProps>) {
    const answeredCount = checklist.filter((i) => i.response !== null).length;
    const pendingRequiredCount = checklist.filter((i) => i.is_required && i.response === null).length;
    const pdfPhotosCount = images.filter((i) => i.include_in_pdf).length;
    const hasCover = images.some((i) => i.is_cover);

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-semibold">
                        Scope of Work Sign-Off & Finalization
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Review report completeness, verify photos, and proceed with approval or submission.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-lg border border-border p-3.5 space-y-1">
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Current Status</span>
                                <SowReportStatusBadge status={report.status} />
                            </div>
                            <p className="text-sm font-semibold capitalize text-foreground">
                                {report.status.replace('_', ' ')}
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                Revision {report.revision} · {report.revision_date ?? 'No date'}
                            </p>
                        </div>

                        <div className="rounded-lg border border-border p-3.5 space-y-1">
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Survey Checklist</span>
                                <CheckSquare className="size-3.5" />
                            </div>
                            <p className="text-sm font-semibold text-foreground">
                                {answeredCount} / {checklist.length} Answered
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                {pendingRequiredCount === 0 ? (
                                    <span className="text-emerald-600 font-medium">All required items answered</span>
                                ) : (
                                    <span className="text-amber-600 font-medium">
                                        {pendingRequiredCount} required item(s) pending
                                    </span>
                                )}
                            </p>
                        </div>

                        <div className="rounded-lg border border-border p-3.5 space-y-1">
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Annotated Photos</span>
                                <FileText className="size-3.5" />
                            </div>
                            <p className="text-sm font-semibold text-foreground">
                                {images.length} Attached
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                {pdfPhotosCount} included in PDF · {hasCover ? 'Cover selected' : 'No cover set'}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border border-border p-4 space-y-3 bg-muted/20">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Summary of Key Survey Fields
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span className="text-muted-foreground">Site Name: </span>
                                <span className="font-medium text-foreground">{report.site_name || '—'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Fiber Provider: </span>
                                <span className="font-medium text-foreground">{report.fiber_provider || 'VZB'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">PS #: </span>
                                <span className="font-medium text-foreground">{report.ps_number || '—'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Demarcation: </span>
                                <span className="font-medium text-foreground">{report.demarcation || '—'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Field Survey Date: </span>
                                <span className="font-medium text-foreground">
                                    {report.field_survey_date ? report.field_survey_date.slice(0, 10) : '—'}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Survey Contractor: </span>
                                <span className="font-medium text-foreground">{report.survey_contractor_name || '—'}</span>
                            </div>
                        </div>
                    </div>

                    {report.pdf_generation_status === 'generating' && (
                        <div className="flex items-center gap-2.5 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                            <Loader2 className="size-3.5 shrink-0 animate-spin" />
                            <span>Generating the SOW PDF — this panel updates itself, no reload needed.</span>
                        </div>
                    )}

                    <div className="border-t border-border pt-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                {report.status === 'draft' && can.submit && 'Draft mode: make updates, attach photos, and submit for manager review.'}
                                {report.status === 'draft' && !can.update && 'Technician draft in progress: viewing in read-only mode until submitted for review.'}
                                {report.status === 'in_review' && can.publish && 'Submitted for review: review survey data & photos, edit if needed, and finalize.'}
                                {report.status === 'in_review' && !can.publish && 'Submitted for review: awaiting project manager approval and finalization.'}
                                {report.status === 'published' && 'Published: finalized Scope of Work package ready for PDF distribution.'}
                                {report.status === 'archived' && 'Archived: this SOW is stored in terminal read-only state.'}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                            {report.status === 'draft' && !can.update && !can.submit && (
                                <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/60 border border-border/80 rounded-md px-2.5 py-1.5">
                                    <Lock className="size-3.5 text-amber-500 shrink-0" />
                                    <span>Read-only until technician submits</span>
                                </span>
                            )}

                            {can.publish && (
                                <Button
                                    size="sm"
                                    disabled={isPublishing}
                                    onClick={onPublish}
                                    className="gap-1.5 h-9 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    {isPublishing ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <CheckCircle2 className="size-3.5" />
                                    )}
                                    Approve & Finalize SOW
                                </Button>
                            )}

                            {report.status === 'draft' && can.submit && (
                                <Button
                                    size="sm"
                                    disabled={isSubmitting}
                                    onClick={onSubmit}
                                    className="gap-1.5 h-9 text-xs"
                                >
                                    {isSubmitting ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <Send className="size-3.5" />
                                    )}
                                    Submit for Review
                                </Button>
                            )}

                            {
                                report.status === 'published' && can.download && report.pdf_generation_status === 'ready' && (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="info"
                                        className="gap-1.5 h-9 text-xs font-medium"
                                    >
                                        <a
                                            href={route('sow.reports.pdf', report.id)}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <Download className="size-3.5" />
                                            Download SOW PDF
                                        </a>
                                    </Button>
                                )
                            }
                            {report.status === 'published' && can.regenerate && onRegenerate && (() => {
                                const isGenerating = isRegenerating || report.pdf_generation_status === 'generating';
                                let label = 'Regenerate PDF';
                                if (isGenerating) {
                                    label = 'Generating…';
                                } else if (report.pdf_generation_status === 'failed') {
                                    label = 'Retry Generation';
                                }
                                return (
                                    <Button
                                        size="sm"
                                        variant="warning"
                                        disabled={isGenerating}
                                        onClick={onRegenerate}
                                        className="gap-1.5 h-9 text-xs font-medium"
                                        title="Regenerate the PDF with latest changes"
                                    >
                                        <RotateCcw className={cn("size-3.5", isGenerating && "animate-spin")} />
                                        {label}
                                    </Button>
                                );
                            })()}

                            {
                                (report.status === 'published' || report.status === 'in_review') && can.reopen && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        disabled={isReopening}
                                        onClick={onReopen}
                                        className="gap-1.5 h-9 text-xs"
                                    >
                                        <Undo2 className="size-3.5" />
                                        Reopen SOW for Changes
                                    </Button>
                                )
                            }
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
