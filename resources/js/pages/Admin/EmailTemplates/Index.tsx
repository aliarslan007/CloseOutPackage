import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { EmailTemplateRow } from '@/pages/Admin/EmailTemplates/components/email-template-row';
import {
    type EmailTemplateItem,
    type EmailTypeOption,
    type PaginatedEmailTemplates,
} from '@/pages/Admin/EmailTemplates/components/email-template-types';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Copy, Plus } from 'lucide-react';
import { useState } from 'react';

export type AdminEmailTemplatesIndexProps = PageProps<{
    templates: PaginatedEmailTemplates;
    emailTypes: EmailTypeOption[];
    filters: { search: string | null; type: string | null };
}>;

interface DeleteTarget {
    readonly id: number;
    readonly name: string;
}

export function AdminEmailTemplatesIndex() {
    useFlashToasts();

    const { templates, emailTypes, filters } = usePage<AdminEmailTemplatesIndexProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);

    const applyFilters = (patch: { search?: string; type?: string; page?: number }): void => {
        router.get(
            route('admin.email-templates.index'),
            {
                search: patch.search ?? (search === '' ? null : search),
                type: patch.type ?? (type === '' ? null : type),
                page: patch.page,
            },
            { preserveState: true }
        );
    };

    const setDefault = (template: EmailTemplateItem): void => {
        router.post(
            route('admin.email-templates.setDefault', template.id),
            {},
            { preserveScroll: true }
        );
    };

    const duplicate = (template: EmailTemplateItem): void => {
        router.post(
            route('admin.email-templates.duplicate', template.id),
            {},
            { preserveScroll: true }
        );
    };

    const destroy = (): void => {
        if (deleteTarget === null) {
            return;
        }
        router.delete(route('admin.email-templates.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const rows = templates.data;

    return (
        <AppLayout
            title="Email Templates"
            breadcrumb={[{ label: 'Email Templates' }]}
            headerActions={
                <Button asChild className="gap-2 text-xs">
                    <Link href={route('admin.email-templates.create')}>
                        <Plus className="size-4" /> New Template
                    </Link>
                </Button>
            }
        >
            <Head title="Email Templates" />

            <div className="space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters({ search: event.currentTarget.value });
                            }
                        }}
                        onBlur={() => applyFilters({})}
                        placeholder="Search by name…"
                        className="max-w-xs text-sm"
                    />
                    <select
                        value={type}
                        onChange={(event) => {
                            setType(event.target.value);
                            applyFilters({ type: event.target.value });
                        }}
                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option value="">All types</option>
                        {emailTypes.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                {rows.length === 0 ? (
                    <EmptyCard
                        title="No email templates"
                        description="Create a template to customize the emails sent to customers, or adjust the filters above."
                        icon={<Copy className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border/80 bg-card shadow-2xs overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Name</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Updated</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((template) => (
                                    <EmailTemplateRow
                                        key={template.id}
                                        template={template}
                                        emailTypes={emailTypes}
                                        onSetDefault={setDefault}
                                        onDuplicate={duplicate}
                                        onDelete={(target) =>
                                            setDeleteTarget({
                                                id: target.id,
                                                name: target.name,
                                            })
                                        }
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {templates.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2 text-xs text-muted-foreground">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={templates.current_page <= 1}
                            onClick={() => applyFilters({ page: templates.current_page - 1 })}
                        >
                            <ChevronLeft className="size-3" />
                        </Button>
                        Page {templates.current_page} of {templates.last_page}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={templates.current_page >= templates.last_page}
                            onClick={() => applyFilters({ page: templates.current_page + 1 })}
                        >
                            <ChevronRight className="size-3" />
                        </Button>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete email template"
                description={`Delete "${deleteTarget?.name ?? ''}"? Templates are soft-deleted, so an administrator can restore it later.`}
                confirmLabel="Delete"
                variant="destructive"
                onConfirm={destroy}
            />
        </AppLayout>
    );
}

export default AdminEmailTemplatesIndex;
