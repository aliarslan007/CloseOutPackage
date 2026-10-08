import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/permissions';
import { Button } from '@/components/ui/button';
import { Trash2 } from 'lucide-react';
import { Head } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { useAccessibleProjects } from '../hooks/use-accessible-projects';
import { useMediaLibrary } from '../hooks/use-media-library';
import { useMediaStats } from '../hooks/use-media-stats';
import { useTeamSupervisors } from '../hooks/use-team-supervisors';
import { useUploadQueue } from '../hooks/use-upload-queue';
import type { MediaAsset, MediaLibraryProject, TeamSupervisor } from '../types/media-library.types';
import { MediaAssetDeleteDialog } from './MediaAssetDeleteDialog';
import { MediaAssetDetail } from './MediaAssetDetail';
import { MediaCanvasEditorDialog } from './MediaCanvasEditorDialog';
import { MediaHeaderActions } from './MediaHeaderActions';
import { MediaLibraryGrid } from './MediaLibraryGrid';
import { MediaLibraryToolbar } from './MediaLibraryToolbar';
import { MediaPurgeDialog } from './MediaPurgeDialog';
import { uploaderSectionLabel } from './MediaUploaderFilter';
import { UploadDropzone } from './UploadDropzone';
import { UploadQueue } from './UploadQueue';

export interface MediaLibraryPageProps {
    readonly projects?: MediaLibraryProject[];
    readonly technicians?: TeamSupervisor[];
}

export function MediaLibraryPage({
    projects: initialProjects,
    technicians: initialTechnicians,
}: MediaLibraryPageProps = {}) {
    const { projects } = useAccessibleProjects(initialProjects);
    const { user, isAdmin, isManager, isFieldSupervisor } = usePermissions();
    const showTeam = isAdmin || isManager || isFieldSupervisor;
    // Admins/Managers list their technicians; technicians list their managers + admins.
    const sectionLabel = uploaderSectionLabel(isFieldSupervisor, isAdmin);
    const [isDense, setIsDense] = useState(false);
    const [showDropzone, setShowDropzone] = useState(false);
    const [selectedDetailAsset, setSelectedDetailAsset] = useState<MediaAsset | null>(null);
    const [assetToDelete, setAssetToDelete] = useState<MediaAsset | null>(null);
    const [annotatingAsset, setAnnotatingAsset] = useState<MediaAsset | null>(null);
    const [purgeOpen, setPurgeOpen] = useState(false);

    const {
        assets,
        isLoading,
        isLoadingMore,
        hasMore,
        filters,
        updateFilters,
        resetFilters,
        loadMore,
        removeAssetLocal,
        prependAssetLocal,
    } = useMediaLibrary({ status: 'all' });

    const { stats, refetch: refetchStats } = useMediaStats();
    const { supervisors } = useTeamSupervisors(showTeam, initialTechnicians);
    const statsTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleAssetCompleted = useCallback(
        (newAsset: MediaAsset) => {
            prependAssetLocal(newAsset);
            if (statsTimerRef.current) {
                clearTimeout(statsTimerRef.current);
            }
            statsTimerRef.current = setTimeout(() => {
                refetchStats();
            }, 1500);
        },
        [prependAssetLocal, refetchStats]
    );

    useUploadQueue(handleAssetCompleted);

    const handleDeleted = useCallback(
        (assetId: number) => {
            removeAssetLocal(assetId);
            refetchStats();
        },
        [removeAssetLocal, refetchStats]
    );

    const hasActiveFilters = Boolean(
        filters.search ||
        (filters.status && filters.status !== 'all') ||
        filters.project_id ||
        filters.unassigned ||
        filters.user_id ||
        filters.market_id
    );

    return (
        <AppLayout
            title="Media Library"
            breadcrumb={[{ label: 'Media Library' }]}
            headerActions={
                <>
                    <MediaHeaderActions
                        stats={stats}
                        showDropzone={showDropzone}
                        onToggleDropzone={() => setShowDropzone((prev) => !prev)}
                    />

                    {/* System-wide purge. Admin-only in the UI *and* on the route;
                        non-admins never see the control at all. */}
                    {isAdmin ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setPurgeOpen(true)}
                            className="h-8 shrink-0 gap-1.5 px-2.5 text-xs font-semibold text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                            <span>Delete all media</span>
                        </Button>
                    ) : null}
                </>
            }
        >
            <Head title="Media Library" />
            <div className="space-y-4 pb-16">
                {/* Optional Collapsible Dropzone */}
                {showDropzone && (
                    <div className="animate-in fade-in-50 duration-200">
                        <UploadDropzone projects={projects} />
                    </div>
                )}

                {/* Search & Filter Toolbar */}
                <MediaLibraryToolbar
                    filters={filters}
                    projects={projects}
                    technicians={supervisors}
                    loggedInUserId={user?.id}
                    showUploaderFilter={showTeam}
                    uploaderSectionLabel={sectionLabel}
                    onFiltersChange={updateFilters}
                    onResetFilters={resetFilters}
                    isDense={isDense}
                    onToggleDense={() => setIsDense((prev) => !prev)}
                />

                {/* Media Asset Grid — All images in one view */}
                <MediaLibraryGrid
                    assets={assets}
                    isLoading={isLoading}
                    isLoadingMore={isLoadingMore}
                    hasMore={hasMore}
                    onLoadMore={loadMore}
                    onViewAsset={setSelectedDetailAsset}
                    onDeleteAsset={setAssetToDelete}
                    onAnnotateAsset={setAnnotatingAsset}
                    isDense={isDense}
                    hasActiveFilters={hasActiveFilters}
                    onResetFilters={resetFilters}
                    onOpenUpload={() => setShowDropzone(true)}
                />

                {/* Floating Upload Queue Drawer */}
                <UploadQueue />

                {/* Modals */}
                <MediaAssetDetail
                    asset={selectedDetailAsset}
                    isOpen={Boolean(selectedDetailAsset)}
                    onClose={() => setSelectedDetailAsset(null)}
                    onDeleteRequest={setAssetToDelete}
                    onAnnotate={(asset) => {
                        setSelectedDetailAsset(null);
                        setAnnotatingAsset(asset);
                    }}
                />

                <MediaCanvasEditorDialog
                    asset={annotatingAsset}
                    isOpen={Boolean(annotatingAsset)}
                    onClose={() => setAnnotatingAsset(null)}
                    onSaved={handleAssetCompleted}
                />

                <MediaAssetDeleteDialog
                    asset={assetToDelete}
                    isOpen={Boolean(assetToDelete)}
                    onClose={() => setAssetToDelete(null)}
                    onDeleted={handleDeleted}
                />

                <MediaPurgeDialog
                    isOpen={purgeOpen}
                    onClose={() => setPurgeOpen(false)}
                    onCompleted={() => {
                        resetFilters();
                        refetchStats();
                    }}
                />
            </div>
        </AppLayout>
    );
}
