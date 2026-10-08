import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Link, router } from '@inertiajs/react';
import { Bookmark, BookmarkPlus, ExternalLink, Play } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FavoriteListItem } from '@/components/templates/favorite-list-item';
import type { FavoriteData, FavoriteSnapshotItem } from '@/components/templates/favorite-types';

export type { FavoriteData, FavoriteSnapshotItem } from '@/components/templates/favorite-types';

interface FavoritesDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    favorites: FavoriteData[];
    templateId?: number;
    canSave: boolean;
    snapshot: FavoriteSnapshotItem[];
    onApply: (favorite: FavoriteData) => void;
}

export function FavoritesDialog({
    open,
    onOpenChange,
    favorites,
    templateId,
    canSave,
    snapshot,
    onApply,
}: Readonly<FavoritesDialogProps>) {
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    const handleSave = () => {
        if (!templateId) {
            toast.error('This package has no template to save a favorite against.');
            return;
        }
        if (name.trim() === '') {
            toast.error('Please name your favorite first.');
            return;
        }

        setSaving(true);
        router.post(
            route('favorites.store'),
            { name: name.trim(), template_id: templateId, snapshot },
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success('Favorite template saved.');
                    setName('');
                },
                onError: () => toast.error('Could not save the favorite. Please try again.'),
                onFinish: () => setSaving(false),
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Saved Favorites</DialogTitle>
                    <DialogDescription>
                        Reuse your page-3 selections: apply a saved favorite to this step, or save
                        the current selections for later.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-5">
                    {/* Save current selections */}
                    {canSave && (
                        <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
                            <p className="flex items-center gap-2 text-sm font-semibold">
                                <BookmarkPlus className="size-4 text-primary" />
                                Save current selections
                            </p>
                            <div className="flex gap-2">
                                <Input
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Ohio Grounding Layout"
                                    aria-label="Favorite name"
                                    className="h-9 text-sm"
                                />
                                <Button
                                    onClick={handleSave}
                                    loading={saving}
                                    disabled={snapshot.length === 0}
                                    className="shrink-0"
                                    size="sm"
                                >
                                    Save
                                </Button>
                            </div>
                            {snapshot.length === 0 && (
                                <p className="text-[11px] text-muted-foreground">
                                    No categories available on this step yet.
                                </p>
                            )}
                        </div>
                    )}

                    {/* Apply a saved favorite */}
                    <div className="space-y-2">
                        <p className="flex items-center gap-2 text-sm font-semibold">
                            <Bookmark className="size-4 text-primary" />
                            Apply a saved favorite
                        </p>
                        {(() => {
                            const matching = favorites.filter(
                                (favorite) => favorite.template_id === templateId
                            );

                            if (matching.length === 0) {
                                return (
                                    <p className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                                        {favorites.length === 0
                                            ? 'No favorites saved yet. Save your current selections to reuse them on the next site.'
                                            : 'No favorites saved for this template yet. Favorites only apply to packages built from the same master template.'}
                                    </p>
                                );
                            }

                            return (
                                <ul className="space-y-2">
                                    {matching.map((favorite) => (
                                        <FavoriteListItem
                                            key={favorite.id}
                                            favorite={{
                                                id: favorite.id,
                                                name: favorite.name,
                                                category_count: favorite.snapshot.length,
                                                template: favorite.template ?? null,
                                            }}
                                            action={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="shrink-0 gap-1.5"
                                                    onClick={() => onApply(favorite)}
                                                >
                                                    <Play className="size-3" />
                                                    Apply
                                                </Button>
                                            }
                                        />
                                    ))}
                                </ul>
                            );
                        })()}
                    </div>
                </div>

                <Link
                    href={route('favorites.index')}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ExternalLink className="size-3.5" />
                    Manage all favorites
                </Link>
            </DialogContent>
        </Dialog>
    );
}
