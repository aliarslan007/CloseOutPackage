import * as React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { TemplatePreviewDialog } from '@/components/templates/template-preview-dialog';
import { TemplateDetail } from '@/components/templates/template-types';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { PageProps } from '@/types/inertia';
import {
    Activity,
    BookMarked,
    CheckCircle2,
    Copy,
    Eye,
    FileText,
    Info,
    Layers,
    ListChecks,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    XCircle,
} from 'lucide-react';

export interface AdminTemplateListItem {
    id: number;
    hash_id?: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    inactive_reason?: string | null;
    market_id: number;
    market?: { id: number; name: string } | null;
    sections?: Array<{ id: number; stage?: string; heading?: string; admin_notes?: string | null; sort_order?: number; categories?: Array<{ id: number; name: string; description?: string | null; default_picture_count?: number; is_required?: boolean; sort_order?: number }> }>;
    checklist_items?: Array<{ id: number; label: string; has_explain_field?: boolean; allows_na?: boolean; sort_order?: number }>;
    checklist_items_count?: number;
    duplicated_from_id?: number | null;
    duplicatedFrom?: { id: number; name: string } | null;
}

export interface MarketGroup {
    market: string;
    templates: AdminTemplateListItem[];
}

export type AdminTemplatesPageProps = PageProps<{
    grouped: MarketGroup[];
    unassigned: AdminTemplateListItem[];
}>;

interface TemplateCardProps {
    template: AdminTemplateListItem;
    onPreview: (t: AdminTemplateListItem) => void;
    onDelete: (t: AdminTemplateListItem) => void;
}

function TemplateCard({
    template,
    onPreview,
    onDelete,
}: Readonly<TemplateCardProps>) {
    const handleDuplicate = (id: number) => {
        router.post(route('admin.templates.duplicate', id));
    };

    const isDuplicate = Boolean(template.duplicated_from_id);

    return (
        <div className={`bg-card rounded-xl border shadow-2xs hover:border-primary/40 transition-all flex flex-col justify-between space-y-4 p-5 text-xs ${isDuplicate ? 'border-l-2 border-l-amber-500/60 border-border/80' : 'border-border/80'}`}>
            <div className="space-y-2">
                <div className="flex items-center gap-2 min-w-0">
                    <Layers className="size-4 text-primary shrink-0" />
                    <h3 className="font-bold font-heading text-sm text-foreground break-words min-w-0">
                        {template.name}
                    </h3>
                    {isDuplicate && (
                        <Badge
                            variant="outline"
                            className="gap-1 bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 text-[10px] shrink-0"
                        >
                            <Copy className="size-2.5" /> Copy
                        </Badge>
                    )}
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
                <p className="text-muted-foreground line-clamp-2 text-[11px]" title={template.description ?? ''}>
                    {template.description || 'No description provided.'}
                </p>
                {!template.is_active && template.inactive_reason && (
                    <p className="flex items-start gap-1.5 rounded-md bg-muted/60 px-2 py-1.5 text-[11px] text-muted-foreground">
                        <Info className="mt-px size-3 shrink-0" />
                        <span>{template.inactive_reason}</span>
                    </p>
                )}
            </div>

            <div className="flex items-center justify-between text-muted-foreground text-[11px]">
                <span className="flex items-center gap-1.5">
                    <Layers className="size-3.5" />
                    {template.sections?.length ?? 3} Sections
                </span>
                <span className="flex items-center gap-1.5">
                    <ListChecks className="size-3.5" />
                    {template.checklist_items_count ?? 0} Checklist
                </span>
            </div>

            <div className="pt-3 border-t border-border/60 flex flex-wrap items-center justify-between gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPreview(template)}
                    className="h-7 text-[11px] gap-1 px-2"
                    title="Preview Template"
                >
                    <Eye className="size-3 text-primary" /> Preview
                </Button>
                <Button asChild variant="outline" size="sm" className="h-7 text-[11px] px-2">
                    <Link href={route('admin.templates.show', template.hash_id || template.id)}>
                        <Pencil className="size-3" /> Build
                    </Link>
                </Button>
                <Button asChild variant="outline" size="sm" className="h-7 text-[11px] px-2">
                    <Link href={route('admin.templates.edit', template.hash_id || template.id)}>
                        Edit
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleDuplicate(template.id)}
                    className="h-7 text-[11px] gap-1 px-2"
                    title="Duplicate Template"
                >
                    <Copy className="size-3" /> Duplicate
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onDelete(template)}
                    className="h-7 text-[11px] text-destructive hover:bg-destructive/10 px-2"
                    title="Delete Template"
                >
                    <Trash2 className="size-3" />
                </Button>
            </div>
        </div>
    );
}

