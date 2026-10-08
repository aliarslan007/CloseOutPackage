import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { EmailTemplateForm } from '@/pages/Admin/EmailTemplates/components/email-template-form';
import {
    type DefaultEmailTemplatesMap,
    type EmailTemplateItem,
    type EmailTemplateVariables,
    type EmailTypeOption,
} from '@/pages/Admin/EmailTemplates/components/email-template-types';
import { PageProps } from '@/types/inertia';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export type AdminEmailTemplateShowProps = PageProps<{
    template: EmailTemplateItem | null;
    emailTypes: EmailTypeOption[];
    variables: EmailTemplateVariables;
    defaultTemplates?: DefaultEmailTemplatesMap;
}>;

export function AdminEmailTemplateShow() {
    useFlashToasts();

    const { template, emailTypes, variables, defaultTemplates = {} } =
        usePage<AdminEmailTemplateShowProps>().props;
    const pageTitle = template === null ? 'Create Email Template' : `Edit — ${template.name}`;

    return (
        <AppLayout
            title={template === null ? 'Create Email Template' : 'Edit Email Template'}
            breadcrumb={[
                { label: 'Email Templates', href: route('admin.email-templates.index') },
                { label: template === null ? 'Create' : 'Edit' },
            ]}
        >
            <Head title={pageTitle} />

            {/* Full-width layout, matching the spacing of the other admin pages. */}
            <div className="space-y-5 pb-8">
                <Link
                    href={route('admin.email-templates.index')}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" /> Back to Email Templates
                </Link>

                <EmailTemplateForm
                    template={template}
                    emailTypes={emailTypes}
                    variables={variables}
                    defaultTemplates={defaultTemplates}
                />
            </div>
        </AppLayout>
    );
}

export default AdminEmailTemplateShow;
