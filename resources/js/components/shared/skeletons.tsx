import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function TableSkeleton({ rows = 5, cols = 4 }: Readonly<{ rows?: number; cols?: number }>) {
    return (
        <div className="w-full space-y-3">
            <div className="flex justify-between items-center pb-2">
                <Skeleton className="h-9 w-48" />
                <Skeleton className="h-9 w-32" />
            </div>
            <div className="rounded-xl border p-4 space-y-4">
                <div className="flex gap-4">
                    {Array.from({ length: cols }).map((_, c) => (
                        <Skeleton key={c} className="h-6 flex-1" />
                    ))}
                </div>
                {Array.from({ length: rows }).map((_, r) => (
                    <div key={r} className="flex gap-4 pt-2">
                        {Array.from({ length: cols }).map((_, c) => (
                            <Skeleton key={c} className="h-8 flex-1" />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

export function CardGridSkeleton({ count = 6 }: Readonly<{ count?: number }>) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {Array.from({ length: count }).map((_, i) => (
                <Card key={i} className="p-4 space-y-3">
                    <Skeleton className="h-6 w-3/4" />
                    <Skeleton className="h-4 w-1/2" />
                    <Skeleton className="h-20 w-full" />
                </Card>
            ))}
        </div>
    );
}

export function FormSkeleton() {
    return (
        <div className="space-y-6 max-w-xl">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-10 w-full" />
            </div>
            <div className="space-y-2">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-10 w-full" />
            </div>
            <div className="space-y-2">
                <Skeleton className="h-4 w-20" />
                <Skeleton className="h-24 w-full" />
            </div>
            <Skeleton className="h-10 w-32" />
        </div>
    );
}

export function DashboardSkeleton() {
    return (
        <div className="space-y-8 py-6">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i} className="p-4 space-y-2">
                        <Skeleton className="h-4 w-20" />
                        <Skeleton className="h-8 w-16" />
                    </Card>
                ))}
            </div>
            <Card className="p-6 space-y-4">
                <Skeleton className="h-6 w-48" />
                <Skeleton className="h-40 w-full" />
            </Card>
        </div>
    );
}

export function PhotoGallerySkeleton({ count = 8 }: Readonly<{ count?: number }>) {
    return (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="space-y-2">
                    <Skeleton className="aspect-square w-full rounded-lg" />
                    <Skeleton className="h-4 w-3/4" />
                </div>
            ))}
        </div>
    );
}
