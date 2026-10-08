import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Link, router } from '@inertiajs/react';
import { Bookmark, FilePlus2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FavoriteListItem } from '@/components/templates/favorite-list-item';
import type { FavoriteOption } from '@/components/templates/favorite-types';

interface UseFavoriteDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    favorites: FavoriteOption[];
    projectId: number | string;
}

export function UseFavoriteDialog({
    open,
    onOpenChange,
    favorites,
    projectId,
}: Readonly<UseFavoriteDialogProps>) {
    const [creatingId, setCreatingId] = useState<number | null>(null);

    const handleCreate = (favorite: FavoriteOption) => {
        setCreatingId(favorite.id);
        router.post(
            route('packages.store', projectId),
            {
                project_id: projectId,
                template_id: favorite.template_id,
                favorite_id: favorite.id,
            },
            {
                onSuccess: () => {
                    toast.success(`Package created from "${favorite.name}".`);
                    setCreatingId(null);
                },
                onError: () => {
                    toast.error('Could not create the package. Please try again.');
                    setCreatingId(null);
                },
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create Package from a Favorite</DialogTitle>
                    <DialogDescription>
                        Pick a saved favorite to start a new close-out package with your saved
                        categories, notes, and photo counts already applied.
                    </DialogDescription>
                </DialogHeader>

                {favorites.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                        No favorites saved for this site&apos;s market yet. Save one from the{' '}
                        <Link
                            href={route('favorites.index')}
                            className="text-primary hover:underline"
                        >
                            Favorites
                        </Link>{' '}
                        page or while building a package.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {favorites.map((favorite) => (
                            <FavoriteListItem
                                key={favorite.id}
                                favorite={favorite}
                                action={
                                    <Button
                                        size="sm"
                                        className="shrink-0 gap-1.5"
                                        loading={creatingId === favorite.id}
                                        disabled={creatingId !== null}
                                        onClick={() => handleCreate(favorite)}
                                    >
                                        <FilePlus2 className="size-3" />
                                        Create Package
                                    </Button>
                                }
                            />
                        ))}
                    </ul>
                )}

                <Link
                    href={route('favorites.index')}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    <Bookmark className="size-3.5" />
                    Manage favorites
                </Link>
            </DialogContent>
        </Dialog>
    );
}
