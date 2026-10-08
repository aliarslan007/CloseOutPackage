import { FormField } from '@/components/shared/form-field';
import { CategoryItem } from '@/components/templates/category-item';
import { CategoryLibraryPickerDialog } from '@/components/templates/category-library-picker-dialog';
import {
    SubcategoryFormDialog,
    SubcategoryItem,
} from '@/components/templates/subcategory-form-dialog';
import {
    STAGE_LABELS,
    TemplateSectionItem,
    nextSortOrder,
} from '@/components/templates/template-types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { BookMarked, CheckCircle2, Loader2, Plus } from 'lucide-react';
import * as React from 'react';

interface SectionForm {
    heading: string;
    admin_notes: string;
}

export interface SectionCardProps {
    templateId: number;
    section: TemplateSectionItem;
    /** Active library presets (all — the picker groups used vs not-used). */
    libraryPresets?: SubcategoryItem[];
    /** Preset ids already used anywhere in this template. */
    usedPresetIds?: number[];
    /** Preset id → stage labels where it is used ("Stage 1", …). */
    stagesByPresetId?: Record<number, string[]>;
    /** Renders the card hidden (kept mounted so unsaved edits survive tab switches). */
    hidden?: boolean;
    /** Reports whether this section currently has unsaved heading/notes edits. */
    onDirtyChange?: (sectionId: number, dirty: boolean) => void;
}

