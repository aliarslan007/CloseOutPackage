import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Loader } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

export interface RefreshButtonProps {
    /** Button text. Defaults to "Refresh". */
    label?: string;
    /** Tooltip + accessible name. Defaults to a generic description. */
    title?: string;
    className?: string;
    onSuccess?: () => void;
    successToast?: string | null;
}

/**
 * Re-fetches the current Inertia page from the server (keeping scroll
 * position), so fresh data — status changes, review decisions, replaced
 * photos — shows up without a full browser reload. The icon spins while
 * the request is in flight and the button disables to avoid double-clicks.
 */
export function RefreshButton({
    label = 'Refresh',
    title = 'Reload the latest data from the server.',
    className,
    onSuccess,
    successToast = 'Refreshed with latest data',
}: Readonly<RefreshButtonProps>) {
    const [refreshing, setRefreshing] = useState(false);
    const inFlight = useRef(false);

    const handleRefresh = () => {
        if (inFlight.current) {
            return;
        }
        inFlight.current = true;
        setRefreshing(true);
        router.reload({
            onSuccess: () => {
                if (successToast) {
                    toast.success(successToast);
                }
                onSuccess?.();
            },
            onError: () => {
                toast.error('Could not refresh data. Please check your connection.');
            },
            onFinish: () => {
                inFlight.current = false;
                setRefreshing(false);
            },
        });
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handleRefresh}
            disabled={refreshing}
            title={title}
            className={cn('gap-1.5 cursor-pointer', className)}
        >
            <Loader className={cn('size-3.5', refreshing && 'animate-spin')} />
            {label}
        </Button>
    );
}

export default RefreshButton;
