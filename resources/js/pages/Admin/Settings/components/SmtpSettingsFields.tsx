import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ExternalLink } from 'lucide-react';
import { GMAIL_PRESET, type MailForm, type MailSettingsProps } from './mail-settings.types';
import { GuideRef, SetupGuide } from './SetupGuide';

interface SmtpSettingsFieldsProps {
    form: MailForm;
    settings: MailSettingsProps;
    onChange: (updater: (prev: MailForm) => MailForm) => void;
    onUseGmailPreset: () => void;
}

export function SmtpSettingsFields({
    form,
    settings,
    onChange,
    onUseGmailPreset,
}: Readonly<SmtpSettingsFieldsProps>) {
    const isGmail = form.host === GMAIL_PRESET.host;

    return (
        <div className="space-y-4 rounded-lg border bg-muted/30 p-4">
            {isGmail && (
                <SetupGuide title="How to create a Gmail App Password">
                    <ol className="list-decimal space-y-1.5 pl-5 marker:font-semibold marker:text-foreground">
                        <li>
                            In your browser, make sure you are logged in with the
                            Gmail address you want this system to send from.
                        </li>
                        <li>
                            Turn on <GuideRef>2-Step Verification</GuideRef> in your
                            Google Account under <GuideRef>Security</GuideRef> — it is
                            required before App passwords appear.
                        </li>
                        <li>
                            Open{' '}
                            <a
                                href="https://myaccount.google.com/apppasswords"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                            >
                                App passwords <ExternalLink className="size-3" />
                            </a>
                            , enter an app name such as{' '}
                            <GuideRef>Close Out Package</GuideRef>, and create it.
                        </li>
                        <li>
                            Copy the 16-character password into the password field
                            below. Username and From email must be the same Gmail
                            address; From name is the display name recipients see.
                        </li>
                    </ol>
                </SetupGuide>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Mail Server (host)" htmlFor="mail-host">
                    <Input
                        id="mail-host"
                        value={form.host}
                        onChange={(e) => onChange((p) => ({ ...p, host: e.target.value }))}
                        placeholder="smtp.gmail.com"
                        maxLength={255}
                    />
                </FormField>
                <FormField label="Port" htmlFor="mail-port">
                    <Input
                        id="mail-port"
                        type="number"
                        min={1}
                        max={65535}
                        value={form.port}
                        onChange={(e) => onChange((p) => ({ ...p, port: e.target.value }))}
                        placeholder="587"
                    />
                </FormField>
            </div>

            {!isGmail && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormField label="Security" htmlFor="mail-encryption">
                        <select
                            id="mail-encryption"
                            value={form.encryption}
                            onChange={(e) =>
                                onChange((p) => ({ ...p, encryption: e.target.value }))
                            }
                            className="h-10 w-full rounded-md border border-input bg-card px-3 text-sm"
                        >
                            <option value="tls">TLS (recommended)</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                    </FormField>
                    <div className="flex items-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onUseGmailPreset}
                            className="h-10"
                        >
                            Use Gmail settings
                        </Button>
                    </div>
                </div>
            )}

            <FormField label="Username / Email" htmlFor="mail-username">
                <Input
                    id="mail-username"
                    value={form.username}
                    onChange={(e) => onChange((p) => ({ ...p, username: e.target.value }))}
                    placeholder="you@gmail.com"
                    maxLength={255}
                />
            </FormField>

            <FormField
                label={
                    settings.has_smtp_password
                        ? 'App Password (saved — type to replace)'
                        : 'App Password'
                }
                htmlFor="mail-password"
            >
                <Input
                    id="mail-password"
                    type="password"
                    value={form.password}
                    onChange={(e) => onChange((p) => ({ ...p, password: e.target.value }))}
                    placeholder={
                        settings.has_smtp_password
                            ? '•••••••• (saved)'
                            : '16-character app password'
                    }
                    maxLength={255}
                    autoComplete="new-password"
                />
            </FormField>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="From Email Address" htmlFor="mail-from-address">
                    <Input
                        id="mail-from-address"
                        type="email"
                        value={form.from_address}
                        onChange={(e) =>
                            onChange((p) => ({ ...p, from_address: e.target.value }))
                        }
                        placeholder="noreply@company.com"
                        maxLength={255}
                    />
                </FormField>
                <FormField label="From Name" htmlFor="mail-from-name">
                    <Input
                        id="mail-from-name"
                        value={form.from_name}
                        onChange={(e) => onChange((p) => ({ ...p, from_name: e.target.value }))}
                        placeholder="Close Out Package"
                        maxLength={255}
                    />
                </FormField>
            </div>
        </div>
    );
}
