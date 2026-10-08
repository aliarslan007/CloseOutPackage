import { Checkbox } from '@/components/Checkbox';
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
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import * as React from 'react';

interface EditMarketForm {
    name: string;
    description: string;
    is_active: boolean;
}

export interface EditMarketProp {
    id: number;
    hash_id?: string;
    name: string;
    slug: string;
    description?: string | null;
    is_active: boolean;
}

export type AdminMarketEditPageProps = PageProps<{
    market: EditMarketProp;
}>;

export function AdminMarketEdit() {
    useFlashToasts();

    const { market } = usePage<AdminMarketEditPageProps>().props;

    const form = useForm<EditMarketForm>({
        name: market.name,
        description: market.description ?? '',
        is_active: market.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('admin.markets.update', market.hash_id || market.id));
    };

    return (
        <AppLayout
            title="Edit Market"
            breadcrumb={[
                { label: 'Markets', href: route('admin.markets.index') },
                { label: 'Edit' },
            ]}
        >
            <Head title={`Edit ${market.name}`} />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Edit Market Details</CardTitle>
                        <CardDescription>
                            Slug (<span className="font-mono">{market.slug}</span>) is generated
                            automatically and cannot be changed.
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent className="space-y-4">
                            <FormField
                                label="Market Name"
                                htmlFor="name"
                                required
                                error={form.errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="e.g. Ohio Market"
                                    maxLength={120}
                                    required
                                    error={Boolean(form.errors.name)}
                                />
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
                                    placeholder="What is this market responsible for?"
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
                                        onChange={(e) =>
                                            form.setData('is_active', e.target.checked)
                                        }
                                    />
                                    <span>Market is active and available for use</span>
                                </label>
                            </FormField>
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.markets.index')}>Cancel</Link>
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

export default AdminMarketEdit;
