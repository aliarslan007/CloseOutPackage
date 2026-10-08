import { SearchInput } from '@/components/shared/search-input';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import * as React from 'react';

export interface CustomerEmailFilterState {
    search?: string;
    status?: string;
    type?: string;
    project_id?: string;
}

interface CustomerEmailFiltersProps {
    filters: CustomerEmailFilterState;
    projects: Array<{ id: number; name: string }>;
    onFilterChange: (key: keyof CustomerEmailFilterState, value: string | undefined) => void;
    onReset: () => void;
}

const SELECT_CLASS =
    'h-10 rounded-md border border-input bg-card px-2.5 pr-7 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All statuses' },
    { value: 'ready', label: 'Not sent' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'pending', label: 'Sending' },
    { value: 'sent', label: 'Sent' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
];

/**
 * The customer-facing email categories this outbox tracks. Single source of
 * truth for both the type filter and the header legend.
 */
export const CUSTOMER_EMAIL_TYPE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'change_order', label: 'Change Order' },
    { value: 'final_pdf_email', label: 'Final PDF Package' },
    { value: 'progress_pdf', label: 'Progress PDF Package' },
    { value: 'customer_review_opened', label: 'Customer Review' },
    { value: 'revision_ready', label: 'Revision Ready' },
    { value: 'issue_forward', label: 'Issue Forward' },
];

const TYPE_OPTIONS = [{ value: 'all', label: 'All types' }, ...CUSTOMER_EMAIL_TYPE_OPTIONS];

export function CustomerEmailFilters({
    filters,
    projects,
    onFilterChange,
    onReset,
}: Readonly<CustomerEmailFiltersProps>) {
    const hasActiveFilters = Boolean(
        filters.search ||
        (filters.status && filters.status !== 'all') ||
        (filters.type && filters.type !== 'all') ||
        (filters.project_id && filters.project_id !== 'all')
    );

    return (
        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-1 flex-wrap items-center gap-2">
                <div className="w-full sm:w-64">
                    <SearchInput
                        value={filters.search ?? ''}
                        onSearchChange={(value) => onFilterChange('search', value.trim() || undefined)}
                        placeholder="Search recipient or subject..."
                        className="h-10 text-xs"
                    />
                </div>

                <select
                    value={filters.status ?? 'all'}
                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                        const val = e.target.value;
                        onFilterChange('status', val === 'all' ? undefined : val);
                    }}
                    className={SELECT_CLASS}
                    aria-label="Filter by status"
                >
                    {STATUS_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>

                <select
                    value={filters.type ?? 'all'}
                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                        const val = e.target.value;
                        onFilterChange('type', val === 'all' ? undefined : val);
                    }}
                    className={SELECT_CLASS}
                    aria-label="Filter by type"
                >
                    {TYPE_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>

                {projects.length > 0 && (
                    <select
                        value={filters.project_id ?? 'all'}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                            const val = e.target.value;
                            onFilterChange('project_id', val === 'all' ? undefined : val);
                        }}
                        className={SELECT_CLASS}
                        aria-label="Filter by project"
                    >
                        <option value="all">All projects</option>
                        {projects.map((proj) => (
                            <option key={proj.id} value={String(proj.id)}>
                                {proj.name}
                            </option>
                        ))}
                    </select>
                )}

                {hasActiveFilters && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onReset}
                        className="h-10 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <X className="mr-1 size-3.5" />
                        Reset
                    </Button>
                )}
            </div>
        </div>
    );
}
