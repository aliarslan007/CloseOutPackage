import { cn } from '@/lib/utils';
import { Cloud, MailCheck, Server } from 'lucide-react';
import type { MailForm } from './mail-settings.types';

interface MailProviderPickerProps {
    value: MailForm['provider'];
    onChange: (provider: MailForm['provider']) => void;
}

const PROVIDERS: Array<{
    value: MailForm['provider'];
    label: string;
    hint: string;
    icon: typeof Server;
}> = [
    {
        value: 'env',
        label: 'Server Default',
        hint: 'Uses the mail settings (.env) already configured on this server.',
        icon: Server,
    },
    {
        value: 'brevo',
        label: 'Brevo',
        hint: 'Send transactional emails via Brevo REST API using your API key.',
        icon: Cloud,
    },
    {
        value: 'smtp',
        label: 'Gmail / SMTP',
        hint: 'Send through Gmail App Password or custom SMTP server.',
        icon: MailCheck,
    },
];

export function MailProviderPicker({ value, onChange }: Readonly<MailProviderPickerProps>) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
            {PROVIDERS.map((option) => {
                const Icon = option.icon;
                const isSelected = value === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'rounded-lg border p-3 text-left transition-all cursor-pointer',
                            isSelected
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                : 'border-border hover:border-primary/40 hover:bg-muted/40'
                        )}
                    >
                        <span className="flex items-center gap-2 text-xs font-semibold">
                            <Icon className="size-4" />
                            {option.label}
                        </span>
                        <span className="mt-1 block text-[11px] leading-snug text-muted-foreground">
                            {option.hint}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
