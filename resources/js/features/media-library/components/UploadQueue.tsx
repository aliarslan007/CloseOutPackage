import { useMemo, useState } from 'react';
import { ChevronDown, ChevronUp, Pause, Play, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { useUploadQueueStore } from '../stores/upload-queue.store';
import { formatFileSize } from '../utils/format-file-size';
import { VirtualizedUploadList } from './VirtualizedUploadList';
import { UploadQueueTabs, type QueueTab } from './UploadQueueTabs';

function StatusDot({
    inFlightCount,
    failedCount,
    isPaused,
}: Readonly<{ inFlightCount: number; failedCount: number; isPaused: boolean }>) {
    if (isPaused && inFlightCount > 0) {
        return (
            <span className="relative flex h-2.5 w-2.5">
                <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500" />
            </span>
        );
    }
    if (inFlightCount > 0) {
        return (
            <span className="relative flex h-2.5 w-2.5">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75" />
                <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary" />
            </span>
        );
    }
    if (failedCount > 0) {
        return (
            <span className="relative flex h-2.5 w-2.5">
                <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-destructive" />
            </span>
        );
    }
    return (
        <span className="relative flex h-2.5 w-2.5">
            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
        </span>
    );
}

function getQueueTitle(
    inFlightCount: number,
    failedCount: number,
    doneCount: number,
    isPaused: boolean,
): string {
    if (isPaused && inFlightCount > 0) {
        return `Uploads Paused (${inFlightCount} remaining)`;
    }
    if (inFlightCount > 0) {
        const plural = inFlightCount === 1 ? '' : 's';
        return `Uploading ${inFlightCount} file${plural}`;
    }
    if (failedCount > 0) {
        return `${failedCount} upload failed`;
    }
    const plural = doneCount === 1 ? '' : 's';
    return `${doneCount} upload${plural} complete`;
}

export function UploadQueue() {
    const files = useUploadQueueStore((state) => state.files);
    const isPaused = useUploadQueueStore((state) => state.isPaused);
    const isDrawerOpen = useUploadQueueStore((state) => state.isDrawerOpen);
    const isMinimized = useUploadQueueStore((state) => state.isMinimized);

    const pause = useUploadQueueStore((state) => state.pause);
    const resume = useUploadQueueStore((state) => state.resume);
    const setDrawerOpen = useUploadQueueStore((state) => state.setDrawerOpen);
    const toggleMinimize = useUploadQueueStore((state) => state.toggleMinimize);
    const clearCompleted = useUploadQueueStore((state) => state.clearCompleted);

    const [activeTab, setActiveTab] = useState<QueueTab>('all');

    const uploadingCount = useMemo(() => files.filter((f) => f.status === 'uploading').length, [files]);
    const pendingCount = useMemo(() => files.filter((f) => f.status === 'pending').length, [files]);
    const doneCount = useMemo(() => files.filter((f) => f.status === 'done').length, [files]);
    const failedCount = useMemo(() => files.filter((f) => f.status === 'failed').length, [files]);

    const inFlightCount = uploadingCount + pendingCount;
    const title = getQueueTitle(inFlightCount, failedCount, doneCount, isPaused);

    const totalDeclaredBytes = useMemo(
        () => files.reduce((acc, f) => acc + f.file.size, 0),
        [files],
    );
    const totalUploadedBytes = useMemo(
        () =>
            files.reduce((acc, f) => {
                if (f.status === 'done') return acc + f.file.size;
                return acc + Math.round((f.file.size * f.progress) / 100);
            }, 0),
        [files],
    );
    const overallPercentage =
        totalDeclaredBytes > 0
            ? Math.min(100, Math.round((totalUploadedBytes / totalDeclaredBytes) * 100))
            : 0;

    const displayedFiles = useMemo(() => {
        if (activeTab === 'active') {
            return files.filter((f) => f.status === 'uploading' || f.status === 'pending');
        }
        if (activeTab === 'done') {
            return files.filter((f) => f.status === 'done');
        }
        if (activeTab === 'failed') {
            return files.filter((f) => f.status === 'failed');
        }
        return files;
    }, [files, activeTab]);

    if (!isDrawerOpen || files.length === 0) {
        return null;
    }

    const pauseResumeTitle = isPaused ? 'Resume uploads' : 'Pause uploads';
    const PauseResumeIcon = isPaused ? Play : Pause;
    const minimizeTitle = isMinimized ? 'Expand' : 'Minimize';
    const MinimizeIcon = isMinimized ? ChevronUp : ChevronDown;

    return (
        <section
            aria-label="Uploads Queue"
            className={cn(
                'fixed bottom-4 right-4 z-50 w-full max-w-md rounded-xl border border-border bg-card shadow-xl transition-all duration-200 overflow-hidden',
            )}
        >
            <div className="flex items-center justify-between border-b border-border bg-muted/60 px-4 py-2.5">
                <div className="flex items-center gap-2">
                    <StatusDot
                        inFlightCount={inFlightCount}
                        failedCount={failedCount}
                        isPaused={isPaused}
                    />
                    <h4 className="text-xs font-semibold text-foreground">{title}</h4>
                </div>

                <div className="flex items-center gap-1">
                    {inFlightCount > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                            onClick={isPaused ? resume : pause}
                            title={pauseResumeTitle}
                            aria-label={pauseResumeTitle}
                        >
                            <PauseResumeIcon className="h-3.5 w-3.5" />
                        </Button>
                    )}

                    {doneCount > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                            onClick={clearCompleted}
                            title="Clear completed uploads"
                            aria-label="Clear completed uploads"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    )}

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7 text-muted-foreground hover:text-foreground"
                        onClick={toggleMinimize}
                        title={minimizeTitle}
                        aria-label={minimizeTitle}
                    >
                        <MinimizeIcon className="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7 text-muted-foreground hover:text-foreground"
                        onClick={() => setDrawerOpen(false)}
                        title="Close upload drawer"
                        aria-label="Close upload drawer"
                    >
                        <X className="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            {/* Overall Batch Progress */}
            {inFlightCount > 0 && !isMinimized && (
                <div className="border-b border-border/80 bg-muted/30 px-4 py-2 space-y-1.5">
                    <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                        <span>
                            {doneCount} of {files.length} uploaded ({overallPercentage}%)
                        </span>
                        <span>
                            {formatFileSize(totalUploadedBytes)} / {formatFileSize(totalDeclaredBytes)}
                        </span>
                    </div>
                    <Progress
                        value={overallPercentage}
                        className={cn('h-1.5', isPaused && '[&>div]:bg-amber-500')}
                    />
                </div>
            )}

            {!isMinimized && files.length > 5 && (
                <UploadQueueTabs
                    activeTab={activeTab}
                    onTabChange={setActiveTab}
                    totalCount={files.length}
                    inFlightCount={inFlightCount}
                    doneCount={doneCount}
                    failedCount={failedCount}
                />
            )}

            {!isMinimized && <VirtualizedUploadList files={displayedFiles} />}
        </section>
    );
}
