import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { SearchInput } from '@/components/shared/search-input';
import {
    SubcategoryFormDialog,
    SubcategoryItem,
} from '@/components/templates/subcategory-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, router, usePage } from '@inertiajs/react';
import {
    BookMarked,
    Camera,
    CircleSlash2,
    Copy,
    Pencil,
    Plus,
    Power,
    Trash2,
    X,
} from 'lucide-react';
import * as React from 'react';

interface PresetFilters {
    search: string;
    status: string;
    sort: string;
}

export type AdminCategoryPresetsPageProps = PageProps<{
    presets: SubcategoryItem[];
    filters: Partial<PresetFilters>;
}>;

function renderEmptyState(hasActiveFilters: boolean) {
    if (hasActiveFilters) {
        return (
            <EmptyCard
                title="No subcategories match"
                description="Try adjusting or clearing your filters."
                icon={<CircleSlash2 className="size-10 text-muted-foreground" />}
            />
        );
    }

    return (
        <EmptyCard
            title="No subcategories yet"
            description='Add reusable photo groups like "Tower Foundation" or "Antenna Mount". You will pick them by name while building templates — no retyping.'
            icon={<BookMarked className="size-10 text-muted-foreground" />}
        />
    );
}

export function AdminCategoryPresets() {
    useFlashToasts();

    const { presets, filters: serverFilters } = usePage<AdminCategoryPresetsPageProps>().props;
    const [formOpen, setFormOpen] = React.useState(false);
    const [editingPreset, setEditingPreset] = React.useState<SubcategoryItem | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<SubcategoryItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const [filters, setFilters] = React.useState<PresetFilters>({
        search: serverFilters.search ?? '',
        status: serverFilters.status ?? '',
        sort: serverFilters.sort ?? '',
    });

    const pushFilters = React.useCallback(
        (next: Partial<PresetFilters>) => {
            const merged = { ...filters, ...next };
            const params: Record<string, string> = {};
            if (merged.search) params.search = merged.search;
            if (merged.status) params.status = merged.status;
            if (merged.sort) params.sort = merged.sort;

            router.get(route('admin.templates.category-presets.index'), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [filters]
    );

    const handleFilterChange = (key: keyof PresetFilters, value: string) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
        pushFilters({ [key]: value });
    };

    const handleClearFilters = () => {
        const cleared = { search: '', status: '', sort: '' };
        setFilters(cleared);
        pushFilters(cleared);
    };

    const hasActiveFilters =
        Boolean(filters.search) || Boolean(filters.status) || Boolean(filters.sort);

    const openCreate = () => {
        setEditingPreset(null);
        setFormOpen(true);
    };

    const openEdit = (preset: SubcategoryItem) => {
        setEditingPreset(preset);
        setFormOpen(true);
    };

    const handleToggleActive = (preset: SubcategoryItem) => {
        router.post(route('admin.templates.category-presets.toggle-active', preset.id));
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        setIsDeleting(true);
        router.delete(route('admin.templates.category-presets.destroy', deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    return (
        <AppLayout
            title="Subcategory Headings"
            breadcrumb={[
                { label: 'Templates', href: route('admin.templates.index') },
                { label: 'Subcategory Headings' },
            ]}
            headerActions={
                <Button onClick={openCreate} className="gap-2 text-xs shrink-0 h-9">
                    <Plus className="size-4" /> Add Subcategory
                </Button>
            }
        >
            <Head title="Subcategory Headings Library" />

            <div className="space-y-3">
                {/* Dedicated Filter & Search Toolbar */}
                <div className="rounded-xl border bg-card p-3 shadow-2xs">
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <SearchInput
                            value={filters.search}
                            onSearchChange={(value) => handleFilterChange('search', value)}
                            placeholder="Search subcategories..."
                            className="w-full sm:w-64 shrink-0"
                        />
                        <select
                            value={filters.status}
                            onChange={(e) => handleFilterChange('status', e.target.value)}
                            aria-label="Filter by status"
                            className="h-9 rounded-md border border-input bg-card px-3 pr-8 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select
                            value={filters.sort}
                            onChange={(e) => handleFilterChange('sort', e.target.value)}
                            aria-label="Sort"
                            className="h-9 rounded-md border border-input bg-card px-3 pr-8 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0"
                        >
                            <option value="">Sort: Name A→Z</option>
                            <option value="name_desc">Name Z→A</option>
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                        </select>
                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                                className="h-9 gap-1.5 text-xs text-muted-foreground shrink-0 cursor-pointer"
                            >
                                <X className="size-3" /> Clear
                            </Button>
                        )}
                    </div>
                </div>

                {presets.length === 0 ? (
                    renderEmptyState(hasActiveFilters)
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        {presets.map((preset) => (
                            <div
                                key={preset.id}
                                className={`bg-card rounded-xl border shadow-2xs hover:border-primary/40 transition-all flex flex-col justify-between space-y-3 p-4 ${
                                    preset.is_active
                                        ? 'border-border/80'
                                        : 'border-border/60 opacity-70'
                                }`}
                            >
                                <div className="space-y-1.5">
                                    <div className="flex items-center gap-2 min-w-0 flex-wrap">
                                        <Camera className="size-4 text-primary shrink-0" />
                                        <h3
                                            className="font-heading text-sm font-bold text-foreground truncate min-w-0"
                                            title={preset.name}
                                        >
                                            {preset.name}
                                        </h3>
                                    </div>
                                    {preset.description && (
                                        <p
                                            className="text-[11px] text-muted-foreground line-clamp-2"
                                            title={preset.description}
                                        >
                                            {preset.description}
                                        </p>
                                    )}
                                    <div className="flex items-center gap-1.5 flex-wrap pt-0.5">
                                        <Badge variant="secondary" className="text-[10px]">
                                            {preset.default_picture_count}{' '}
                                            {preset.default_picture_count === 1
                                                ? 'photo'
                                                : 'photos'}
                                        </Badge>
                                        {preset.is_required && (
                                            <Badge variant="warning" className="text-[10px]">
                                                Must capture
                                            </Badge>
                                        )}
                                        {preset.is_active ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px]"
                                            >
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 bg-muted text-muted-foreground text-[10px]"
                                            >
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                <div className="pt-2.5 border-t border-border/60 flex flex-wrap items-center gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => openEdit(preset)}
                                        className="h-7 text-[11px] gap-1 px-2"
                                    >
                                        <Pencil className="size-3" /> Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleToggleActive(preset)}
                                        className="h-7 text-[11px] gap-1 px-2"
                                        title={
                                            preset.is_active
                                                ? 'Set Inactive — stays saved, hidden when building templates'
                                                : 'Set Active — appears again when building templates'
                                        }
                                    >
                                        <Power className="size-3" />
                                        {preset.is_active ? 'Deactivate' : 'Activate'}
                                    </Button>
                                    {!preset.is_active && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setDeleteTarget(preset)}
                                            className="h-7 text-[11px] gap-1 px-2 text-destructive hover:bg-destructive/10"
                                            title="Delete permanently"
                                        >
                                            <Trash2 className="size-3" /> Delete
                                        </Button>
                                    )}
                                    <span className="ml-auto inline-flex items-center gap-1 text-[10px] text-muted-foreground">
                                        <Copy className="size-2.5" />
                                        Reusable
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {formOpen && (
                <SubcategoryFormDialog
                    key={editingPreset?.id ?? 'new'}
                    mode="library"
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    preset={editingPreset}
                />
            )}

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteTarget(null);
                }}
                title="Delete subcategory"
                description={`Delete "${deleteTarget?.name}"? Templates already using it are not affected. This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default AdminCategoryPresets;
