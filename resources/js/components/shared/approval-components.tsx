import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { AlertTriangle, CheckCircle2, Clock, XCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';

export interface ReadinessRule {
    key: string;
    label: string;
    passed: boolean;
    details?: string;
}

export interface ReadinessCheckPanelProps {
    rules: ReadinessRule[];
    onFinalApprove?: () => void;
    canApprove?: boolean;
    className?: string;
}

export function ReadinessCheckPanel({
    rules,
    onFinalApprove,
    canApprove = true,
    className,
}: Readonly<ReadinessCheckPanelProps>) {
    const allPassed = rules.every((r) => r.passed);

    return (
        <Card className={cn('overflow-hidden border-border', className)}>
            <CardHeader className="bg-muted/40 pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-bold">
                        Package Approval Readiness
                    </CardTitle>
                    <span
                        className={cn(
                            'text-xs font-semibold px-2.5 py-1 rounded-full',
                            allPassed
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                        )}
                    >
                        {allPassed ? 'Ready for Approval' : 'Action Required'}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="p-4 space-y-3">
                <div className="space-y-2">
                    {rules.map((rule) => (
                        <div
                            key={rule.key}
                            className="flex items-start justify-between text-xs py-1 border-b last:border-0 border-border"
                        >
                            <div className="flex items-start gap-2">
                                {rule.passed ? (
                                    <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                                ) : (
                                    <XCircle className="size-4 text-destructive shrink-0 mt-0.5" />
                                )}
                                <div>
                                    <span className="font-medium text-foreground">
                                        {rule.label}
                                    </span>
                                    {rule.details && (
                                        <p className="text-[11px] text-muted-foreground">
                                            {rule.details}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {onFinalApprove && (
                    <Button
                        className="w-full mt-2"
                        variant="success"
                        disabled={!allPassed || !canApprove}
                        onClick={onFinalApprove}
                    >
                        Approve Package & Lock Final PDF
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

export interface ReplacementRequestFormProps {
    onSubmit: (reason: string) => void;
    onCancel: () => void;
    loading?: boolean;
}

export function ReplacementRequestForm({
    onSubmit,
    onCancel,
    loading = false,
}: Readonly<ReplacementRequestFormProps>) {
    const [reason, setReason] = useState('');

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (reason.trim()) {
            onSubmit(reason.trim());
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4 pt-2">
            <div className="space-y-1.5">
                <label
                    htmlFor="replacement-reason"
                    className="text-xs font-semibold text-foreground"
                >
                    Reason for Replacement Request <span className="text-destructive">*</span>
                </label>
                <Textarea
                    id="replacement-reason"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    placeholder="Describe why this photo must be replaced (e.g. Blurry photo, incorrect angle)..."
                    className="min-h-[100px] text-xs"
                    required
                />
            </div>
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onCancel}
                    disabled={loading}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    size="sm"
                    loading={loading}
                    disabled={!reason.trim()}
                >
                    Request Replacement
                </Button>
            </div>
        </form>
    );
}

export function ApprovalStatusIcon({ status }: Readonly<{ status: string }>) {
    switch (status.toLowerCase()) {
        case 'approved':
            return <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />;
        case 'rejected':
            return <XCircle className="size-5 text-destructive" />;
        case 'flagged':
            return <AlertTriangle className="size-5 text-amber-500" />;
        case 'pending':
        default:
            return <Clock className="size-5 text-muted-foreground" />;
    }
}
