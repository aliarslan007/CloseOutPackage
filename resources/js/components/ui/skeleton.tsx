import { cn } from '@/lib/utils';
import * as React from 'react';

function Skeleton({ className, ...props }: Readonly<React.HTMLAttributes<HTMLDivElement>>) {
    return <div className={cn('animate-pulse rounded-md bg-muted/60', className)} {...props} />;
}

export { Skeleton };
