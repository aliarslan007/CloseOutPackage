import { ProjectFormValues } from '@/components/projects/project-form-types';
import { FormField } from '@/components/shared/form-field';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/react';
import * as React from 'react';

export interface ProjectTimelineFieldsProps {
    form: ReturnType<typeof useForm<ProjectFormValues>>;
}

/** Award + REC + desired completion are required at creation; the rest follow. */
const TIMELINE_FIELDS: Array<{
    key: keyof ProjectFormValues;
    label: string;
    required?: boolean;
}> = [
        { key: 'awarded_date', label: 'Date Awarded to Us', required: true },
        { key: 'rec_issued_date', label: 'Date REC Issued to Us', required: true },
        { key: 'completed_date', label: "Customer's Desired Completion", required: true },
        { key: 'construction_started_date', label: 'Date Construction Began' },
        { key: 'site_completed_date', label: 'Date Site Completed' },
    ];

export function ProjectTimelineFields({ form }: Readonly<ProjectTimelineFieldsProps>) {
    const setString = (key: keyof ProjectFormValues) => (e: React.ChangeEvent<HTMLInputElement>) =>
        form.setData(key, e.target.value);

    const errorFor = (key: keyof ProjectFormValues) =>
        form.errors[key as keyof typeof form.errors];

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {TIMELINE_FIELDS.map(({ key, label, required }) => {
                const name = key as string;
                return (
                    <FormField
                        key={name}
                        label={label}
                        htmlFor={name}
                        required={required}
                        error={errorFor(key)}
                    >
                        <Input
                            id={name}
                            name={name}
                            type="date"
                            value={(form.data[key] as string) ?? ''}
                            onChange={setString(key)}
                            required={required}
                            error={Boolean(errorFor(key))}
                        />
                    </FormField>
                );
            })}
            <div className="space-y-1 text-[11px] text-muted-foreground sm:col-span-2">
                <p>
                    Desired completion must be today or later. Every other date can be a real
                    date already past or a planned one in the future — only clearly wrong dates
                    (older than five years) are rejected.
                </p>
                <p>
                    The closeout package date is recorded automatically when the package is
                    approved.
                </p>
            </div>
        </div>
    );
}
