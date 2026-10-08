import * as React from 'react';
import { useOffline } from '@/hooks/use-offline';
import { WifiOff } from 'lucide-react';

export function OfflineBanner() {
    const isOffline = useOffline();

    if (!isOffline) {
        return null;
    }

    return (
        <div className="bg-amber-500 text-amber-950 px-4 py-2 text-xs font-semibold flex items-center justify-center gap-2 shadow-sm sticky top-0 z-50">
            <WifiOff className="size-4 shrink-0" />
            <span>You are currently offline. Changes will be saved locally and synced when connection is restored.</span>
        </div>
    );
}

export default OfflineBanner;
