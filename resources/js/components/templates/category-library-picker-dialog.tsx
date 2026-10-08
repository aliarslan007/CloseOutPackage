import { SubcategoryItem } from '@/components/templates/subcategory-form-dialog';
import { Badge } from '@/components/ui/badge';
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
import { router } from '@inertiajs/react';
import { BookMarked, Camera, CheckSquare, Square } from 'lucide-react';
import * as React from 'react';

export interface CategoryLibraryPickerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    templateId: number;
    sectionId: number;
    /** Active library presets (all of them — grouped below). */
    presets: SubcategoryItem[];
    /** Preset ids already placed somewhere in THIS template. */
    usedPresetIds?: number[];
    /** Preset id → stage labels where it is currently used ("Stage 1", …). */
    stagesByPresetId?: Record<number, string[]>;
    /** Preset ids already in the CURRENT stage — cannot be added again. */
    sameStagePresetIds?: number[];
}

interface PresetRowProps {
    preset: SubcategoryItem;
    checked: boolean;
    disabled?: boolean;
    disabledHint?: string;
    stageLabels?: string[];
    onToggle: () => void;
}

function getPresetRowClass(disabled?: boolean, checked?: boolean): string {
    if (disabled) {
        return 'cursor-not-allowed opacity-55 border-transparent';
    }
    if (checked) {
        return 'border-primary/50 bg-primary/5';
    }
    return 'border-transparent hover:bg-muted/40';
}

function PresetRow({
    preset,
    checked,
    disabled,
    disabledHint,
    stageLabels,
    onToggle,
}: Readonly<PresetRowProps>) {
    return (
        <button
            type="button"
            onClick={onToggle}
            disabled={disabled}
            aria-pressed={checked}
            title={disabledHint}
            className={`flex w-full items-center gap-3 rounded-lg border px-3 py-2 text-left transition-colors ${getPresetRowClass(disabled, checked)}`}
        >
            {checked ? (
                <CheckSquare className="size-4 shrink-0 text-primary" />
            ) : (
                <Square className="size-4 shrink-0 text-muted-foreground" />
            )}
            <Camera className="size-3.5 shrink-0 text-primary" />
            <span className="min-w-0 flex-1">
                <span className="block truncate text-xs font-medium">{preset.name}</span>
                {(stageLabels?.length ?? 0) > 0 && (
                    <span className="mt-0.5 flex items-center gap-1 flex-wrap">
                        {stageLabels?.map((label) => (
                            <Badge key={label} variant="outline" className="text-[9px] px-1.5 py-0">
                                Used in {label}
                            </Badge>
                        ))}
                    </span>
                )}
                {disabled && disabledHint && (
                    <span className="mt-0.5 block text-[10px] text-muted-foreground">
                        {disabledHint}
                    </span>
                )}
            </span>
            {preset.is_required && (
                <Badge variant="warning" className="text-[10px] shrink-0">
                    Must
                </Badge>
            )}
            <Badge variant="secondary" className="text-[10px] shrink-0">
                {preset.default_picture_count}
                {preset.default_picture_count === 1 ? ' photo' : ' photos'}
            </Badge>
        </button>
    );
}

