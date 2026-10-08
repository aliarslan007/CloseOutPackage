import * as React from 'react';
import { cn } from '@/lib/utils';

const LINK_CLASSES =
    'text-blue-600 dark:text-blue-400 underline underline-offset-2 hover:text-blue-800 dark:hover:text-blue-300 transition-colors break-all';

export interface EmailLinkProps {
    email: string;
    className?: string;
}

export function EmailLink({ email, className }: Readonly<EmailLinkProps>) {
    return (
        <a
            href={`mailto:${email}`}
            target="_blank"
            rel="noreferrer"
            className={cn(LINK_CLASSES, className)}
        >
            {email}
        </a>
    );
}

export interface PhoneLinkProps {
    phone: string;
    className?: string;
}

export function PhoneLink({ phone, className }: Readonly<PhoneLinkProps>) {
    return (
        <a href={`tel:${phone}`} className={cn(LINK_CLASSES, className)}>
            {phone}
        </a>
    );
}
