import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import * as React from 'react';
import { SOW_FIELD_LIMITS } from './sow-field-limits';

export interface SowSurveyDataFormProps {
    reportId: number;
    readOnly?: boolean;
    initial: {
        field_survey_date: string | null;
        fiber_provider: string | null;
        provider_contact_name: string | null;
        provider_contact_phone: string | null;
        provider_contact_email: string | null;
        demarcation: string | null;
        ps_number: string | null;
        survey_contractor_name: string | null;
        survey_contact_name: string | null;
        survey_contact_phone: string | null;
        survey_contact_email: string | null;
        scope_of_work_description: string | null;
        site_access_notes: string | null;
    };
}

/** Page 2 — Table 2: Survey Data (survey date, contacts, demarcation, PS#, scope, access). */
export function SowSurveyDataForm({
    reportId,
    initial,
    readOnly = false,
}: Readonly<SowSurveyDataFormProps>) {
    const form = useForm({
        field_survey_date: initial.field_survey_date ? initial.field_survey_date.slice(0, 10) : '',
        fiber_provider: initial.fiber_provider ?? 'VZB',
        provider_contact_name: initial.provider_contact_name ?? '',
        provider_contact_phone: initial.provider_contact_phone ?? '',
        provider_contact_email: initial.provider_contact_email ?? '',
        demarcation: initial.demarcation ?? '',
        ps_number: initial.ps_number ?? '',
        survey_contractor_name: initial.survey_contractor_name ?? '',
        survey_contact_name: initial.survey_contact_name ?? '',
        survey_contact_phone: initial.survey_contact_phone ?? '',
        survey_contact_email: initial.survey_contact_email ?? '',
        scope_of_work_description: initial.scope_of_work_description ?? '',
        site_access_notes: initial.site_access_notes ?? '',
    });
    const { setData } = form;

    React.useEffect(() => {
        setData({
            field_survey_date: initial.field_survey_date ? initial.field_survey_date.slice(0, 10) : '',
            fiber_provider: initial.fiber_provider ?? 'VZB',
            provider_contact_name: initial.provider_contact_name ?? '',
            provider_contact_phone: initial.provider_contact_phone ?? '',
            provider_contact_email: initial.provider_contact_email ?? '',
            demarcation: initial.demarcation ?? '',
            ps_number: initial.ps_number ?? '',
            survey_contractor_name: initial.survey_contractor_name ?? '',
            survey_contact_name: initial.survey_contact_name ?? '',
            survey_contact_phone: initial.survey_contact_phone ?? '',
            survey_contact_email: initial.survey_contact_email ?? '',
            scope_of_work_description: initial.scope_of_work_description ?? '',
            site_access_notes: initial.site_access_notes ?? '',
        });
    }, [initial, setData]);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-semibold">Table 2 — Survey Data</CardTitle>
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
                    <FormField label="Field Survey Date" error={form.errors.field_survey_date}>
                        <Input
                            type="date"
                            value={form.data.field_survey_date ? form.data.field_survey_date.slice(0, 10) : ''}
                            onChange={(e) => form.setData('field_survey_date', e.target.value)}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Fiber Provider"
                        error={form.errors.fiber_provider}
                        maxLength={SOW_FIELD_LIMITS.fiber_provider}
                        valueLength={(form.data.fiber_provider ?? '').length}
                    >
                        <Input
                            value={form.data.fiber_provider ?? ''}
                            onChange={(e) => form.setData('fiber_provider', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.fiber_provider}
                            disabled={readOnly}
                        />
                    </FormField>

                    <FormField
                        label="Provider Contact Name"
                        error={form.errors.provider_contact_name}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.provider_contact_name}
                        valueLength={(form.data.provider_contact_name ?? '').length}
                    >
                        <Input
                            value={form.data.provider_contact_name ?? ''}
                            onChange={(e) => form.setData('provider_contact_name', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.provider_contact_name}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Provider Contact Phone"
                        error={form.errors.provider_contact_phone}
                        maxLength={SOW_FIELD_LIMITS.provider_contact_phone}
                        valueLength={(form.data.provider_contact_phone ?? '').length}
                    >
                        <Input
                            value={form.data.provider_contact_phone ?? ''}
                            onChange={(e) => form.setData('provider_contact_phone', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.provider_contact_phone}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Provider Contact Email"
                        error={form.errors.provider_contact_email}
                        maxLength={SOW_FIELD_LIMITS.provider_contact_email}
                        valueLength={(form.data.provider_contact_email ?? '').length}
                    >
                        <Input
                            type="email"
                            value={form.data.provider_contact_email ?? ''}
                            onChange={(e) => form.setData('provider_contact_email', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.provider_contact_email}
                            disabled={readOnly}
                        />
                    </FormField>

                    <FormField
                        label="Demarcation"
                        error={form.errors.demarcation}
                        maxLength={SOW_FIELD_LIMITS.demarcation}
                        valueLength={(form.data.demarcation ?? '').length}
                    >
                        <Input
                            value={form.data.demarcation ?? ''}
                            onChange={(e) => form.setData('demarcation', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.demarcation}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="PS # (Hashtag)"
                        error={form.errors.ps_number}
                        maxLength={SOW_FIELD_LIMITS.ps_number}
                        valueLength={(form.data.ps_number ?? '').length}
                    >
                        <Input
                            value={form.data.ps_number ?? ''}
                            onChange={(e) => form.setData('ps_number', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.ps_number}
                            disabled={readOnly}
                        />
                    </FormField>

                    <FormField
                        label="Survey Contractor"
                        error={form.errors.survey_contractor_name}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.survey_contractor_name}
                        valueLength={(form.data.survey_contractor_name ?? '').length}
                    >
                        <Input
                            value={form.data.survey_contractor_name ?? ''}
                            onChange={(e) => form.setData('survey_contractor_name', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.survey_contractor_name}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Survey Contact Name"
                        error={form.errors.survey_contact_name}
                        maxLength={SOW_FIELD_LIMITS.survey_contact_name}
                        valueLength={(form.data.survey_contact_name ?? '').length}
                    >
                        <Input
                            value={form.data.survey_contact_name ?? ''}
                            onChange={(e) => form.setData('survey_contact_name', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.survey_contact_name}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Survey Contact Phone"
                        error={form.errors.survey_contact_phone}
                        maxLength={SOW_FIELD_LIMITS.survey_contact_phone}
                        valueLength={(form.data.survey_contact_phone ?? '').length}
                    >
                        <Input
                            value={form.data.survey_contact_phone ?? ''}
                            onChange={(e) => form.setData('survey_contact_phone', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.survey_contact_phone}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Survey Contact Email"
                        error={form.errors.survey_contact_email}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.survey_contact_email}
                        valueLength={(form.data.survey_contact_email ?? '').length}
                    >
                        <Input
                            type="email"
                            value={form.data.survey_contact_email ?? ''}
                            onChange={(e) => form.setData('survey_contact_email', e.target.value)}
                            maxLength={SOW_FIELD_LIMITS.survey_contact_email}
                            disabled={readOnly}
                        />
                    </FormField>

                    <FormField
                        label="Scope of Work Description"
                        error={form.errors.scope_of_work_description}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.scope_of_work_description}
                        valueLength={(form.data.scope_of_work_description ?? '').length}
                    >
                        <Textarea
                            value={form.data.scope_of_work_description ?? ''}
                            onChange={(e) =>
                                form.setData('scope_of_work_description', e.target.value)
                            }
                            rows={3}
                            maxLength={SOW_FIELD_LIMITS.scope_of_work_description}
                            disabled={readOnly}
                        />
                    </FormField>
                    <FormField
                        label="Site Access Notes"
                        error={form.errors.site_access_notes}
                        className="sm:col-span-2"
                        maxLength={SOW_FIELD_LIMITS.site_access_notes}
                        valueLength={(form.data.site_access_notes ?? '').length}
                    >
                        <Textarea
                            value={form.data.site_access_notes ?? ''}
                            onChange={(e) => form.setData('site_access_notes', e.target.value)}
                            rows={2}
                            maxLength={SOW_FIELD_LIMITS.site_access_notes}
                            disabled={readOnly}
                        />
                    </FormField>

                    {!readOnly && (
                        <div className="sm:col-span-2 flex justify-end">
                            <Button type="submit" disabled={form.processing} className="gap-1.5">
                                <Save className="size-3.5" />
                                {form.processing ? 'Saving…' : 'Save Survey Data'}
                            </Button>
                        </div>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}