export function AdminTemplatesIndex() {
    useFlashToasts();

    const { grouped, unassigned } = usePage<AdminTemplatesPageProps>().props;
    const [deleteTarget, setDeleteTarget] = React.useState<AdminTemplateListItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const [previewTemplate, setPreviewTemplate] = React.useState<TemplateDetail | null>(null);
    const [previewOpen, setPreviewOpen] = React.useState(false);

    const handlePreview = (template: AdminTemplateListItem) => {
        const detail: TemplateDetail = {
            id: template.id,
            hash_id: template.hash_id,
            name: template.name,
            description: template.description,
            is_active: template.is_active,
            inactive_reason: template.inactive_reason,
            market_id: template.market_id,
            market: template.market,
            sections: (template.sections ?? []).map((s) => ({
                id: s.id,
                stage: (s.stage as TemplateDetail['sections'][number]['stage']) ?? 'pre_construction',
                heading: s.heading ?? '',
                admin_notes: s.admin_notes,
                sort_order: s.sort_order ?? 0,
                categories: (s.categories ?? []).map((c) => ({
                    id: c.id,
                    name: c.name,
                    description: c.description,
                    default_picture_count: c.default_picture_count ?? 1,
                    is_required: Boolean(c.is_required),
                    sort_order: c.sort_order ?? 0,
                })),
            })),
            checklist_items: (template.checklist_items ?? []).map((c) => ({
                id: c.id,
                label: c.label,
                has_explain_field: Boolean(c.has_explain_field),
                allows_na: Boolean(c.allows_na),
                sort_order: c.sort_order ?? 0,
            })),
        };
        setPreviewTemplate(detail);
        setPreviewOpen(true);
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        setIsDeleting(true);
        router.delete(route('admin.templates.destroy', deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    const isEmpty = (!grouped || grouped.length === 0) && (!unassigned || unassigned.length === 0);

    if (isEmpty && !grouped && !unassigned) {
        return (
            <AppLayout title="Templates" breadcrumb={[{ label: 'Templates' }]}>
                <Head title="Templates" />
                <EmptyCard
                    title="Templates unavailable"
                    description="The template list could not be loaded. Please try refreshing the page."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Templates"
            breadcrumb={[{ label: 'Templates' }]}
            headerActions={
                <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full sm:w-auto">
                    <Button
                        asChild
                        variant="ghost"
                        className="gap-2 text-xs border border-foreground/40 bg-card text-foreground shadow-xs hover:bg-accent hover:text-accent-foreground flex-1 sm:flex-initial h-9"
                    >
                        <Link href={route('admin.templates.category-presets.index')}>
                            <BookMarked className="size-4" /> Subcategory Headings
                        </Link>
                    </Button>
                    <Button asChild className="gap-2 text-xs flex-1 sm:flex-initial h-9">
                        <Link href={route('admin.templates.create')}>
                            <Plus className="size-4" /> Create Template
                        </Link>
                    </Button>
                </div>
            }
        >
            <Head title="Templates" />
            <div className="space-y-2">
                {isEmpty ? (
                    <EmptyCard
                        title="No templates yet"
                        description="Create your first template to define the stages, picture categories, and checklist for field work."
                        icon={<FileText className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <>
                        {grouped?.map((group) => (
                            <section key={group.market} className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <MapPin className="size-4 text-primary" />
                                    <h2 className="font-heading text-sm font-bold text-foreground">
                                        {group.market}
                                    </h2>
                                    <Badge variant="secondary" className="text-[10px]">
                                        {group.templates.length} {group.templates.length === 1 ? 'template' : 'templates'}
                                    </Badge>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {group.templates.map((template) => (
                                        <TemplateCard
                                            key={template.id}
                                            template={template}
                                            onPreview={handlePreview}
                                            onDelete={setDeleteTarget}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}

                        {unassigned && unassigned.length > 0 && (
                            <section className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <FileText className="size-4 text-muted-foreground" />
                                    <h2 className="font-heading text-sm font-bold text-foreground">
                                        Unassigned
                                    </h2>
                                    <Badge variant="secondary" className="text-[10px]">
                                        {unassigned.length} {unassigned.length === 1 ? 'template' : 'templates'}
                                    </Badge>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {unassigned.map((template) => (
                                        <TemplateCard
                                            key={template.id}
                                            template={template}
                                            onPreview={handlePreview}
                                            onDelete={setDeleteTarget}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>

            {previewTemplate && (
                <TemplatePreviewDialog
                    open={previewOpen}
                    onOpenChange={setPreviewOpen}
                    template={previewTemplate}
                />
            )}

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteTarget(null);
                }}
                title="Delete template"
                description={`Delete "${deleteTarget?.name}"? This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default AdminTemplatesIndex;
