import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, FileText } from 'lucide-react';
import * as React from 'react';

export interface TemplateOption {
    id: number;
    name?: string;
    title?: string;
    description?: string | null;
    market?: { id: number; name: string } | null;
}

export type PackagesCreatePageProps = PageProps<{
    project: {
        id: number;
        hash_id?: string;
        name: string;
    };
    templates: TemplateOption[];
}>;

export function PackagesCreate() {
    useFlashToasts();

    const { project, templates } = usePage<PackagesCreatePageProps>().props;

    const form = useForm({
        project_id: project.id,
        template_id: templates[0]?.id ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.data.template_id) return;
        form.post(route('packages.store', project.hash_id || project.id));
    };

    return (
        <AppLayout
            title={`New Close-Out Package - ${project.name}`}
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', project.hash_id || project.id) },
                { label: 'Create Package' },
            ]}
            headerActions={
                <Button asChild variant="ghost" size="sm" className="gap-1.5 text-xs">
                    <Link href={route('packages.index', project.hash_id || project.id)}>
                        <ArrowLeft className="size-4" /> Back to Closeout Packages
                    </Link>
                </Button>
            }
        >
            <Head title={`New Package - ${project.name}`} />

            <div className="max-w-2xl mx-auto space-y-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold flex items-center gap-2">
                            <FileText className="size-4 text-primary" /> Select Admin Master
                            Template
                        </CardTitle>
                        <CardDescription>
                            Choose the picture category structure and checklist template for cell
                            site "{project.name}".
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit}>
                        <CardContent className="space-y-4">
                            {templates.length === 0 ? (
                                <p className="text-xs text-destructive bg-destructive/10 p-3 rounded font-mono">
                                    No master templates found for this site's market. An Admin must
                                    create a template first.
                                </p>
                            ) : (
                                <FormField
                                    label="Master Template"
                                    htmlFor="template_id"
                                    required
                                    error={form.errors.template_id}
                                >
                                    <div className="space-y-2">
                                        {templates.map((tpl) => (
                                            <label
                                                key={tpl.id}
                                                className={`flex items-start gap-3 p-3.5 rounded-lg border text-xs cursor-pointer transition-colors ${
                                                    Number(form.data.template_id) === tpl.id
                                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                        : 'border-border/80 bg-card hover:bg-muted/10'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="template_id"
                                                    value={tpl.id}
                                                    checked={
                                                        Number(form.data.template_id) === tpl.id
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'template_id',
                                                            Number(e.target.value)
                                                        )
                                                    }
                                                    className="mt-0.5"
                                                />
                                                <div className="space-y-1">
                                                    <span className="font-bold text-foreground font-heading text-sm block">
                                                        {tpl.name || tpl.title}
                                                    </span>
                                                    {tpl.description && (
                                                        <p className="text-muted-foreground text-[11px]">
                                                            {tpl.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </FormField>
                            )}
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('packages.index', project.hash_id || project.id)}>Cancel</Link>
                            </Button>
                            <Button
                                type="submit"
                                loading={form.processing}
                                disabled={templates.length === 0 || !form.data.template_id}
                                className="gap-1.5"
                            >
                                <Check className="size-4" /> Initialize Package
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default PackagesCreate;
