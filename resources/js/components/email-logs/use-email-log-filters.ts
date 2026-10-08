import * as React from 'react';
import { router } from '@inertiajs/react';
import {
    EmailLogFilterState,
    EmailLogSort,
    EMAIL_SORT_DEFAULT,
} from '@/components/email-logs/email-log-labels';

export interface EmailLogFilterActions {
    filters: EmailLogFilterState;
    hasActiveFilters: boolean;
    push: (next: Partial<EmailLogFilterState>, page?: number) => void;
    updateSearch: (value: string) => void;
    updateDateRange: (dateFrom: string, dateTo: string) => void;
    updateCustomDate: (key: 'date_from' | 'date_to', value: string) => void;
    updateSort: (value: EmailLogSort) => void;
    clear: () => void;
}

export function useEmailLogFilters(
    routeName: string,
    serverFilters: Partial<EmailLogFilterState>
): EmailLogFilterActions {
    const [filters, setFilters] = React.useState<EmailLogFilterState>({
        search: serverFilters.search ?? '',
        date_from: serverFilters.date_from ?? '',
        date_to: serverFilters.date_to ?? '',
        sort: (serverFilters.sort as EmailLogSort | undefined) ?? EMAIL_SORT_DEFAULT,
    });

    const filtersRef = React.useRef(filters);
    filtersRef.current = filters;

    const push = React.useCallback(
        (next: Partial<EmailLogFilterState>, page?: number) => {
            const merged = { ...filtersRef.current, ...next };
            const params: Record<string, string> = {};

            if (merged.search) params.search = merged.search;
            if (merged.date_from) params.date_from = merged.date_from;
            if (merged.date_to) params.date_to = merged.date_to;
            if (merged.sort !== EMAIL_SORT_DEFAULT) params.sort = merged.sort;
            if (page !== undefined && page > 1) params.page = String(page);

            router.get(route(routeName), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [routeName]
    );

    const updateSearch = React.useCallback(
        (value: string) => {
            setFilters((prev) => ({ ...prev, search: value }));
            push({ search: value });
        },
        [push]
    );

    const updateDateRange = React.useCallback(
        (dateFrom: string, dateTo: string) => {
            setFilters((prev) => ({ ...prev, date_from: dateFrom, date_to: dateTo }));
            push({ date_from: dateFrom, date_to: dateTo });
        },
        [push]
    );

    const updateCustomDate = React.useCallback(
        (key: 'date_from' | 'date_to', value: string) => {
            setFilters((prev) => ({ ...prev, [key]: value }));
            push({ [key]: value });
        },
        [push]
    );

    const updateSort = React.useCallback(
        (value: EmailLogSort) => {
            setFilters((prev) => ({ ...prev, sort: value }));
            push({ sort: value });
        },
        [push]
    );

    const clear = React.useCallback(() => {
        setFilters({ search: '', date_from: '', date_to: '', sort: EMAIL_SORT_DEFAULT });
        push({ search: '', date_from: '', date_to: '', sort: EMAIL_SORT_DEFAULT });
    }, [push]);

    const hasActiveFilters =
        filters.search !== '' ||
        filters.date_from !== '' ||
        filters.date_to !== '' ||
        filters.sort !== EMAIL_SORT_DEFAULT;

    return { filters, hasActiveFilters, push, updateSearch, updateDateRange, updateCustomDate, updateSort, clear };
}
