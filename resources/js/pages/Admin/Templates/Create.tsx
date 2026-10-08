import * as React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/Checkbox';
import { FormField } from '@/components/shared/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { PageProps } from '@/types/inertia';

interface CreateTemplateForm {
    market_id: number | '';
    name: string;
    description: string;
    is_active: boolean;
}

export interface MarketOptionItem {
    id: number;
    name: string;
}

export type AdminTemplateCreatePageProps = PageProps<{
    markets: MarketOptionItem[];
}>;

export function AdminTemplateCreate() {
    useFlashToasts();

    const { markets } = usePage<AdminTemplateCreatePageProps>().props;

    const form = useForm<CreateTemplateForm>({
        market_id: '',
        name: '',
        description: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('admin.templates.store'));
    };

    return (
            <AppLayout title="Create Template" breadcrumb={[{ label: 'Templates', href: route('admin.templates.index') }, { label: 'Create' }]}>
            <Head title="Create Template" />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Create Master Template</CardTitle>
                        <CardDescription>
                            The three standard stages are created automatically — you configure
                            headings, picture categories, and checklist items after creation.
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent className="space-y-4">
                            <FormField
                                label="Market"
                                htmlFor="market"
                                required
                                error={form.errors.market_id}
                            >
                                <select
                                    id="market"
                                    name="market_id"
                                    value={form.data.market_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'market_id',
                                            e.target.value === '' ? '' : Number(e.target.value)
                                        )
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    required
                                >
                                    <option value="">Select a market...</option>
                                    {markets.map((market) => (
                                        <option key={market.id} value={market.id}>
                                            {market.name}
                                        </option>
                                    ))}
                                </select>
                            </FormField>

                            <FormField
                                label="Template Name"
                                htmlFor="name"
                                required
                                error={form.errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="e.g. Ohio Verizon Standard COP"
                                    maxLength={255}
                                    required
                                    error={Boolean(form.errors.name)}
                                />
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    {form.data.name.length}/255 characters
                                </p>
                            </FormField>

                            <FormField
                                label="Description"
                                htmlFor="description"
                                error={form.errors.description}
                            >
                                <Textarea
                                    id="description"
                                    name="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    placeholder="Detailed description of template requirements..."
                                    maxLength={500}
                                    error={Boolean(form.errors.description)}
                                />
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    {form.data.description.length}/500 characters
                                </p>
                            </FormField>

                            <FormField label="Active">
                                <label className="flex items-center gap-2 text-sm cursor-pointer">
                                    <Checkbox
                                        checked={form.data.is_active}
                                        onChange={(e) => form.setData('is_active', e.target.checked)}
                                    />
                                    <span>Template is active and available for field work</span>
                                </label>
                            </FormField>
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.templates.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Create Template
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default AdminTemplateCreate;
