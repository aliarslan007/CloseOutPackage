import { MapPinOff } from 'lucide-react';

/** Centred empty state — no techs currently sharing location (plan 3-D). */
export function NoTechsState({ projectName }: Readonly<{ projectName: string }>) {
    return (
        <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 p-8 text-center">
            <span className="flex size-14 items-center justify-center rounded-full bg-muted">
                <MapPinOff className="size-7 text-muted-foreground" />
            </span>
            <div>
                <h2 className="text-base font-bold text-foreground">No active technicians</h2>
                <p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">
                    No technicians are currently sharing their location for {projectName}. Their
                    markers appear here automatically once they open the tracking page on site.
                </p>
            </div>
        </div>
    );
}