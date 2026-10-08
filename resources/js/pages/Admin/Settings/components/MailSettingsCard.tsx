import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import axios from 'axios';
import { KeyRound } from 'lucide-react';
import * as React from 'react';
import { BrevoSettingsFields } from './BrevoSettingsFields';
import { MailProviderPicker } from './MailProviderPicker';
import { SmtpSettingsFields } from './SmtpSettingsFields';
import { GMAIL_PRESET, type MailForm, type MailSettingsProps } from './mail-settings.types';

export type { MailSettingsProps };

export function MailSettingsCard({ settings, activeTab }: Readonly<{ settings: MailSettingsProps; activeTab: string }>) {
    const [form, setForm] = React.useState<MailForm>({
        provider: settings.provider,
        host: settings.smtp.host,
        port: String(settings.smtp.port),
        username: settings.smtp.username,
        password: '',
        encryption: settings.smtp.encryption,
        from_address: settings.smtp.from_address,
        from_name: settings.smtp.from_name,
        brevo_api_key: '',
    });
    const [saving, setSaving] = React.useState(false);

    const handleProviderChange = (provider: MailForm['provider']) => {
        setForm((prev) => {
            if (provider === 'smtp' && prev.host === '') {
                return { ...prev, provider, ...GMAIL_PRESET };
            }
            return { ...prev, provider };
        });
    };

    const handleUseGmailPreset = () => {
        setForm((prev) => ({ ...prev, ...GMAIL_PRESET }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        axios
            .post(route('admin.settings.mail.update'), {
                ...form,
                port: form.port === '' ? null : Number(form.port),
                password: form.password === '' ? null : form.password,
                brevo_api_key: form.brevo_api_key === '' ? null : form.brevo_api_key,
            })
            .then(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', activeTab);
                window.location.href = url.toString();
            })
            .finally(() => setSaving(false));
    };

    return (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div className="lg:col-span-8">
                <Card className="border-border shadow-xs">
                    <CardHeader className="pb-4">
                        <CardTitle className="text-sm font-semibold text-foreground">
                            Email Sending Settings
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Configure transactional email delivery (PDF packages, review notifications,
                            change orders). All dispatched emails will utilize the selected provider.
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent className="space-y-5">
                            <MailProviderPicker
                                value={form.provider}
                                onChange={handleProviderChange}
                            />

                            {form.provider === 'brevo' && (
                                <BrevoSettingsFields
                                    form={form}
                                    settings={settings}
                                    onChange={setForm}
                                />
                            )}

                            {form.provider === 'smtp' && (
                                <SmtpSettingsFields
                                    form={form}
                                    settings={settings}
                                    onChange={setForm}
                                    onUseGmailPreset={handleUseGmailPreset}
                                />
                            )}

                            {form.provider === 'env' && (
                                <p className="text-[11px] text-muted-foreground rounded-lg border bg-muted/30 p-4">
                                    Emails will be sent using the server default configuration (.env)
                                    such as SMTP, Mailgun, SES, Log, or other configured Laravel mailers.
                                </p>
                            )}

                            <div className="pt-2 flex justify-end">
                                <Button type="submit" loading={saving} className="px-5">
                                    <KeyRound className="size-3.5" />
                                    Save Email Settings
                                </Button>
                            </div>
                        </CardContent>
                    </form>
                </Card>
            </div>
        </div>
    );
}
