import * as React from 'react';
import { Button } from '@/components/ui/button';
import { SearchInput } from '@/components/shared/search-input';
import {
    DatePreset,
    EmailLogSort,
    EMAIL_SORT_OPTIONS,
    datePresetLabel,
    datePresetRange,
    resolveDatePreset,
} from '@/components/email-logs/email-log-labels';
import { EmailLogFilterActions } from '@/components/email-logs/use-email-log-filters';
import { CalendarDays, X } from 'lucide-react';

interface EmailLogFiltersProps {
    actions: EmailLogFilterActions;
}

const SELECT_CLASS =
    'h-8.5 rounded-md border border-input bg-card px-2.5 pr-7 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0';
const DATE_INPUT_CLASS =
    'h-8.5 rounded-md border border-input bg-card px-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0';

const DATE_PRESET_KEYS: DatePreset[] = ['all', 'yesterday', 'last_7_days', 'this_month', 'custom'];

export function EmailLogFilters({ actions }: EmailLogFiltersProps) {
    const { filters, hasActiveFilters, updateSearch, updateDateRange, updateCustomDate, updateSort, clear } = actions;

    // Keep the user's explicit preset choice (e.g. "Custom range") even while
    // the date inputs are empty — the derived preset alone would snap back to "All dates".
    const [presetOverride, setPresetOverride] = React.useState<DatePreset | null>(null);
    const preset = presetOverride ?? resolveDatePreset(filters.date_from, filters.date_to);

    const handlePresetChange = (value: DatePreset) => {
        setPresetOverride(value);

        if (value === 'all' || value === 'custom') {
            updateDateRange('', '');
            return;
        }

        const range = datePresetRange(value);
        updateDateRange(range.date_from, range.date_to);
    };

    const handleCustomDate = (key: 'date_from' | 'date_to', value: string) => {
        setPresetOverride('custom');
        updateCustomDate(key, value);
    };

    const handleClear = () => {
        setPresetOverride(null);
        clear();
    };

    return (
        <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <SearchInput
                value={filters.search}
                onSearchChange={updateSearch}
                placeholder="Search recipient, sender, or subject..."
                className="w-48 sm:w-56 lg:w-64 shrink-0"
            />

            <div className="flex items-center gap-1.5 shrink-0">
                <CalendarDays className="size-4 text-muted-foreground shrink-0" />
                <select
                    value={preset}
                    onChange={(e) => handlePresetChange(e.target.value as DatePreset)}
                    className={SELECT_CLASS}
                    aria-label="Date range"
                >
                    {DATE_PRESET_KEYS.map((key) => (
                        <option key={key} value={key}>
                            {datePresetLabel(key)}
                        </option>
                    ))}
                </select>
            </div>

            {preset === 'custom' && (
                <div className="flex items-center gap-1.5 shrink-0">
                    <input
                        type="date"
                        value={filters.date_from}
                        onChange={(e) => handleCustomDate('date_from', e.target.value)}
                        className={DATE_INPUT_CLASS}
                        aria-label="From date"
                    />
                    <span className="text-xs text-muted-foreground">to</span>
                    <input
                        type="date"
                        value={filters.date_to}
                        onChange={(e) => handleCustomDate('date_to', e.target.value)}
                        className={DATE_INPUT_CLASS}
                        aria-label="To date"
                    />
                </div>
            )}

            <select
                value={filters.sort}
                onChange={(e) => updateSort(e.target.value as EmailLogSort)}
                className={SELECT_CLASS}
                aria-label="Sort order"
            >
                {EMAIL_SORT_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            {hasActiveFilters && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleClear}
                    className="h-8.5 px-2 gap-1 text-xs text-muted-foreground hover:text-foreground shrink-0"
                >
                    <X className="size-3" /> Clear
                </Button>
            )}
        </div>
    );
}
