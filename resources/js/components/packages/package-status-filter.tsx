import { PACKAGE_STATUS_OPTIONS } from '@/lib/package-statuses';
import { cn } from '@/lib/utils';

export interface PackageStatusFilterProps {
    value: string | null;
    onChange: (value: string) => void;
    className?: string;
}

export function PackageStatusFilter({
    value,
    onChange,
    className,
}: Readonly<PackageStatusFilterProps>) {
    return (
        <select
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className={cn(
                'h-9 rounded-md border border-input bg-card px-3 pr-8 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                className
            )}
            aria-label="Filter packages by status"
        >
            {PACKAGE_STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

export default PackageStatusFilter;
