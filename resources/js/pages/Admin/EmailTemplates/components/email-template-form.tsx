import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    EMAIL_TEMPLATE_STATUS_LABELS,
    type DefaultEmailTemplatesMap,
    type EmailTemplateItem,
    type EmailTemplateStatusValue,
    type EmailTemplateVariables,
    type EmailTypeOption,
} from '@/pages/Admin/EmailTemplates/components/email-template-types';
import { TokenChipBar } from '@/pages/Admin/EmailTemplates/components/token-chip-bar';
import { useEmailTemplateEditor } from '@/pages/Admin/EmailTemplates/components/use-email-template-editor';
import { Eye, EyeOff, Loader2, RotateCcw, Save } from 'lucide-react';
import { type ReactNode } from 'react';

interface EmailTemplateFormProps {
    readonly template: EmailTemplateItem | null;
    readonly emailTypes: ReadonlyArray<EmailTypeOption>;
    readonly variables: EmailTemplateVariables;
    readonly defaultTemplates?: DefaultEmailTemplatesMap;
}

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-card px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export function EmailTemplateForm({
    template,
    emailTypes,
    variables,
    defaultTemplates = {},
}: Readonly<EmailTemplateFormProps>) {
    const {
        form,
        preview,
        setPreview,
        previewing,
        focusedField,
        setFocusedField,
        subjectRef,
        bodyRef,
        insertToken,
        resetToDefault,
        handleTypeChange,
        runPreview,
        submit,
    } = useEmailTemplateEditor({ template, emailTypes, defaultTemplates });

    const tokens = variables[form.data.email_type] ?? [];
    const hasDefaultWording = Boolean(defaultTemplates[form.data.email_type]);
    const previewShown = preview !== null;

    // A single indicator: while loading the spinner replaces the eye icon
    // (passing `loading` to the Button *and* rendering a spinner showed two).
    let previewIcon: ReactNode = <Eye className="size-3.5" />;
    if (previewing) {
        previewIcon = <Loader2 className="size-3.5 animate-spin" />;
    } else if (previewShown) {
        previewIcon = <EyeOff className="size-3.5" />;
    }

    const togglePreview = () => {
        if (previewing) {
            return;
        }

        if (previewShown) {
            setPreview(null);
            return;
        }

        runPreview();
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            className="space-y-5"
        >
            {/* ── Details ─────────────────────────────────────────────── */}
            <Card>
                <CardHeader className="pb-4">
                    <CardTitle className="text-sm font-bold">Template Details</CardTitle>
                    <CardDescription className="text-xs">
                        Which email this template powers, what it is called, and whether it is live.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="template-type" className="text-xs font-medium text-foreground">
                                Email type
                            </Label>
                            <select
                                id="template-type"
                                value={form.data.email_type}
                                onChange={(event) => handleTypeChange(event.target.value)}
                                className={SELECT_CLASS}
                            >
                                {emailTypes.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <p className="text-[11px] text-muted-foreground">
                                The event that triggers this email.
                            </p>
                            {form.errors.email_type && (
                                <p className="text-[11px] text-destructive">{form.errors.email_type}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="template-name" className="text-xs font-medium text-foreground">
                                Name
                            </Label>
                            <Input
                                id="template-name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                placeholder="Final COP — Standard"
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Only used to identify it in this library.
                            </p>
                            {form.errors.name && (
                                <p className="text-[11px] text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="template-status" className="text-xs font-medium text-foreground">
                                Status
                            </Label>
                            <select
                                id="template-status"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target.value as EmailTemplateStatusValue
                                    )
                                }
                                className={SELECT_CLASS}
                            >
                                {(
                                    Object.keys(EMAIL_TEMPLATE_STATUS_LABELS) as EmailTemplateStatusValue[]
                                ).map((status) => (
                                    <option key={status} value={status}>
                                        {EMAIL_TEMPLATE_STATUS_LABELS[status]}
                                    </option>
                                ))}
                            </select>
                            <p className="text-[11px] text-muted-foreground">
                                Only <span className="font-medium text-foreground">Active</span>{' '}
                                templates can be sent.
                            </p>
                        </div>
                    </div>

                    <label className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border/70 bg-muted/20 p-3 transition-colors hover:bg-muted/40">
                        <input
                            type="checkbox"
                            checked={form.data.is_default}
                            onChange={(event) => form.setData('is_default', event.target.checked)}
                            className="mt-0.5 size-4 rounded border-input"
                        />
                        <span className="space-y-0.5">
                            <span className="block text-xs font-semibold text-foreground">
                                Default template for this email type
                            </span>
                            <span className="block text-[11px] text-muted-foreground">
                                The system uses this automatically when it sends this type of email.
                            </span>
                        </span>
                    </label>
                </CardContent>
            </Card>

            {/* ── Content ─────────────────────────────────────────────── */}
            <Card>
                <CardHeader className="pb-4">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="space-y-1.5">
                            <CardTitle className="text-sm font-bold">Email Content</CardTitle>
                            <CardDescription className="text-xs">
                                The subject line and message. Insert tokens to pull in live details.
                            </CardDescription>
                        </div>
                        {hasDefaultWording && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={resetToDefault}
                                className="h-7 gap-1.5 px-2 text-[11px]"
                                title="Restore the system default subject and body for this email type"
                            >
                                <RotateCcw className="size-3" />
                                Reset to Default Wording
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="template-subject" className="text-xs font-medium text-foreground">
                            Subject{' '}
                            {focusedField === 'subject' && (
                                <span className="font-normal text-primary">(token target)</span>
                            )}
                        </Label>
                        <Input
                            id="template-subject"
                            ref={subjectRef}
                            value={form.data.subject_template}
                            onFocus={() => setFocusedField('subject')}
                            onChange={(event) => form.setData('subject_template', event.target.value)}
                            maxLength={500}
                        />
                        {form.errors.subject_template && (
                            <p className="text-[11px] text-destructive">
                                {form.errors.subject_template}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="template-body" className="text-xs font-medium text-foreground">
                                Body{' '}
                                {focusedField === 'body' && (
                                    <span className="font-normal text-primary">(token target)</span>
                                )}
                            </Label>
                            <span className="text-[10px] text-muted-foreground">
                                Click a token to insert it into the {focusedField}
                            </span>
                        </div>
                        <TokenChipBar tokens={tokens} onInsert={insertToken} />
                        <Textarea
                            id="template-body"
                            ref={bodyRef}
                            rows={12}
                            value={form.data.body_template}
                            onFocus={() => setFocusedField('body')}
                            onChange={(event) => form.setData('body_template', event.target.value)}
                            className="whitespace-pre-wrap font-mono text-xs"
                        />
                        {form.errors.body_template && (
                            <p className="text-[11px] text-destructive">{form.errors.body_template}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── Preview ─────────────────────────────────────────────── */}
            {preview !== null && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-bold">Preview</CardTitle>
                        <CardDescription className="text-xs">
                            Tokens resolve against live data when the email is actually sent — this
                            is a sample rendering.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <p className="text-sm font-semibold text-foreground">{preview.subject}</p>
                        <p className="whitespace-pre-wrap rounded-lg border border-border/70 bg-muted/30 p-3 text-xs text-foreground">
                            {preview.body}
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* ── Actions ─────────────────────────────────────────────── */}
            <div className="flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={togglePreview}
                    disabled={previewing}
                >
                    {previewIcon}
                    {previewShown ? 'Hide Preview' : 'Preview'}
                </Button>
                <Button type="submit" size="sm" loading={form.processing}>
                    <Save className="size-3.5" />
                    {template === null ? 'Create Template' : 'Save Changes'}
                </Button>
            </div>
        </form>
    );
}

export default EmailTemplateForm;
