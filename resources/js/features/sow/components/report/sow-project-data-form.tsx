import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import * as React from 'react';
import { SOW_FIELD_LIMITS } from './sow-field-limits';

export interface SowProjectDataFormProps {
    reportId: number;
    readOnly?: boolean;
    initial: {
        site_address: string | null;
        site_city: string | null;
        site_county: string | null;
        latitude: string | null;
        longitude: string | null;
        project_description: string | null;
    };
}

/** Page 2 — Table 1: Project Data (address, city, county, coordinates, description). */
export function SowProjectDataForm({
    reportId,
    initial,
    readOnly = false,
}: Readonly<SowProjectDataFormProps>) {
    const form = useForm({
        site_address: initial.site_address ?? '',
        site_city: initial.site_city ?? '',
        site_county: initial.site_county ?? '',
        latitude: initial.latitude ?? '',
        longitude: initial.longitude ?? '',
        project_description: initial.project_description ?? '',
    });
    const { setData } = form;

    React.useEffect(() => {
        setData({
            site_address: initial.site_address ?? '',
            site_city: initial.site_city ?? '',
            site_county: initial.site_county ?? '',
            latitude: initial.latitude ?? '',
            longitude: initial.longitude ?? '',
            project_description: initial.project_description ?? '',
        });
    }, [initial, setData]);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-semibold">Table 1 — Project Data</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (readOnly) return;
                        form.put(route('sow.reports.update', { report: reportId }), {
                            preserveScroll: true,
                        });
                    }}
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >
                    <FormField
                        label="Site Address"
                        error={form.errors.site_address}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.site_address}
                        valueLength={(form.data.site_address ?? '').length}
                    >
                        <Input
                            value={form.data.site_address ?? ''}
                            onChange={(e) => form.setData('site_address', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.site_address}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Site City"
                        error={form.errors.site_city}
                        maxLength={SOW_FIELD_LIMITS.site_city}
                        valueLength={(form.data.site_city ?? '').length}
                    >
                        <Input
                            value={form.data.site_city ?? ''}
                            onChange={(e) => form.setData('site_city', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.site_city}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Site County"
                        error={form.errors.site_county}
                        maxLength={SOW_FIELD_LIMITS.site_county}
                        valueLength={(form.data.site_county ?? '').length}
                    >
                        <Input
                            value={form.data.site_county ?? ''}
                            onChange={(e) => form.setData('site_county', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.site_county}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Latitude"
                        error={form.errors.latitude}
                        helper="Decimal degrees, e.g. 37.1234567"
                    >
                        <Input
                            value={form.data.latitude ?? ''}
                            onChange={(e) => form.setData('latitude', e.target.value)}
                            inputMode="decimal"
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Longitude"
                        error={form.errors.longitude}
                        helper="Decimal degrees, e.g. -122.1234567"
                    >
                        <Input
                            value={form.data.longitude ?? ''}
                            onChange={(e) => form.setData('longitude', e.target.value)}
                            inputMode="decimal"
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Project Description"
                        error={form.errors.project_description}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.project_description}
                        valueLength={(form.data.project_description ?? '').length}
                    >
                        <Textarea
                            value={form.data.project_description ?? ''}
                            onChange={(e) => form.setData('project_description', e.target.value)}
                            rows={3}
                            maxLength={SOW_FIELD_LIMITS.project_description}
                            disabled={readOnly}
                        />
                    </FormField>
                    {!readOnly && (
                        <div className="sm:col-span-2 flex justify-end">
                            <Button type="submit" disabled={form.processing} className="gap-1.5">
                                <Save className="size-3.5" />
                                {form.processing ? 'Saving…' : 'Save Project Data'}
                            </Button>
                        </div>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}
