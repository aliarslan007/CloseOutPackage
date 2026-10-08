import { cn } from '@/lib/utils';
import { FolderTree, Image as ImageIcon, UploadCloud } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useAccessibleProjects } from '../hooks/use-accessible-projects';
import { useMediaConfig } from '../hooks/use-media-config';
import { useUploadQueue } from '../hooks/use-upload-queue';
import type { MediaLibraryProject } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';

export interface UploadDropzoneProps {
    projectId?: number | null;
    projects?: MediaLibraryProject[];
    className?: string;
    onUploaded?: () => void;
}

export function UploadDropzone({
    projectId = null,
    projects: initialProjects,
    className,
    onUploaded,
}: Readonly<UploadDropzoneProps>) {
    const { projects } = useAccessibleProjects(initialProjects);
    const { config } = useMediaConfig();
    const { enqueueFiles } = useUploadQueue();
    const [isDragging, setIsDragging] = useState(false);
    const [selectedProjectId, setSelectedProjectId] = useState<number | null>(projectId ?? null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const effectiveProjectId = projectId ?? selectedProjectId;

    const handleDragOver = useCallback((e: React.DragEvent<HTMLElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent<HTMLElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    }, []);

    const handleDrop = useCallback(
        async (e: React.DragEvent<HTMLElement>) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragging(false);

            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                await enqueueFiles(e.dataTransfer.files, effectiveProjectId);
                onUploaded?.();
            }
        },
        [enqueueFiles, effectiveProjectId, onUploaded]
    );

    const handleFileInputChange = useCallback(
        async (e: React.ChangeEvent<HTMLInputElement>) => {
            if (e.target.files && e.target.files.length > 0) {
                await enqueueFiles(e.target.files, effectiveProjectId);
                e.target.value = '';
                onUploaded?.();
            }
        },
        [enqueueFiles, effectiveProjectId, onUploaded]
    );

    const handleClick = useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    const maxSizeStr = formatFileSize(config.max_file_bytes);
    const acceptTypes = config.allowed_mimes.join(',');
    const selectedProjectName = projects.find((p) => p.id === selectedProjectId)?.name;

    return (
        <section
            aria-label="File upload dropzone"
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            className={cn(
                'group relative flex flex-col rounded-xl border-2 border-dashed p-4 sm:p-5 transition-all shadow-2xs',
                isDragging
                    ? 'border-primary bg-primary/10 shadow-inner scale-[1.005]'
                    : 'border-border/90 bg-card/60 hover:border-primary/60 hover:bg-muted/20',
                className
            )}
        >
            <input
                ref={fileInputRef}
                id="media-library-file-input"
                type="file"
                multiple
                accept={acceptTypes}
                onChange={handleFileInputChange}
                className="sr-only"
                aria-label="Choose image files to upload"
            />

            {/* Seamless Integrated Project Assignment & Format Bar */}
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 pb-3.5 mb-3 border-b border-border/60">
                {projectId ? (
                    <div className="flex items-center gap-1.5 text-xs font-medium text-foreground">
                        <FolderTree className="h-4 w-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                        <span>Uploading to Project Context</span>
                    </div>
                ) : (
                    <div className="flex items-center gap-2 flex-wrap">
                        <label
                            htmlFor="upload-target-project"
                            className="text-xs font-semibold text-foreground flex items-center gap-1.5 shrink-0"
                        >
                            <FolderTree className="h-4 w-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                            Target Project:
                        </label>
                        <select
                            id="upload-target-project"
                            value={selectedProjectId ?? ''}
                            onChange={(e) => {
                                const val = e.target.value;
                                setSelectedProjectId(val ? Number(val) : null);
                            }}
                            className="h-8 max-w-[260px] rounded-lg border border-border bg-background px-2.5 text-xs font-semibold text-foreground shadow-2xs focus:outline-none focus:ring-1 focus:ring-primary truncate cursor-pointer"
                            aria-label="Target Project Assignment"
                            title={selectedProjectName ?? 'Unassigned (Personal Library)'}
                        >
                            <option value="">Unassigned (Personal Library)</option>
                            {projects.map((p) => (
                                <option key={p.id} value={p.id} title={p.name}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        {selectedProjectName && (
                            <span className="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium hidden md:inline">
                                Links directly to {selectedProjectName}
                            </span>
                        )}
                    </div>
                )}

                <div className="text-[11px] text-muted-foreground flex items-center gap-1.5 shrink-0">
                    <ImageIcon className="h-3.5 w-3.5 text-muted-foreground/70" />
                    <span>JPG, PNG, WebP, HEIC · max {maxSizeStr}</span>
                </div>
            </div>

            {/* Central Drag & Drop Action Area */}
            <div className="flex flex-col items-center justify-center py-4 space-y-2.5 text-center">
                <div className="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shadow-xs transition-transform group-hover:scale-110">
                    <UploadCloud className="h-5 w-5" aria-hidden="true" />
                </div>

                <div className="space-y-1 text-center">
                    <button
                        type="button"
                        onClick={handleClick}
                        className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition-colors cursor-pointer"
                    >
                        <UploadCloud className="h-4 w-4" aria-hidden="true" />
                        <span>Click to Browse &amp; Upload Photos</span>
                    </button>
                    <p className="text-xs text-muted-foreground pt-1">
                        or drag and drop photo files anywhere in this area
                    </p>
                </div>
            </div>
        </section>
    );
}
