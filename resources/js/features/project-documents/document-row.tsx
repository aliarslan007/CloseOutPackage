import { formatFileSize } from '@/features/media-library';
import { cn } from '@/lib/utils';
import { Download, Eye, Trash2 } from 'lucide-react';
import type { ProjectDocument } from './types';
import { fileTypeMeta } from './utils';

export interface DocumentRowProps {
    doc: ProjectDocument;
    canManage: boolean;
    onRequestDelete: (doc: ProjectDocument) => void;
}

export function DocumentRow({ doc, canManage, onRequestDelete }: Readonly<DocumentRowProps>) {
    const { Icon, colorClass } = fileTypeMeta(doc.name);
    const meta = [formatFileSize(doc.size_bytes), doc.uploaded_at ?? null, doc.uploaded_by ?? null]
        .filter(Boolean)
        .join(' · ');

    return (
        <li className="group flex w-72 max-w-full shrink-0 items-start gap-2.5 rounded-lg border border-border/60 px-3 py-2 transition-colors hover:bg-muted/50">
            <Icon className={cn('size-4 shrink-0', colorClass)} aria-hidden />

            <div className="min-w-0 flex-1">
                <a
                    href={doc.view_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={doc.name}
                    className="block whitespace-normal break-words text-xs font-medium text-foreground hover:underline"
                >
                    {doc.name}
                </a>
                <p className="break-words text-[11px] text-muted-foreground">{meta}</p>
            </div>

            <div className="flex shrink-0 items-center gap-0.5">
                <a
                    href={doc.view_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`Open ${doc.name}`}
                    title="Open in new tab"
                    className="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <Eye className="size-3.5" />
                </a>

                <a
                    href={doc.download_url}
                    aria-label={`Download ${doc.name}`}
                    title="Download"
                    className="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <Download className="size-3.5" />
                </a>

                {canManage && (
                    <button
                        type="button"
                        onClick={() => onRequestDelete(doc)}
                        aria-label={`Remove ${doc.name}`}
                        title="Remove"
                        className="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <Trash2 className="size-3.5" />
                    </button>
                )}
            </div>
        </li>
    );
}
