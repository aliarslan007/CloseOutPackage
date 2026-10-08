/**
 * @deprecated Part of the legacy REC card. Superseded by `DocumentRow` in
 * `@/features/project-documents`; delete together with `rec-document-card.tsx`.
 */
import type { ProjectRecDocument } from '@/components/projects/project-show-types';
import { formatFileSize } from '@/features/media-library';
import { cn } from '@/lib/utils';
import { Download, ExternalLink, FileText, Trash2 } from 'lucide-react';
import { extensionOf, isPdf, recFileLabel } from './rec-document-utils';

export interface RecDocumentTileProps {
    doc: ProjectRecDocument;
    canManage: boolean;
    /** Stagger index for the entrance animation. */
    index: number;
    onRequestDelete: (doc: ProjectRecDocument) => void;
}

/** Stylized paper stack shown behind the native PDF viewer while it paints. */
function PaperPreview() {
    return (
        <div className="flex h-full w-full items-center justify-center bg-gradient-to-b from-muted/20 via-transparent to-muted/60 p-5">
            <div className="relative -rotate-3 transition-transform duration-300 ease-out group-hover:-translate-y-1.5 group-hover:rotate-0">
                {/* trailing sheets = depth */}
                <div className="absolute inset-0 translate-x-2 translate-y-1.5 rotate-6 rounded-sm bg-white/60 shadow-sm" />
                <div className="absolute inset-0 translate-x-1 translate-y-1 rotate-3 rounded-sm bg-white/80 shadow-sm" />

                {/* the page */}
                <div className="relative flex h-36 w-28 flex-col rounded-sm bg-white p-3 shadow-lg ring-1 ring-zinc-900/10">
                    <div className="absolute right-0 top-0 size-5 rounded-bl-sm bg-zinc-100 [clip-path:polygon(0_0,100%_100%,0_100%)] shadow-sm" />
                    <div className="mb-1.5 h-2 w-3/5 rounded-full bg-rose-500" />
                    <div className="space-y-1.5">
                        <div className="h-1 w-full rounded-full bg-zinc-200" />
                        <div className="h-1 w-11/12 rounded-full bg-zinc-200" />
                        <div className="h-1 w-full rounded-full bg-zinc-200" />
                        <div className="h-1 w-4/5 rounded-full bg-zinc-200" />
                        <div className="h-1 w-full rounded-full bg-zinc-200" />
                        <div className="h-1 w-2/3 rounded-full bg-zinc-200" />
                    </div>
                    <div className="mt-auto flex items-center justify-between">
                        <span className="text-[8px] font-black uppercase tracking-widest text-zinc-300">Rec</span>
                        <FileText className="size-3.5 text-rose-500" />
                    </div>
                </div>
            </div>
        </div>
    );
}

export function RecDocumentTile({ doc, canManage, index, onRequestDelete }: Readonly<RecDocumentTileProps>) {
    const pdf = isPdf(doc);
    const meta = [
        formatFileSize(doc.size_bytes),
        doc.uploaded_at ?? null,
        doc.uploaded_by ?? null,
    ].filter(Boolean);

    return (
        <article
            style={{ animationDelay: `${Math.min(index, 6) * 70}ms` }}
            className={cn(
                'group relative flex flex-col overflow-hidden rounded-md border border-border/60 bg-card',
                'shadow-2xs transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-xl hover:shadow-black/10',
                'motion-safe:animate-fade-up focus-within:ring-2 focus-within:ring-ring'
            )}
        >
            <a
                href={doc.view_url}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={`Open ${recFileLabel(doc)}`}
                className="relative block aspect-[4/3] overflow-hidden"
            >
                {pdf ? (
                    <div className="relative h-full w-full overflow-hidden bg-muted/20">
                        <div className="absolute inset-0">
                            <PaperPreview />
                        </div>
                        {/* W-112% and h-[240%] with overflow-hidden clips out the native PDF viewer scrollbar on the right while filling horizontal width cleanly */}
                        <iframe
                            src={`${doc.view_url}#toolbar=0&navpanes=0&scrollbar=0&view=FitH`}
                            title={`${recFileLabel(doc)} preview`}
                            loading="lazy"
                            tabIndex={-1}
                            aria-hidden
                            className="pointer-events-none absolute left-0 top-0 h-[240%] w-[114%] border-0 bg-white"
                        />
                    </div>
                ) : (
                    <img
                        src={doc.preview_url}
                        alt={`Preview of ${recFileLabel(doc)}`}
                        loading="lazy"
                        className="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.05]"
                    />
                )}

                <span className="absolute right-2 top-2 rounded-md bg-zinc-900/75 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white backdrop-blur-sm">
                    {extensionOf(doc.name) || (pdf ? 'pdf' : 'img')}
                </span>

                {/* hover affordance — desktop only, the footer buttons cover touch */}
                <span className="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center pb-3 opacity-0 transition-opacity duration-200 group-hover:opacity-100 max-lg:hidden">
                    <span className="flex items-center gap-1.5 rounded-full bg-zinc-900/80 px-3 py-1.5 text-[11px] font-semibold text-white shadow-lg backdrop-blur-sm">
                        <ExternalLink className="size-3" /> Open document
                    </span>
                </span>
            </a>

            <div className="flex items-center gap-2 border-t border-border/60 px-3 py-2.5">
                <div className="min-w-0 flex-1">
                    <a
                        href={doc.view_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block truncate text-sm font-semibold text-foreground hover:underline"
                        title={recFileLabel(doc)}
                    >
                        {recFileLabel(doc)}
                    </a>
                    <p className="truncate text-[11px] text-muted-foreground">{meta.join(' · ')}</p>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <a
                        href={doc.download_url}
                        aria-label={`Download ${recFileLabel(doc)}`}
                        title="Download"
                        className="flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <Download className="size-4" />
                    </a>
                    {canManage && (
                        <button
                            type="button"
                            onClick={() => onRequestDelete(doc)}
                            aria-label={`Remove ${recFileLabel(doc)}`}
                            title="Remove"
                            className="flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    )}
                </div>
            </div>
        </article>
    );
}
