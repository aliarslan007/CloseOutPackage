import { EMAIL_TEMPLATE_PREVIEW_ROUTE } from '@/components/email/email-types';
import {
    type DefaultEmailTemplatesMap,
    type EmailTemplateItem,
    type EmailTemplateStatusValue,
    type EmailTypeOption,
} from '@/pages/Admin/EmailTemplates/components/email-template-types';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useRef, useState } from 'react';

export interface TemplateFormData {
    email_type: string;
    name: string;
    subject_template: string;
    body_template: string;
    status: EmailTemplateStatusValue;
    is_default: boolean;
}

export interface PreviewPanel {
    readonly subject: string;
    readonly body: string;
}

interface UseEmailTemplateEditorOptions {
    readonly template: EmailTemplateItem | null;
    readonly emailTypes: ReadonlyArray<EmailTypeOption>;
    readonly defaultTemplates?: DefaultEmailTemplatesMap;
}

export function useEmailTemplateEditor({
    template,
    emailTypes,
    defaultTemplates = {},
}: Readonly<UseEmailTemplateEditorOptions>) {
    const initialType = template?.email_type ?? emailTypes[0]?.value ?? 'final_pdf_email';
    const fallbackDefault = defaultTemplates[initialType];

    const form = useForm<TemplateFormData>({
        email_type: initialType,
        name: template?.name ?? fallbackDefault?.name ?? '',
        subject_template: template?.subject_template ?? fallbackDefault?.subject ?? '',
        body_template: template?.body_template ?? fallbackDefault?.body ?? '',
        status: template?.status ?? 'active',
        is_default: template?.is_default ?? false,
    });

    const [preview, setPreview] = useState<PreviewPanel | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [focusedField, setFocusedField] = useState<'subject' | 'body'>('body');

    const subjectRef = useRef<HTMLInputElement | null>(null);
    const bodyRef = useRef<HTMLTextAreaElement | null>(null);

    const insertToken = useCallback(
        (token: string): void => {
            const snippet = `{{${token}}}`;

            if (focusedField === 'subject') {
                const el = subjectRef.current;
                const value = form.data.subject_template;
                if (el === null) {
                    form.setData('subject_template', value + snippet);

                    return;
                }
                const start = el.selectionStart ?? value.length;
                const end = el.selectionEnd ?? start;
                form.setData('subject_template', value.slice(0, start) + snippet + value.slice(end));
                globalThis.requestAnimationFrame(() => {
                    el.focus();
                    el.selectionStart = start + snippet.length;
                    el.selectionEnd = start + snippet.length;
                });

                return;
            }

            const el = bodyRef.current;
            const value = form.data.body_template;
            if (el === null) {
                form.setData('body_template', value + snippet);

                return;
            }
            const start = el.selectionStart ?? value.length;
            const end = el.selectionEnd ?? start;
            form.setData('body_template', value.slice(0, start) + snippet + value.slice(end));
            globalThis.requestAnimationFrame(() => {
                el.focus();
                el.selectionStart = start + snippet.length;
                el.selectionEnd = start + snippet.length;
            });
        },
        [focusedField, form],
    );

    const resetToDefault = useCallback((): void => {
        const standard = defaultTemplates[form.data.email_type];
        if (!standard) {
            return;
        }

        form.setData((prev) => ({
            ...prev,
            name: prev.name.trim() === '' ? standard.name : prev.name,
            subject_template: standard.subject,
            body_template: standard.body,
        }));
    }, [defaultTemplates, form]);

    const handleTypeChange = useCallback(
        (newType: string): void => {
            const standard = defaultTemplates[newType];
            form.setData((prev) => {
                const isNewAndEmpty =
                    template === null &&
                    prev.subject_template.trim() === '' &&
                    prev.body_template.trim() === '';

                return {
                    ...prev,
                    email_type: newType,
                    name: isNewAndEmpty && standard ? standard.name : prev.name,
                    subject_template: isNewAndEmpty && standard ? standard.subject : prev.subject_template,
                    body_template: isNewAndEmpty && standard ? standard.body : prev.body_template,
                };
            });
        },
        [defaultTemplates, form, template],
    );

    const runPreview = useCallback((): void => {
        setPreviewing(true);
        axios
            .post<{ subject: string; body: string }>(route(EMAIL_TEMPLATE_PREVIEW_ROUTE), {
                email_type: form.data.email_type,
                subject_template: form.data.subject_template,
                body_template: form.data.body_template,
            })
            .then((response) => setPreview(response.data))
            .catch(() => setPreview(null))
            .finally(() => setPreviewing(false));
    }, [form.data.body_template, form.data.email_type, form.data.subject_template]);

    const submit = useCallback((): void => {
        if (template === null) {
            form.post(route('admin.email-templates.store'), { preserveScroll: true });
        } else {
            form.put(route('admin.email-templates.update', template.id), { preserveScroll: true });
        }
    }, [form, template]);

    return {
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
    };
}