export function SectionCard({
    templateId,
    section,
    libraryPresets = [],
    usedPresetIds = [],
    stagesByPresetId = {},
    hidden = false,
    onDirtyChange,
}: Readonly<SectionCardProps>) {
    const [addOpen, setAddOpen] = React.useState(false);
    const [pickerOpen, setPickerOpen] = React.useState(false);

    const form = useForm<SectionForm>({
        heading: section.heading,
        admin_notes: section.admin_notes ?? '',
    });

    // Always keep the latest server props in a ref so the save onSuccess handler
    // can re-sync the inputs with the server-confirmed values (no stale closure).
    const sectionRef = React.useRef(section);
    sectionRef.current = section;

    // Derived from the server-provided props so the flag clears automatically
    // once a save round-trips (the props then match the form values).
    const isDirty =
        form.data.heading !== section.heading ||
        (form.data.admin_notes ?? '') !== (section.admin_notes ?? '');

    React.useEffect(() => {
        onDirtyChange?.(section.id, isDirty);
    }, [section.id, isDirty, onDirtyChange]);

    const formId = `section-form-${section.id}`;

    const handleReorder = (fromIndex: number, toIndex: number) => {
        const reordered = [...section.categories];
        const [moved] = reordered.splice(fromIndex, 1);
        reordered.splice(toIndex, 0, moved);

        router.post(
            route('admin.templates.categories.reorder', [templateId, section.id]),
            {
                items: reordered.map((category, index) => ({
                    id: category.id,
                    sort_order: index,
                })),
            },
            { preserveScroll: true }
        );
    };

    // Autosave: debounced PUT 600ms after the last keystroke. The dirty flag
    // (form vs server props) doubles as the "saving…" indicator and feeds the
    // page-level navigation guard, so nothing can be lost by tabbing away.
    // 1000ms idle debounce: saves only after the user stops typing — saving on
    // every keystroke pause is request-chatty and annoying.
    React.useEffect(() => {
        if (!isDirty || !form.data.heading.trim()) return undefined;

        const timer = globalThis.setTimeout(() => {
            form.put(route('admin.templates.sections.update', [templateId, section.id]), {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // Re-sync from the server-confirmed values so the dirty flag
                    // clears even if the server normalizes/trims the input.
                    const saved = sectionRef.current;
                    form.setData({
                        heading: saved.heading,
                        admin_notes: saved.admin_notes ?? '',
                    });
                },
            });
        }, 1000);

        return () => globalThis.clearTimeout(timer);
    }, [form, isDirty, form.data.heading, form.data.admin_notes, section.id, templateId]);

    const isSaving = isDirty && Boolean(form.data.heading.trim());
    const needsTitle = isDirty && !form.data.heading.trim();

    return (
        <div
            className={cn(
                'rounded-xl border border-border/80 bg-card overflow-hidden',
                hidden && 'hidden'
            )}
        >
            <div className="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-4 py-3">
                <div className="flex items-center gap-2 min-w-0">
                    <Badge variant="info" className="text-[10px] whitespace-nowrap">
                        Stage {section.sort_order} of 3
                    </Badge>
                    <h3 className="text-sm font-bold text-foreground truncate">
                        {STAGE_LABELS[section.stage]}
                    </h3>
                    <span
                        className="size-3.5 rounded-full border border-border text-[9px] leading-none flex items-center justify-center text-muted-foreground cursor-help select-none shrink-0"
                        title="A stage is one phase of work (before / during / after construction). Photos and questions you add here appear only while field users work on this phase."
                        aria-label="What is a stage?"
                        role="note"
                    >
                        ?
                    </span>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                    <p className="text-[11px] text-muted-foreground hidden sm:block">
                        {section.categories.length}{' '}
                        {section.categories.length === 1 ? 'photo group' : 'photo groups'}
                    </p>
                </div>
            </div>

            <div className="grid gap-4 p-4 lg:grid-cols-12 lg:gap-5">
                <form
                    id={formId}
                    onSubmit={(e) => e.preventDefault()}
                    noValidate
                    className="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3.5 lg:col-span-5 xl:col-span-4"
                >
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            About this stage
                        </p>
                        {/* Compact autosave status: saves 1s after typing stops. */}
                        {isSaving && (
                            <span
                                className="inline-flex items-center gap-1 text-[10px] font-medium text-amber-600 dark:text-amber-400"
                                title="Saving your changes automatically"
                            >
                                <Loader2 className="size-3 animate-spin" /> Saving…
                            </span>
                        )}
                        {!isDirty && (
                            <span
                                className="inline-flex items-center gap-1 text-[10px] text-muted-foreground"
                                title="Changes are saved automatically as you type"
                            >
                                <CheckCircle2 className="size-3 text-emerald-500" /> Auto-saved
                            </span>
                        )}
                        {needsTitle && (
                            <span className="text-[10px] text-muted-foreground">
                                Type a title to save
                            </span>
                        )}
                    </div>

                    <FormField
                        label="Stage Title"
                        htmlFor={`heading-${section.id}`}
                        required
                        helper="Shown at the top of this stage on the field user's phone."
                        error={form.errors.heading}
                    >
                        <Input
                            id={`heading-${section.id}`}
                            value={form.data.heading}
                            onChange={(e) => form.setData('heading', e.target.value)}
                            maxLength={500}
                            required
                            error={Boolean(form.errors.heading)}
                        />
                    </FormField>

                    <FormField
                        label="Field-Tech Instructions"
                        htmlFor={`admin-notes-${section.id}`}
                        helper="Short guidance shown to field users while they work on this stage."
                        error={form.errors.admin_notes}
                    >
                        <Textarea
                            id={`admin-notes-${section.id}`}
                            value={form.data.admin_notes}
                            onChange={(e) => form.setData('admin_notes', e.target.value)}
                            placeholder="e.g. Capture photos before backfilling..."
                            className="min-h-[70px]"
                            error={Boolean(form.errors.admin_notes)}
                        />
                    </FormField>

                    {Boolean(form.errors.heading) && (
                        <p className="text-[11px] font-medium text-destructive">
                            Fix the stage title above — it saves automatically once valid.
                        </p>
                    )}
                </form>

                <div className="space-y-2 lg:col-span-7 xl:col-span-8">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        Photos captured in this stage
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="default"
                            size="sm"
                            onClick={() => setPickerOpen(true)}
                            className="h-8 text-xs gap-1.5"
                        >
                            <BookMarked className="size-3.5" /> Pull Subcategories from Library
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAddOpen(true)}
                            className="h-8 text-xs gap-1"
                        >
                            <Plus className="size-3.5" /> Create Subcategory Heading
                        </Button>
                    </div>

                    {section.categories.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-4 text-center">
                            <p className="text-xs font-medium text-foreground">
                                No photo groups yet
                            </p>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                Pull saved groups from your library, or create a new one — e.g.
                                “Tower Foundation”, “Antenna Installation”.
                            </p>
                        </div>
                    ) : (
                        section.categories.map((category, index) => (
                            <CategoryItem
                                key={category.id}
                                category={category}
                                templateId={templateId}
                                sectionId={section.id}
                                canMoveUp={index > 0}
                                canMoveDown={index < section.categories.length - 1}
                                onMoveUp={() => handleReorder(index, index - 1)}
                                onMoveDown={() => handleReorder(index, index + 1)}
                            />
                        ))
                    )}
                </div>
            </div>

            {addOpen && (
                <SubcategoryFormDialog
                    key={`new-${section.id}`}
                    mode="stage"
                    open={addOpen}
                    onOpenChange={setAddOpen}
                    templateId={templateId}
                    sectionId={section.id}
                    initialSortOrder={nextSortOrder(section.categories)}
                />
            )}

            {pickerOpen && (
                <CategoryLibraryPickerDialog
                    key={`picker-${section.id}`}
                    open={pickerOpen}
                    onOpenChange={setPickerOpen}
                    templateId={templateId}
                    sectionId={section.id}
                    presets={libraryPresets}
                    usedPresetIds={usedPresetIds}
                    stagesByPresetId={stagesByPresetId}
                    sameStagePresetIds={section.categories
                        .map((c) => c.category_preset_id)
                        .filter((id): id is number => typeof id === 'number')}
                />
            )}
        </div>
    );
}
