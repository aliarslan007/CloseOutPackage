import { Button } from '@/components/ui/button';
import { LocateOff } from 'lucide-react';

/**
 * Shown when the browser blocks geolocation — plan 3-D edge case with
 * OS-specific instructions and a retry.
 */
export function PermissionDeniedState({ onRetry }: Readonly<{ onRetry: () => void }>) {
    const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
    const instructions = isMobile
        ? 'Open your browser settings → Site settings → Location → Allow, then come back and retry.'
        : 'Click the padlock in the address bar → Site settings → Location → Allow, then retry.';

    return (
        <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 p-8 text-center">
            <span className="flex size-14 items-center justify-center rounded-full bg-red-500/10">
                <LocateOff className="size-7 text-red-600" />
            </span>
            <div>
                <h2 className="text-base font-bold text-foreground">
                    Location permission is blocked
                </h2>
                <p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">
                    {instructions}
                </p>
            </div>
            <Button variant="outline" size="sm" onClick={onRetry}>
                Try Again
            </Button>
        </div>
    );
}