export function CategoryLibraryPickerDialog({
    open,
    onOpenChange,
    templateId,
    sectionId,
    presets,
    usedPresetIds = [],
    stagesByPresetId = {},
    sameStagePresetIds = [],
}: Readonly<CategoryLibraryPickerDialogProps>) {
    const [search, setSearch] = React.useState('');
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());

    // Reset transient selection each time the dialog opens for a fresh start.
    React.useEffect(() => {
        if (open) setSelectedIds(new Set());
    }, [open]);

    const term = search.trim().toLowerCase();
    const matches = (p: SubcategoryItem): boolean =>
        !term ||
        p.name.toLowerCase().includes(term) ||
        (p.description ?? '').toLowerCase().includes(term);

    const usedSet = new Set(usedPresetIds);
    const sameStageSet = new Set(sameStagePresetIds);

    const usedHere = presets.filter((p) => matches(p) && sameStageSet.has(p.id));
    const usedElsewhere = presets.filter(
        (p) => matches(p) && usedSet.has(p.id) && !sameStageSet.has(p.id)
    );
    const notUsed = presets.filter((p) => matches(p) && !usedSet.has(p.id));

    const toggle = (id: number) => {
        if (sameStageSet.has(id)) return;
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleAdd = () => {
        if (selectedIds.size === 0) return;
        router.post(
            route('admin.templates.categories.from-library', [templateId, sectionId]),
            { preset_ids: Array.from(selectedIds) },
            { preserveScroll: true, onSuccess: () => onOpenChange(false) }
        );
    };

    const renderGroup = (
        title: string,
        items: SubcategoryItem[],
        hint?: string,
        opts?: { disabledAll?: boolean; showStages?: boolean }
    ) => {
        if (items.length === 0) return null;
        return (
            <div className="space-y-1.5">
                <p className="flex items-center gap-2 px-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    {title}
                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-bold">
                        {items.length}
                    </span>
                    {hint && (
                        <span className="font-normal normal-case tracking-normal text-[10px]">
                            {hint}
                        </span>
                    )}
                </p>
                {items.map((preset) => {
                    const lockedSameStage = Boolean(
                        opts?.disabledAll && sameStageSet.has(preset.id)
                    );
                    return (
                        <PresetRow
                            key={preset.id}
                            preset={preset}
                            checked={selectedIds.has(preset.id)}
                            disabled={lockedSameStage}
                            disabledHint="Already added to this stage"
                            stageLabels={opts?.showStages ? stagesByPresetId[preset.id] : undefined}
                            onToggle={() => toggle(preset.id)}
                        />
                    );
                })}
            </div>
        );
    };

    const nothingVisible =
        usedHere.length === 0 && usedElsewhere.length === 0 && notUsed.length === 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Pull subcategories from your library</DialogTitle>
                    <DialogDescription>
                        Saved subcategories you can drop into this stage — no retyping. A
                        subcategory can appear in more than one stage.
                    </DialogDescription>
                </DialogHeader>

                {presets.length === 0 ? (
                    <div className="space-y-3 py-4 text-center">
                        <p className="text-sm text-muted-foreground">
                            Your library is empty — add subcategories first, then pull them here.
                        </p>
                        <Button asChild variant="outline" size="sm" className="gap-1.5 text-xs">
                            <a href={route('admin.templates.category-presets.index')}>
                                <BookMarked className="size-3.5" /> Open Subcategories Library
                            </a>
                        </Button>
                    </div>
                ) : (
                    <>
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search your library..."
                            className="h-9 text-xs"
                        />
                        <div className="max-h-80 space-y-3 overflow-y-auto rounded-lg border border-border/60 p-2">
                            {nothingVisible ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
                                    No subcategories match “{search}”.
                                </p>
                            ) : (
                                <>
                                    {renderGroup(
                                        'Not used yet',
                                        notUsed,
                                        'fresh for this template'
                                    )}
                                    {renderGroup(
                                        'Already used in this template',
                                        [...usedElsewhere, ...usedHere],
                                        'safe to reuse in another stage',
                                        { showStages: true, disabledAll: true }
                                    )}
                                </>
                            )}
                        </div>
                        <DialogFooter className="gap-2 pt-1">
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="mr-auto gap-1.5 text-xs text-muted-foreground"
                            >
                                <a href={route('admin.templates.category-presets.index')}>
                                    <BookMarked className="size-3.5" /> Manage library
                                </a>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                onClick={handleAdd}
                                disabled={selectedIds.size === 0}
                            >
                                Add {selectedIds.size > 0 ? `${selectedIds.size} ` : ''}
                                {selectedIds.size === 1 ? 'subcategory' : 'subcategories'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
