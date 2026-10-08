import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';

export function MediaGridSkeleton({
    count = 6,
    isDense = false,
}: {
    count?: number;
    isDense?: boolean;
}) {
    const items = Array.from({ length: count }, (_, i) => i);

    return (
        <div
            className={cn(
                'grid transition-all',
                isDense
                    ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-3.5 gap-y-4'
                    : 'grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6 lg:gap-x-7 lg:gap-y-7',
            )}
        >
            {items.map((key) => (
                <div key={key} className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <Skeleton className={cn('w-full', isDense ? 'aspect-square' : 'aspect-[4/3]')} />
                    <div className={cn(isDense ? 'p-2.5 space-y-1.5' : 'p-3.5 sm:p-4 space-y-2')}>
                        <Skeleton className={cn('w-3/4', isDense ? 'h-3.5' : 'h-4')} />
                        <div className="flex justify-between">
                            <Skeleton className={cn('w-1/3', isDense ? 'h-3' : 'h-3.5')} />
                            <Skeleton className={cn('w-1/4', isDense ? 'h-3' : 'h-3.5')} />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
