import type { CustomerItem, EngineerItem } from './customer-hierarchy-types';

export interface EngineerForm {
    customer_id: number;
    market_id: number | '';
    first_name: string;
    last_name: string;
    company: string;
    email: string;
    phone: string;
    title: string;
    password: string;
    password_confirmation: string;
    is_default: boolean;
    is_active: boolean;
    profile_photo: File | null;
}

/** The engineer row stores one full name; the form collects it as two fields. */
export function splitName(fullName: string): { first: string; last: string } {
    const [first, ...rest] = fullName.trim().split(/\s+/);

    return { first: first ?? '', last: rest.join(' ') };
}

/**
 * The values this dialog should always open with. Applied on mount and again
 * on every open — the dialog stays mounted between openings, so without the
 * re-seed the previous customer/region (and everything typed for it) would be
 * submitted against the next engineer.
 */
export function initialEngineerValues(
    customer: CustomerItem | null,
    engineer: EngineerItem | null,
    defaultMarketId: number | null
): EngineerForm {
    const { first, last } = engineer ? splitName(engineer.name) : { first: '', last: '' };

    return {
        customer_id: customer?.id ?? 0,
        market_id: engineer?.market_id ?? defaultMarketId ?? '',
        first_name: first,
        last_name: last,
        // New engineers default to the customer company they belong to; on
        // edit a blank field means "leave the account's company alone".
        company: engineer ? '' : (customer?.name ?? ''),
        email: engineer?.email ?? '',
        phone: engineer?.phone ?? '',
        title: engineer?.title ?? '',
        password: '',
        password_confirmation: '',
        is_default: engineer?.is_default ?? false,
        is_active: engineer?.is_active ?? true,
        profile_photo: null,
    };
}
