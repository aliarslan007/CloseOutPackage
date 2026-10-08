import { Layers } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Minimal display shape a list row needs. Consumers map their own data
 * (e.g. FavoriteOption or FavoriteData) onto these fields.
 */
export interface FavoriteRowData {
    id: number;
    name: string;
    category_count: number;
    template?: { id: number; name: string } | null;
}

interface FavoriteListItemProps {
    favorite: FavoriteRowData;
    /** Action slot (e.g. Apply / Create Package button) rendered on the right. */
    action?: ReactNode;
}

export function FavoriteListItem({ favorite, action }: Readonly<FavoriteListItemProps>) {
    return (
        <li className="flex items-center justify-between gap-3 rounded-lg border bg-card p-3">
            <div className="min-w-0">
                <p className="text-sm font-semibold truncate">{favorite.name}</p>
                <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <Layers className="size-3" />
                    {favorite.category_count} categor
                    {favorite.category_count === 1 ? 'y' : 'ies'}
                    {favorite.template?.name ? ` · ${favorite.template.name}` : ''}
                </p>
            </div>
            {action}
        </li>
    );
}
