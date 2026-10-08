import { Skeleton } from '@/components/ui/skeleton';

export function StatsBarSkeleton() {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-card p-4 shadow-xs">
            <div className="flex items-center gap-6">
                <Skeleton className="h-4 w-28" />
                <Skeleton className="h-4 w-36" />
                <Skeleton className="h-4 w-24" />
            </div>
            <div className="w-full sm:w-64">
                <Skeleton className="h-2.5 w-full rounded-full" />
            </div>
        </div>
    );
}
