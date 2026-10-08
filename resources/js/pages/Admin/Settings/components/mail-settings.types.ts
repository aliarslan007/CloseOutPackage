export interface MailSettingsProps {
    provider: 'env' | 'brevo' | 'smtp';
    smtp: {
        host: string;
        port: number;
        username: string;
        encryption: string;
        from_address: string;
        from_name: string;
    };
    has_smtp_password: boolean;
    has_brevo_api_key: boolean;
}

export interface MailForm {
    provider: 'env' | 'brevo' | 'smtp';
    host: string;
    port: string;
    username: string;
    password: string;
    encryption: string;
    from_address: string;
    from_name: string;
    brevo_api_key: string;
}

export const GMAIL_PRESET = {
    host: 'smtp.gmail.com',
    port: '587',
    encryption: 'tls',
} as const;
