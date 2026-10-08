const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'] as const;

export function formatFileSize(bytes: number): string {
    if (bytes <= 0) {
        return '0 B';
    }

    const unitIndex = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        UNITS.length - 1,
    );

    const unit = UNITS[unitIndex];
    const value = bytes / Math.pow(1024, unitIndex);

    const precision = unitIndex === 0 ? 0 : 1;
    return `${value.toFixed(precision)} ${unit}`;
}
