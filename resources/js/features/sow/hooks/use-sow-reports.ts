import { router } from '@inertiajs/react';
import * as React from 'react';

/**
 * The repo has no React Query — report data comes from Inertia server props.
 * This hook re-fetches the `report`/`checklist` props after axios image ops.
 */
export function useSowReports(): { refresh: () => void } {
    const [isRefreshing, setIsRefreshing] = React.useState(false);

    const refresh = React.useCallback(() => {
        if (isRefreshing) return;
        setIsRefreshing(true);
        router.reload({
            only: ['report', 'checklist'],
            onFinish: () => setIsRefreshing(false),
        });
    }, [isRefreshing]);

    return { refresh };
}
