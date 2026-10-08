import { FormField } from '@/components/shared/form-field';
import { Input } from '@/components/ui/input';
import { ExternalLink } from 'lucide-react';
import type { MailForm, MailSettingsProps } from './mail-settings.types';
import { GuideRef, SetupGuide } from './SetupGuide';

interface BrevoSettingsFieldsProps {
    form: MailForm;
    settings: MailSettingsProps;
    onChange: (updater: (prev: MailForm) => MailForm) => void;
}

export function BrevoSettingsFields({
    form,
    settings,
    onChange,
}: Readonly<BrevoSettingsFieldsProps>) {
    return (
        <div className="space-y-4 rounded-lg border bg-muted/30 p-4">
            <SetupGuide title="How to get a Brevo API key">
                <ol className="list-decimal space-y-1.5 pl-5 marker:font-semibold marker:text-foreground">
                    <li>
                        Open{' '}
                        <a
                            href="https://app.brevo.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                        >
                            Brevo <ExternalLink className="size-3" />
                        </a>{' '}
                        and log in — or sign up first. Use the email address you want
                        this system to send emails from.
                    </li>
                    <li>
                        Click your profile (top right) and open{' '}
                        <GuideRef>Settings</GuideRef>.
                    </li>
                    <li>
                        In the settings sidebar, open <GuideRef>SMTP &amp; API</GuideRef>{' '}
                        and generate a new API key under API keys.
                    </li>
                    <li>
                        Copy the key and paste it below. Make sure the sender address
                        you enter is verified in your Brevo account.
                    </li>
                </ol>
            </SetupGuide>

            <FormField
                label="Brevo API Key"
                htmlFor="mail-brevo-key"
                helper="Paste the key you generated in Brevo."
            >
                <Input
                    id="mail-brevo-key"
                    type="password"
                    value={form.brevo_api_key}
                    onChange={(e) =>
                        onChange((p) => ({ ...p, brevo_api_key: e.target.value }))
                    }
                    placeholder={
                        settings.has_brevo_api_key
                            ? '•••••••• (saved — type to replace)'
                            : 'xkeysib-…'
                    }
                    maxLength={255}
                    autoComplete="new-password"
                />
            </FormField>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="From Email Address" htmlFor="brevo-from-address">
                    <Input
                        id="brevo-from-address"
                        type="email"
                        value={form.from_address}
                        onChange={(e) =>
                            onChange((p) => ({ ...p, from_address: e.target.value }))
                        }
                        placeholder="noreply@company.com"
                        maxLength={255}
                    />
                </FormField>
                <FormField label="From Name" htmlFor="brevo-from-name">
                    <Input
                        id="brevo-from-name"
                        value={form.from_name}
                        onChange={(e) =>
                            onChange((p) => ({ ...p, from_name: e.target.value }))
                        }
                        placeholder="Close Out Package"
                        maxLength={255}
                    />
                </FormField>
            </div>
        </div>
    );
}
