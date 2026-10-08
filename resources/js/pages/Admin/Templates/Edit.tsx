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
import { MarketOptionItem } from '@/pages/Admin/Templates/Create';
import { AlertTriangle } from 'lucide-react';

interface EditTemplateForm {
    name: string;
    description: string;
    is_active: boolean;
    market_id: number | '';
}

export interface EditTemplateProp {
    id: number;
    hash_id?: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    inactive_reason?: string | null;
    market_id: number;
    market?: { id: number; name: string } | null;
}

export type AdminTemplateEditPageProps = PageProps<{
    template: EditTemplateProp;
    markets: MarketOptionItem[];
}>;

export function AdminTemplateEdit() {
    useFlashToasts();

    const { template, markets } = usePage<AdminTemplateEditPageProps>().props;
    const marketMissing = template.market === null || template.market === undefined;

    const form = useForm<EditTemplateForm>({
        name: template.name,
        description: template.description ?? '',
        is_active: template.is_active,
        market_id: marketMissing ? '' : template.market_id,
    });

    const linkedMarketId = form.data.market_id === '' ? null : Number(form.data.market_id);
    const canActivate = !marketMissing || linkedMarketId !== null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (marketMissing && form.data.market_id === '') {
            form.setError('market_id', 'Select a market to link this template.');
            return;
        }

        form.put(route('admin.templates.update', template.hash_id || template.id));
    };

    return (
            <AppLayout title="Edit Template" breadcrumb={[{ label: 'Templates', href: route('admin.templates.index') }, { label: 'Edit' }]}>
            <Head title={`Edit ${template.name}`} />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Edit Template</CardTitle>
                        <CardDescription>
                            {marketMissing ? (
                                'This template has no market. Pick one below to be able to activate it again.'
                            ) : (
                                <>
                                    Scoped to market{' '}
                                    <span className="font-medium">
                                        {template.market?.name ?? `#${template.market_id ?? '—'}`}
                                    </span>
                                    . The market cannot be changed — duplicate the template to use it
                                    in another market.
                                </>
                            )}
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent className="space-y-4">
                            {marketMissing && (
                                <div className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2.5 text-xs text-amber-700 dark:text-amber-300">
                                    <AlertTriangle className="mt-px size-3.5 shrink-0" />
                                    <div className="space-y-2">
                                        <p>{template.inactive_reason ?? 'This template has no market and cannot be activated.'}</p>
                                    </div>
                                </div>
                            )}

                            {marketMissing && (
                                <FormField
                                    label="Link to market"
                                    htmlFor="market_id"
                                    required
                                    error={form.errors.market_id}
                                >
                                    <select
                                        id="market_id"
                                        name="market_id"
                                        value={form.data.market_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'market_id',
                                                e.target.value === '' ? '' : Number(e.target.value),
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
                            )}
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
                                    maxLength={500}
                                    error={Boolean(form.errors.description)}
                                />
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    {form.data.description.length}/500 characters
                                </p>
                            </FormField>

                            <FormField label="Active">
                                <label className={`flex items-center gap-2 text-sm ${canActivate ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'}`}>
                                    <Checkbox
                                        checked={canActivate && form.data.is_active}
                                        disabled={!canActivate}
                                        onChange={(e) => form.setData('is_active', e.target.checked)}
                                    />
                                    <span>
                                        {canActivate
                                            ? 'Template is active and available for field work'
                                            : 'Link this template to a market to activate it'}
                                    </span>
                                </label>
                            </FormField>
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.templates.index')}>
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Save Changes
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default AdminTemplateEdit;
