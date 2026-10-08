import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronRight, GripVertical, MoreVertical, Pencil, Trash2 } from 'lucide-react';
import * as React from 'react';
import type { SowAnnotatedImage } from '../../types/sow.types';
import { SowImageCard } from './sow-image-card';

export interface SowImageGroupSectionProps {
    /** null = the trailing ungrouped bucket. */
    groupId: number | null;
    title: string;
    images: SowAnnotatedImage[];
    canUpdate: boolean;
    selectable: boolean;
    selectedIds: ReadonlySet<number>;
    busy: boolean;
    /** Image currently mid-request, so only its card shows a spinner. */
    busyId?: number | null;
    statusFor: (image: SowAnnotatedImage) => SowAnnotatedImage['composite_status'];
    canDeleteImage: (image: SowAnnotatedImage) => boolean;
    onToggleSelection: (imageId: number) => void;
    onOpen: (imageId: number) => void;
    onSetCover: (imageId: number) => void;
    onToggleInclude: (imageId: number, includeInPdf: boolean) => void;
    onRemove: (imageId: number) => void;
    onRename: (groupId: number, name: string) => void;
    onDelete: (groupId: number) => void;
    onReorderGroups?: (fromId: number, toId: number) => void;
    onReorderImages?: (key: number | null, fromId: number, toId: number) => void;
    onMoveImagesTo?: (imageIds: number[], targetGroupId: number | null) => void;
}

/**
 * One named group: drag handle + inline-rename title + actions, then its photo
 * cards. Reorder is drag-based, and images can be dropped directly onto groups.
 */
