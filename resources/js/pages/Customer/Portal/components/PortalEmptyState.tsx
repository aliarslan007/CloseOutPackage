import { Button } from '@/components/ui/button';
import { FolderKanban, LifeBuoy, RotateCcw, SearchX } from 'lucide-react';

interface PortalEmptyStateProps {
    isFiltered: boolean;
    onResetFilters?: () => void;
}

export function PortalEmptyState({ isFiltered, onResetFilters }: Readonly<PortalEmptyStateProps>) {
    if (isFiltered) {
        return (
            <div className="flex flex-col items-center px-6 py-14 text-center">
                <div className="flex size-14 items-center justify-center rounded-2xl border border-border/70 bg-secondary/60">
                    <SearchX className="size-6 text-muted-foreground" />
                </div>
                <h3 className="mt-5 font-heading text-lg font-semibold text-foreground">
                    No sites match your filters
                </h3>
                <p className="mt-2 max-w-md text-sm leading-relaxed text-muted-foreground">
                    Try a different search term, or clear the filters to see all of your assigned
                    sites.
                </p>
                {onResetFilters && (
                    <Button
                        variant="outline"
                        onClick={onResetFilters}
                        className="mt-5 h-10 gap-2 px-4 text-sm font-semibold"
                    >
                        <RotateCcw className="size-4" />
                        Clear all filters
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center px-6 py-14 text-center">
            <div className="flex size-14 items-center justify-center rounded-2xl border border-border/70 bg-secondary/60">
                <FolderKanban className="size-6 text-muted-foreground" />
            </div>
            <h3 className="mt-5 font-heading text-lg font-semibold text-foreground">
                No sites assigned to you yet
            </h3>
            <p className="mt-2 max-w-md text-sm leading-relaxed text-muted-foreground">
                When your project administrator assigns a site, it will appear here with its photos,
                checklist, and approval status, so you can follow the work and sign off on each
                package.
            </p>
            <p className="mt-6 inline-flex items-center gap-2 rounded-lg border border-border/70 bg-secondary/40 px-4 py-2.5 text-sm text-muted-foreground">
                <LifeBuoy className="size-4 shrink-0" />
                Need access? Ask your project administrator to enable site oversight for your account.
            </p>
        </div>
    );
}

export default PortalEmptyState;
