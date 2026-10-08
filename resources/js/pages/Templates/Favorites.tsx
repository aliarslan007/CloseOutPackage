import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import type { FavoriteOption } from '@/components/templates/favorite-types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, router } from '@inertiajs/react';
import { Bookmark, Layers, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

export type FavoritesPageProps = PageProps<{
    favorites: FavoriteOption[];
}>;

function formatDate(value?: string): string {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString([], {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function FavoritesPage({ favorites }: FavoritesPageProps) {
    const [pendingDelete, setPendingDelete] = useState<FavoriteOption | null>(null);
    const [deleting, setDeleting] = useState(false);

    const handleDelete = () => {
        if (!pendingDelete) {
            return;
        }
        setDeleting(true);
        router.delete(route('favorites.destroy', pendingDelete.id), {
            onSuccess: () => {
                toast.success('Favorite template deleted.');
                setPendingDelete(null);
                setDeleting(false);
            },
            onError: () => {
                toast.error('Could not delete the favorite. Please try again.');
                setDeleting(false);
            },
        });
    };

    return (
        <AppLayout title="My Favorite Templates">
            <Head title="Favorite Templates" />

            <div className="space-y-2">
                {favorites.length === 0 ? (
                    <div className="p-10 border border-dashed rounded-xl text-center text-sm text-muted-foreground bg-card">
                        No favorite templates saved yet. While building a package, use the
                        &ldquo;Favorites&rdquo; button to save your selections for reuse.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {favorites.map((favorite) => {
                            const categoryCount = favorite.category_count;
                            return (
                                <Card
                                    key={favorite.id}
                                    className="transition-all hover:border-primary/50"
                                >
                                    <CardContent className="p-4 space-y-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3 min-w-0">
                                                <div className="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                                    <Bookmark className="size-5" />
                                                </div>
                                                <div className="min-w-0">
                                                    <h3 className="font-semibold text-sm truncate">
                                                        {favorite.name}
                                                    </h3>
                                                    <p className="text-[11px] text-muted-foreground truncate">
                                                        {favorite.template?.name || 'Template'}
                                                        {favorite.template?.market
                                                            ? ` · ${favorite.template.market.name}`
                                                            : ''}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete favorite ${favorite.name}`}
                                                onClick={() => setPendingDelete(favorite)}
                                                className="shrink-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                        <div className="flex items-center justify-between border-t border-border pt-3">
                                            <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                                                <Layers className="size-3.5" />
                                                {categoryCount} categor
                                                {categoryCount === 1 ? 'y' : 'ies'} saved
                                            </span>
                                            <span className="text-[11px] text-muted-foreground">
                                                {formatDate(favorite.created_at)}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) setPendingDelete(null);
                }}
                title="Delete favorite template"
                description={`Are you sure you want to delete "${pendingDelete?.name}"? Your saved selections will be removed.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={deleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default FavoritesPage;
