export interface PackageStatusOption {
    value: string;
    label: string;
}

export const PACKAGE_STATUS_OPTIONS: PackageStatusOption[] = [
    { value: '', label: 'All packages' },
    { value: 'draft', label: 'Draft' },
    { value: 'awaiting_review', label: 'Awaiting review' },
    { value: 'submitted', label: 'Submitted for review' },
    { value: 'under_review', label: 'In review' },
    { value: 'approved', label: 'Approved' },
];

export function packageStatusLabel(status: string | null | undefined): string {
    return (
        PACKAGE_STATUS_OPTIONS.find((option) => option.value === (status ?? ''))?.label ??
        'All packages'
    );
}
