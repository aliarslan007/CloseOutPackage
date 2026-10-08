import React from 'react';
import AppLayout from '@/layouts/app-layout';
import { TemplateSelector, TemplateOption } from '@/components/shared/selectors';
import { Button } from '@/components/ui/button';
import { Head, router, usePage } from '@inertiajs/react';
import { Bookmark, Plus } from 'lucide-react';
import Can from '@/components/shared/can';
import type { PageProps } from '@/types/inertia';

export interface TemplatesIndexProps {
    templates: TemplateOption[];
}

export function TemplatesIndex({ templates }: TemplatesIndexProps) {
    const { auth } = usePage<PageProps>().props;
    const isAdmin = auth.user.roles.includes('admin');

    return (
        <AppLayout title="Master Templates">
            <Head title="Templates" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Master COP Templates</h1>
                    </div>

                    <div className="flex items-center gap-2">
                        {!isAdmin && (
                            <Button
                                variant="outline"
                                className="gap-1.5"
                                onClick={() => router.get(route('favorites.index'))}
                            >
                                <Bookmark className="size-4" /> My Favorites
                            </Button>
                        )}
                        <Can role="admin">
                            <Button
                                className="gap-1.5"
                                onClick={() => router.get(route('admin.templates.create'))}
                            >
                                <Plus className="size-4" /> Create New Template
                            </Button>
                        </Can>
                    </div>
                </div>

                <TemplateSelector
                    templates={templates}
                    onSelectTemplate={(id) => router.get(route('admin.templates.edit', id))}
                />
            </div>
        </AppLayout>
    );
}

export default TemplatesIndex;