export function SowImageGroupSection({
    groupId,
    title,
    images,
    canUpdate,
    selectable,
    selectedIds,
    busy,
    busyId = null,
    statusFor,
    canDeleteImage,
    onToggleSelection,
    onOpen,
    onSetCover,
    onToggleInclude,
    onRemove,
    onRename,
    onDelete,
    onReorderGroups,
    onReorderImages,
    onMoveImagesTo,
}: Readonly<SowImageGroupSectionProps>) {
    const isUngrouped = groupId === null;
    const [collapsed, setCollapsed] = React.useState(false);
    const [editing, setEditing] = React.useState(false);
    const [draftName, setDraftName] = React.useState(title);
    const [menuOpen, setMenuOpen] = React.useState(false);
    const [draggingId, setDraggingId] = React.useState<number | null>(null);
    const [overGroup, setOverGroup] = React.useState(false);
    const [overId, setOverId] = React.useState<number | null>(null);

    React.useEffect(() => setDraftName(title), [title]);

    const commitRename = () => {
        setEditing(false);
        const next = draftName.trim();
        if (groupId !== null && next !== '' && next !== title) onRename(groupId, next);
        else setDraftName(title);
    };

    return (
        <section
            aria-label={title}
            onDragOver={(e) => {
                if (!canUpdate) return;
                e.preventDefault();
                setOverGroup(true);
            }}
            onDragLeave={() => setOverGroup(false)}
            onDrop={(e) => {
                setOverGroup(false);
                if (!canUpdate) return;
                // Check if an image was dropped onto this group section
                const rawImageId = e.dataTransfer.getData('application/x-sow-image-id') || e.dataTransfer.getData('text/plain');
                const droppedImageId = rawImageId ? Number.parseInt(rawImageId, 10) : Number.NaN;
                if (!Number.isNaN(droppedImageId)) {
                    e.preventDefault();
                    e.stopPropagation();
                    onMoveImagesTo?.([droppedImageId], groupId);
                    return;
                }
                // Otherwise check if a group reorder occurred
                if (draggingId !== null && groupId !== null) {
                    e.preventDefault();
                    onReorderGroups?.(draggingId, groupId);
                }
                setDraggingId(null);
            }}
            className={cn(
                'rounded-md border border-border bg-card/60 transition-colors',
                draggingId !== null && 'opacity-60',
                overGroup && 'border-primary ring-2 ring-primary/40 bg-accent/20'
            )}
        >
            <header className="flex items-center gap-2 border-b border-border/50 px-3 py-2">
                <button
                    type="button"
                    onClick={() => setCollapsed((prev) => !prev)}
                    aria-label={collapsed ? `Expand ${title}` : `Collapse ${title}`}
                    className="rounded p-0.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    {collapsed ? <ChevronRight className="size-4" /> : <ChevronDown className="size-4" />}
                </button>

                {canUpdate && !isUngrouped && (
                    <button
                        type="button"
                        draggable
                        onDragStart={(e) => {
                            // Tag the drag as a group reorder so the cards and the
                            // section's own drop handler can tell the two apart.
                            e.stopPropagation();
                            e.dataTransfer.effectAllowed = 'move';
                            setDraggingId(groupId);
                        }}
                        onDragEnd={() => {
                            setDraggingId(null);
                            setOverGroup(false);
                        }}
                        title="Drag to reorder this group in the PDF"
                        aria-label="Drag to reorder this group in the PDF"
                        className="cursor-grab rounded p-0.5 text-muted-foreground/60 hover:text-foreground active:cursor-grabbing"
                    >
                        <GripVertical className="size-4" />
                    </button>
                )}

                {editing ? (
                    <input
                        autoFocus
                        value={draftName}
                        maxLength={120}
                        onChange={(e) => setDraftName(e.target.value)}
                        onBlur={commitRename}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') commitRename();
                            if (e.key === 'Escape') {
                                setDraftName(title);
                                setEditing(false);
                            }
                        }}
                        className="h-7 min-w-0 flex-1 rounded border border-input bg-background px-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                ) : (
                    <h3 className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">
                        {title}
                    </h3>
                )}

                <span className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                    {images.length}
                </span>

                {canUpdate && !isUngrouped && !editing && (
                    <div className="relative shrink-0">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Actions for ${title}`}
                            disabled={busy}
                            onClick={() => setMenuOpen((prev) => !prev)}
                            className="size-7 rounded-md text-muted-foreground hover:text-foreground"
                        >
                            <MoreVertical className="size-4" />
                        </Button>
                        {menuOpen && (
                            <>
                                <button
                                    type="button"
                                    aria-label="Close menu"
                                    className="fixed inset-0 z-10 cursor-default"
                                    onClick={() => setMenuOpen(false)}
                                />
                                <div className="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-border bg-card p-1 shadow-lg">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setMenuOpen(false);
                                            setEditing(true);
                                        }}
                                        className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-xs text-foreground hover:bg-muted"
                                    >
                                        <Pencil className="size-3.5" /> Rename
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setMenuOpen(false);
                                            if (groupId !== null) onDelete(groupId);
                                        }}
                                        className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-xs text-destructive hover:bg-destructive/10"
                                    >
                                        <Trash2 className="size-3.5" /> Delete group
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                )}
            </header>

            {!collapsed && (
                <div className="flex flex-wrap gap-3 p-3">
                    {images.length === 0 ? (
                        <p className="w-full py-4 text-center text-xs text-muted-foreground">
                            {isUngrouped
                                ? 'Every photo belongs to a group.'
                                : 'No photos in this group yet — drag and drop photos here from the Other Photos section.'}
                        </p>
                    ) : (
                        images.map((image) => (
                            <SowImageCard
                                key={image.id}
                                image={image}
                                compositeStatus={statusFor(image)}
                                canUpdate={canUpdate}
                                canDelete={canDeleteImage(image)}
                                isBusy={busyId === image.id}
                                selectable={selectable}
                                selected={selectedIds.has(image.id)}
                                draggable={canUpdate}
                                isDragging={draggingId === image.id}
                                isDropTarget={overId === image.id && draggingId !== image.id}
                                onSelectToggle={selectable ? onToggleSelection : undefined}
                                onOpen={onOpen}
                                onSetCover={onSetCover}
                                onToggleInclude={onToggleInclude}
                                onRemove={onRemove}
                                onDragStart={() => setDraggingId(image.id)}
                                onDragOver={() => setOverId(image.id)}
                                onDrop={() => {
                                    if (draggingId !== null && overId !== null) {
                                        onReorderImages?.(groupId, draggingId, overId);
                                    }
                                    setDraggingId(null);
                                    setOverId(null);
                                }}
                                onDragEnd={() => {
                                    setDraggingId(null);
                                    setOverId(null);
                                }}
                            />
                        ))
                    )}
                </div>
            )}
        </section>
    );
}
