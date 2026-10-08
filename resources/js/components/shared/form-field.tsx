import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import * as React from 'react';

export interface FormFieldProps extends React.HTMLAttributes<HTMLDivElement> {
    label?: string;
    error?: string;
    helper?: string;
    required?: boolean;
    htmlFor?: string;
    /** Small adornment right of the label (e.g. an "auto-filled" badge). */
    badge?: React.ReactNode;
    /** Backend max length — renders a live "n / max" counter beside the label. */
    maxLength?: number;
    /** Current value length for the counter. */
    valueLength?: number;
    children: React.ReactNode;
}

export function FormField({
    label,
    error,
    helper,
    required = false,
    htmlFor,
    badge,
    maxLength,
    valueLength,
    className,
    children,
    ...props
}: Readonly<FormFieldProps>) {
    let feedback: React.ReactNode = null;

    if (error) {
        feedback = <p className="text-xs font-medium text-destructive">{error}</p>;
    } else if (helper) {
        feedback = <p className="text-xs text-muted-foreground">{helper}</p>;
    }

    return (
        <div className={cn('space-y-1.5', className)} {...props}>
            {label && (
                <div className="flex items-center justify-between gap-2">
                    <Label
                        htmlFor={htmlFor}
                        className="flex shrink-0 items-center gap-1 text-sm font-medium"
                    >
                        {label}
                        {required && <span className="text-destructive font-bold">*</span>}
                    </Label>
                    {(badge !== undefined || maxLength !== undefined) && (
                        <div className="ml-auto flex shrink-0 items-center gap-2">
                            {badge}
                            {maxLength !== undefined && (
                                <span className="shrink-0 text-[11px] text-muted-foreground tabular-nums">
                                    {valueLength ?? 0} / {maxLength}
                                </span>
                            )}
                        </div>
                    )}
                </div>
            )}
            {children}
            {feedback}
        </div>
    );
}

export default FormField;
