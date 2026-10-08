import React from 'react';
import AppLayout from '@/layouts/app-layout';
import { TemplateSelector, TemplateOption } from '@/components/shared/selectors';
import { Head, router } from '@inertiajs/react';

export interface FieldTemplateSelectorProps {
    templates: TemplateOption[];
}

export function FieldTemplateSelector({ templates }: FieldTemplateSelectorProps) {
    const handleSelectTemplate = (templateId: number) => {
        router.get(route('packages.create', { template_id: templateId }));
    };

    return (
        <AppLayout title="Select COP Template">
            <Head title="Select Template" />

            <div className="space-y-6 max-w-4xl mx-auto">
                <div className="text-center space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">Select Master COP Template</h1>
                </div>

                <TemplateSelector
                    templates={templates}
                    onSelectTemplate={handleSelectTemplate}
                />
            </div>
        </AppLayout>
    );
}

export default FieldTemplateSelector;
