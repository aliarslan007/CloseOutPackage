import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { BuilderTabKey, BuilderTabs } from '@/components/templates/builder-tabs';
import { ChecklistPanel } from '@/components/templates/checklist-panel';
import { SectionCard } from '@/components/templates/section-card';
import { SubcategoryItem } from '@/components/templates/subcategory-form-dialog';
import { TemplatePreviewPanel } from '@/components/templates/template-preview-panel';
import { STAGE_LABELS, TemplateDetail } from '@/components/templates/template-types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { useNavigationGuard } from '@/hooks/use-navigation-guard';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { BookMarked, CheckCircle2, Eye, Trash2, XCircle } from 'lucide-react';
import * as React from 'react';

export type AdminTemplateShowPageProps = PageProps<{
    template: TemplateDetail;
    libraryPresets: SubcategoryItem[];
}>;

const TAB_KEYS: BuilderTabKey[] = [
    'pre_construction',
    'construction',
    'post_completion',
    'checklist',
];

const TEMPLATES_INDEX_ROUTE = 'admin.templates.index';

export function AdminTemplateShow() {
    useFlashToasts();

    const { template, libraryPresets } = usePage<AdminTemplateShowPageProps>().props;
    const [activeTab, setActiveTab] = React.useState<BuilderTabKey>('pre_construction');
    const [previewOpen, setPreviewOpen] = React.useState(false);
    const [deleteOpen, setDeleteOpen] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);

    // Tracks which sections have unsaved heading/notes edits so the step tabs
    // can show an unsaved dot and leaving the page can be guarded.
    const [dirtySections, setDirtySections] = React.useState<Record<number, boolean>>({});

    const handleDirtyChange = React.useCallback((sectionId: number, dirty: boolean) => {
        setDirtySections((prev) =>
            prev[sectionId] === dirty ? prev : { ...prev, [sectionId]: dirty }
        );
    }, []);

    const hasUnsavedChanges = React.useMemo(
        () => Object.values(dirtySections).some(Boolean),
        [dirtySections]
    );

    useNavigationGuard(
        hasUnsavedChanges,
        'Your stage edits are still saving. Wait for “Saving…” to clear, or your latest changes may be lost.'
    );

    const handleDelete = () => {
        if (!template) return;
        setIsDeleting(true);
        router.delete(route('admin.templates.destroy', template.id), {
            onSuccess: () => {
                setDeleteOpen(false);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    // Where each library subcategory is already used in THIS template — drives
    // the picker's "used / not used yet" grouping and per-item stage badges.
    const { usedPresetIds, stagesByPresetId } = React.useMemo(() => {
        const ids = new Set<number>();
        const stages: Record<number, string[]> = {};
        if (!template) {
            return { usedPresetIds: [], stagesByPresetId: {} };
        }
        for (const section of template.sections) {
            const label = STAGE_LABELS[section.stage];
            for (const category of section.categories) {
                if (!category.category_preset_id) continue;
                ids.add(category.category_preset_id);
                if (!stages[category.category_preset_id]) {
                    stages[category.category_preset_id] = [];
                }
                stages[category.category_preset_id].push(label);
            }
        }
        return { usedPresetIds: [...ids], stagesByPresetId: stages };
    }, [template]);

    if (!template) {
        return (
            <AppLayout
                title="Template"
                breadcrumb={[{ label: 'Templates', href: route(TEMPLATES_INDEX_ROUTE) }]}
            >
                <Head title="Template" />
                <p className="text-xs text-muted-foreground">
                    Template could not be loaded.
                </p>
            </AppLayout>
        );
    }

    const tabs = TAB_KEYS.map((key) => {
        if (key === 'checklist') {
            return { key, label: 'Checklist', count: template.checklist_items.length };
        }

        const section = template.sections.find((item) => item.stage === key);
        return {
            key,
            label: section ? STAGE_LABELS[section.stage] : key,
            count: section?.categories.length ?? 0,
            dirty: section ? Boolean(dirtySections[section.id]) : false,
        };
    });

    return (
        <AppLayout
            breadcrumb={[
                { label: 'Templates', href: route(TEMPLATES_INDEX_ROUTE) },
                { label: 'Builder' },
            ]}
        >
            <Head title={template.name} />
            <div className="space-y-2">
                {/* Single header row: name + market + status, buttons right, description below. */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="space-y-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap min-w-0">
                            <h1
                                className="font-heading text-xl font-bold text-foreground truncate min-w-0"
                                title={template.name}
                            >
                                {template.name}
                            </h1>
                            <Badge variant="outline" className="text-[10px] shrink-0">
                                {template.market?.name ?? 'No market'}
                            </Badge>
                            {template.is_active ? (
                                <Badge
                                    variant="outline"
                                    className="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px] shrink-0"
                                >
                                    <CheckCircle2 className="size-3" /> Active
                                </Badge>
                            ) : (
                                <Badge
                                    variant="secondary"
                                    className="gap-1 text-muted-foreground text-[10px] shrink-0"
                                >
                                    <XCircle className="size-3" /> Inactive
                                </Badge>
                            )}
                        </div>
                        {template.description && (
                            <p
                                className="text-xs text-muted-foreground truncate max-w-full sm:max-w-[33%]"
                                title={template.description}
                            >
                                {template.description}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-1.5 sm:gap-2 flex-nowrap shrink-0 overflow-x-auto no-scrollbar">
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs gap-1 px-2.5 shrink-0">
                            <Link href={route('admin.templates.category-presets.index')}>
                                <BookMarked className="size-3.5" /> Subcategories Library
                            </Link>
                        </Button>
                        <Button
                            variant={previewOpen ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setPreviewOpen((open) => !open)}
                            className="h-8 text-xs gap-1 px-2.5 shrink-0"
                        >
                            <Eye className="size-3.5" />
                            {previewOpen ? 'Hide Preview' : 'Live Preview'}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteOpen(true)}
                            className="h-8 text-xs gap-1 px-2 text-destructive hover:bg-destructive/10 shrink-0 cursor-pointer"
                            title="Delete template"
                        >
                            <Trash2 className="size-3.5" /> <span className="hidden sm:inline">Delete</span>
                        </Button>
                    </div>
                </div>

                <div className="flex items-center justify-between gap-2">
                    <BuilderTabs tabs={tabs} active={activeTab} onChange={setActiveTab} />
                    <Button
                        asChild
                        variant="success"
                        size="sm"
                        title="Everything saves automatically — mark this template as completed and return to Templates."
                        className="h-8 text-xs gap-1.5 font-semibold shrink-0"
                    >
                        <Link href={route(TEMPLATES_INDEX_ROUTE)}>
                            <CheckCircle2 className="size-3.5" /> Completed
                        </Link>
                    </Button>
                </div>

                <div
                    className={cn(
                        previewOpen &&
                        'lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start lg:gap-4'
                    )}
                >
                    {/* Sections stay mounted at all times so unsaved edits survive every
                        tab switch — non-stage tabs only hide them. */}
                    <div className="space-y-2 min-w-0">
                        {template.sections.map((section) => (
                            <SectionCard
                                key={section.id}
                                templateId={template.id}
                                section={section}
                                libraryPresets={libraryPresets}
                                usedPresetIds={usedPresetIds}
                                stagesByPresetId={stagesByPresetId}
                                hidden={activeTab !== section.stage}
                                onDirtyChange={handleDirtyChange}
                            />
                        ))}
                        {activeTab === 'checklist' && (
                            <ChecklistPanel templateId={template.id} items={template.checklist_items} />
                        )}
                    </div>

                    {previewOpen && (
                        <>
                            <div className="block lg:hidden rounded-xl border border-border/80 bg-card p-3 shadow-md my-2 max-h-[70vh] overflow-y-auto no-scrollbar">
                                <div className="flex items-center justify-between mb-2 pb-2 border-b border-border/60">
                                    <h3 className="font-heading text-xs font-bold flex items-center gap-1.5 text-foreground">
                                        <Eye className="size-3.5 text-primary" /> Field-Tech View (live)
                                    </h3>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setPreviewOpen(false)}
                                        className="h-6 text-[10px] px-2 cursor-pointer"
                                    >
                                        Hide
                                    </Button>
                                </div>
                                <TemplatePreviewPanel template={template} />
                            </div>

                            <aside className="hidden lg:block rounded-xl border border-border/80 bg-card p-4 max-h-[calc(100vh-12rem)] overflow-y-auto sticky top-24 no-scrollbar">
                                <h3 className="font-heading text-sm font-bold mb-3 flex items-center gap-1.5">
                                    <Eye className="size-4 text-primary" /> Field-Tech View (live)
                                </h3>
                                <TemplatePreviewPanel template={template} />
                            </aside>
                        </>
                    )}
                </div>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteOpen(false);
                }}
                title="Delete template"
                description={`Delete "${template.name}"? This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default AdminTemplateShow;
