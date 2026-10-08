import { useCallback, useState } from 'react';
import { ArrowLeft, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useMediaLibrary } from '../hooks/use-media-library';
import type { MediaAsset, TeamSupervisor } from '../types/media-library.types';
import { MediaAssetDeleteDialog } from './MediaAssetDeleteDialog';
import { MediaAssetDetail } from './MediaAssetDetail';
import { MediaLibraryGrid } from './MediaLibraryGrid';
import { SupervisorAvatar } from './SupervisorFolderCard';

export interface SupervisorFolderViewProps {
    readonly supervisor: TeamSupervisor;
    readonly isDense?: boolean;
    readonly onBack: () => void;
    readonly onChanged: () => void;
}

export function SupervisorFolderView({ supervisor, isDense = false, onBack, onChanged }: SupervisorFolderViewProps) {
    const { assets, isLoading, isLoadingMore, hasMore, loadMore, removeAssetLocal } = useMediaLibrary({
        status: 'all',
        user_id: supervisor.id,
    });
    const [selectedDetailAsset, setSelectedDetailAsset] = useState<MediaAsset | null>(null);
    const [assetToDelete, setAssetToDelete] = useState<MediaAsset | null>(null);

    const handleDeleted = useCallback(
        (assetId: number) => {
            removeAssetLocal(assetId);
            onChanged();
        },
        [removeAssetLocal, onChanged],
    );

    return (
        <section aria-label={`${supervisor.name}'s photos`} className="space-y-3">
            <div className="flex items-center gap-3">
                <Button type="button" variant="outline" size="sm" onClick={onBack} className="gap-1.5 text-xs">
                    <ArrowLeft className="h-3.5 w-3.5" /> Folders
                </Button>
                <SupervisorAvatar name={supervisor.name} photoUrl={supervisor.profile_photo_url} size="sm" />
                <div className="min-w-0">
                    <h3 className="truncate text-sm font-semibold text-foreground">{supervisor.name}</h3>
                    <p className="truncate text-[11px] text-muted-foreground">
                        {supervisor.email} · {isLoading ? supervisor.assets_count : assets.length} shown
                    </p>
                    <p className="mt-0.5 flex items-center gap-1 text-[11px] text-muted-foreground/70">
                        <Info className="h-3 w-3 shrink-0" />
                        Only photos assigned to your projects are shown. Unassigned uploads from this team member are private.
                    </p>
                </div>
            </div>

            <MediaLibraryGrid
                assets={assets}
                isLoading={isLoading}
                isLoadingMore={isLoadingMore}
                hasMore={hasMore}
                onLoadMore={loadMore}
                onViewAsset={setSelectedDetailAsset}
                onDeleteAsset={setAssetToDelete}
                isDense={isDense}
            />

            <MediaAssetDetail
                asset={selectedDetailAsset}
                isOpen={Boolean(selectedDetailAsset)}
                onClose={() => setSelectedDetailAsset(null)}
                onDeleteRequest={setAssetToDelete}
            />

            <MediaAssetDeleteDialog
                asset={assetToDelete}
                isOpen={Boolean(assetToDelete)}
                onClose={() => setAssetToDelete(null)}
                onDeleted={handleDeleted}
            />
        </section>
    );
}
