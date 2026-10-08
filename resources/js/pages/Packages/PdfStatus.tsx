import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { AppLayout } from '@/layouts/app-layout';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Download, FileText, Loader2, RotateCw } from 'lucide-react';

interface PdfStatusPackage {
    id: number;
    hash_id: string;
    status: string;
    project_id: number;
    project_name: string;
    photos_count: number;
}

interface PdfStatusRevision {
    id: number;
    status: string;
    status_label: string;
    type: string;
    revision_label: string;
    total_pages: number | null;
    photo_count: number | null;
    generated_at: string | null;
    duration_ms: number | null;
}

interface PdfStatusProps {
    package: PdfStatusPackage;
    revision: PdfStatusRevision | null;
    progress: number;
    isReady: boolean;
    isGenerating: boolean;
    isFailed: boolean;
    totalChunks?: number | null;
    processedChunks?: number | null;
    failedChunks?: number | null;
    downloadUrl: string | null;
}

export function PdfStatus({
    package: pkg,
    revision,
    progress,
    isReady,
    isGenerating,
    isFailed,
    totalChunks,
    processedChunks,
    downloadUrl,
}: Readonly<PdfStatusProps>) {
    // Auto-poll while generating so the manager sees live chunk progress
    usePoll(
        2500,
        {
            only: ['progress', 'isReady', 'isGenerating', 'isFailed', 'totalChunks', 'processedChunks', 'revision', 'downloadUrl'],
        },
        {
            autoStart: isGenerating,
        }
    );

    const handleRetry = () => {
        router.post(
            route('manager.packages.pdf.final', pkg.hash_id || pkg.id),
            { closeout_package_id: pkg.hash_id || pkg.id },
            { preserveScroll: true }
        );
    };

    const reviewUrl = route('packages.stages.show', {
        package: pkg.hash_id || pkg.id,
        stage: 'pre_construction',
    });

    return (
        <AppLayout
            breadcrumb={[
                { label: 'Close-Out Packages', href: route('packages.index', { project: pkg.project_id }) },
                { label: pkg.project_name, href: reviewUrl },
                { label: 'PDF Status' },
            ]}
        >
            <Head title={`PDF Generation — ${pkg.project_name}`} />

            <div className="mx-auto max-w-xl space-y-4 py-6">
                <Link
                    href={reviewUrl}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to Package Review
                </Link>

                <Card className="border shadow-sm">
                    <CardHeader className="pb-4">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-lg font-semibold flex items-center gap-2">
                                <FileText className="size-5 text-primary" />
                                {revision?.revision_label ?? 'Final'} PDF Generation
                            </CardTitle>
                            {isGenerating && (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                    <Loader2 className="size-3 animate-spin" /> In Progress
                                </span>
                            )}
                            {isReady && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                    <CheckCircle2 className="size-3" /> Ready
                                </span>
                            )}
                            {isFailed && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2.5 py-0.5 text-xs font-medium text-destructive">
                                    <AlertTriangle className="size-3" /> Failed
                                </span>
                            )}
                        </div>
                        <CardDescription className="text-xs">
                            {pkg.project_name} — {pkg.photos_count} total site photos
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {isGenerating && (
                            <div className="space-y-2">
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>
                                        {totalChunks && processedChunks !== undefined
                                            ? `Rendering chunks (${processedChunks} of ${totalChunks} complete)`
                                            : 'Compiling document and photos…'}
                                    </span>
                                    <span className="font-semibold text-foreground">{progress}%</span>
                                </div>
                                <Progress value={progress} className="h-2" />
                                <p className="text-[11px] text-muted-foreground">
                                    Rendering is divided into independent parallel chunks. You can navigate away safely; generation will continue in the background.
                                </p>
                            </div>
                        )}

                        {isReady && (
                            <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-4 text-xs space-y-2">
                                <p className="font-medium text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                                    <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    PDF generation completed successfully
                                </p>
                                <div className="grid grid-cols-2 gap-2 pt-1 text-muted-foreground">
                                    <div>Total Pages: <span className="font-medium text-foreground">{revision?.total_pages ?? 'N/A'}</span></div>
                                    <div>Photos Included: <span className="font-medium text-foreground">{revision?.photo_count ?? 'N/A'}</span></div>
                                    {revision?.duration_ms && (
                                        <div>Duration: <span className="font-medium text-foreground">{(revision.duration_ms / 1000).toFixed(1)}s</span></div>
                                    )}
                                    {revision?.generated_at && (
                                        <div>Completed: <span className="font-medium text-foreground">{new Date(revision.generated_at).toLocaleTimeString()}</span></div>
                                    )}
                                </div>
                            </div>
                        )}

                        {isFailed && (
                            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive space-y-1.5">
                                <p className="font-medium flex items-center gap-1.5">
                                    <AlertTriangle className="size-4 shrink-0" />
                                    PDF generation failed
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    An error occurred during chunk rendering or document assembly. Please retry or check that all required media is accessible.
                                </p>
                            </div>
                        )}
                    </CardContent>

                    <CardFooter className="flex justify-end gap-2 border-t pt-3">
                        {isFailed && (
                            <Button size="sm" variant="default" onClick={handleRetry} className="gap-1.5 text-xs">
                                <RotateCw className="size-3.5" /> Retry Generation
                            </Button>
                        )}
                        {isReady && downloadUrl && (
                            <Button asChild size="sm" className="gap-1.5 text-xs font-medium">
                                <a href={downloadUrl} target="_blank" rel="noopener noreferrer">
                                    <Download className="size-3.5" /> Download PDF
                                </a>
                            </Button>
                        )}
                        <Button asChild variant="outline" size="sm" className="text-xs">
                            <Link href={reviewUrl}>Return to Review</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}

export default PdfStatus;
