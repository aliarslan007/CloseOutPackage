import { WifiOff } from 'lucide-react';

/** Non-blocking banner for the field tech when GPS/network is unavailable. */
export function OfflineState() {
    return (
        <div className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
            <WifiOff className="size-4 shrink-0" />
            <span>
                No GPS signal or connection — your manager won't see your live location until it
                recovers. Reconnect automatically when the signal returns.
            </span>
        </div>
    );
}
