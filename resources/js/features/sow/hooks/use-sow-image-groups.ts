import * as React from 'react';
import { toast } from 'sonner';
import { sowImageGroupsApi } from '../api/sow-image-groups.api';
import { useSowReports } from './use-sow-reports';
import type { SowAnnotatedImage, SowImageGroup, SowReport } from '../types/sow.types';
import { sowErrorMessage } from '../utils/sow-error';

export type GroupKey = number | null;

export interface UseSowImageGroups {
    groups: SowImageGroup[];
    imagesFor: (key: GroupKey) => SowAnnotatedImage[];
    ungrouped: SowAnnotatedImage[];
    selectedIds: ReadonlySet<number>;
    busy: boolean;
    toggleSelection: (imageId: number) => void;
    clearSelection: () => void;
    createGroup: (name: string) => Promise<void>;
    renameGroup: (groupId: number, name: string) => Promise<void>;
    deleteGroup: (groupId: number) => Promise<void>;
    moveSelectedTo: (groupId: GroupKey) => Promise<void>;
    moveImagesTo: (imageIds: number[], groupId: GroupKey) => Promise<void>;
    reorderGroups: (fromId: number, toId: number) => Promise<void>;
    reorderImagesWithin: (key: GroupKey, fromId: number, toId: number) => Promise<void>;
}

/**
 * Owns the grouped photo board: group order, image membership per group, and
 * selection. Every ordering/membership change is flattened into one atomic
 * layout write so the board and the database cannot diverge.
 */
export function useSowImageGroups(report: SowReport, canUpdate: boolean): UseSowImageGroups {
    const { refresh } = useSowReports();
    const [busy, setBusy] = React.useState(false);
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());

    const serverGroups = React.useMemo(
        () => [...(report.image_groups ?? [])].sort((a, b) => a.sort_order - b.sort_order),
        [report.image_groups]
    );
    const serverImages = React.useMemo(
        () => report.annotated_images ?? [],
        [report.annotated_images]
    );

    // Local mirrors so a drag feels instant; re-synced whenever fresh props land.
    const [localGroups, setLocalGroups] = React.useState<SowImageGroup[]>(serverGroups);
    const [localImages, setLocalImages] = React.useState<SowAnnotatedImage[]>(serverImages);

    React.useEffect(() => setLocalGroups(serverGroups), [serverGroups]);
    React.useEffect(() => setLocalImages(serverImages), [serverImages]);

    // Local state is only ever an optimistic overlay on server truth. If it is
    // ever empty while the server still has data (e.g. a reload landed with a
    // stale mirror), fall back to the server copy rather than rendering a board
    // with no groups — which previously looked like the grouping was lost.
    const groups = localGroups.length > 0 || serverGroups.length === 0 ? localGroups : serverGroups;
    const images = localImages.length > 0 || serverImages.length === 0 ? localImages : serverImages;

    const imagesFor = React.useCallback(
        (key: GroupKey) => images.filter((image) => image.sow_image_group_id === key),
        [images]
    );

    const ungrouped = React.useMemo(
        () => images.filter((image) => image.sow_image_group_id === null),
        [images]
    );

    const toggleSelection = React.useCallback((imageId: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(imageId)) next.delete(imageId);
            else next.add(imageId);
            return next;
        });
    }, []);

    const clearSelection = React.useCallback(() => setSelectedIds(new Set()), []);

    /**
     * Flatten the board into the backend's contract: groups in order, each
     * group's photos numbered from 0, ungrouped photos last.
     */
    const persist = React.useCallback(
        async (nextGroups: SowImageGroup[], nextImages: SowAnnotatedImage[]) => {
            if (!canUpdate) return;

            const groupPayload = nextGroups.map((group, index) => ({
                id: group.id,
                sort_order: index,
            }));

            const imagePayload: Array<{
                id: number;
                sow_image_group_id: number | null;
                sort_order: number;
            }> = [];

            for (const group of nextGroups) {
                nextImages
                    .filter((image) => image.sow_image_group_id === group.id)
                    .forEach((image, index) => {
                        imagePayload.push({
                            id: image.id,
                            sow_image_group_id: group.id,
                            sort_order: index,
                        });
                    });
            }

            nextImages
                .filter((image) => image.sow_image_group_id === null)
                .forEach((image, index) => {
                    imagePayload.push({ id: image.id, sow_image_group_id: null, sort_order: index });
                });

            setBusy(true);
            try {
                await sowImageGroupsApi.saveLayout(report.id, {
                    groups: groupPayload,
                    images: imagePayload,
                });
                refresh();
            } catch (err) {
                toast.error(sowErrorMessage(err, 'Failed to save photo order.'));
                // Revert to server truth rather than leave a half-applied board.
                setLocalGroups(serverGroups);
                setLocalImages(serverImages);
            } finally {
                setBusy(false);
            }
        },
        [canUpdate, report.id, refresh, serverGroups, serverImages]
    );

    const createGroup = React.useCallback(
        async (name: string) => {
            setBusy(true);
            try {
                await sowImageGroupsApi.store(report.id, name);
                toast.success(`Group "${name}" created.`);
                refresh();
            } catch (err) {
                toast.error(sowErrorMessage(err, 'Failed to create group.'));
            } finally {
                setBusy(false);
            }
        },
        [report.id, refresh]
    );

    const renameGroup = React.useCallback(
        async (groupId: number, name: string) => {
            setBusy(true);
            try {
                await sowImageGroupsApi.update(report.id, groupId, name);
                setLocalGroups((prev) =>
                    prev.map((group) => (group.id === groupId ? { ...group, name } : group))
                );
                toast.success('Group renamed.');
                refresh();
            } catch (err) {
                toast.error(sowErrorMessage(err, 'Failed to rename group.'));
            } finally {
                setBusy(false);
            }
        },
        [report.id, refresh]
    );

    const deleteGroup = React.useCallback(
        async (groupId: number) => {
            setBusy(true);
            try {
                // The backend ungroups the photos, so they stay in the report.
                await sowImageGroupsApi.destroy(report.id, groupId);
                toast.success('Group deleted — its photos are now ungrouped.');
                refresh();
            } catch (err) {
                toast.error(sowErrorMessage(err, 'Failed to delete group.'));
            } finally {
                setBusy(false);
            }
        },
        [report.id, refresh]
    );

    const moveImagesTo = React.useCallback(
        async (imageIds: number[], groupId: GroupKey) => {
            if (imageIds.length === 0) return;

            const moving = new Set(imageIds);
            const nextImages = images.map((image) =>
                moving.has(image.id) ? { ...image, sow_image_group_id: groupId } : image
            );

            setLocalImages(nextImages);
            setSelectedIds(new Set());
            await persist(groups, nextImages);
        },
        [images, groups, persist]
    );

    const moveSelectedTo = React.useCallback(
        async (groupId: GroupKey) => moveImagesTo([...selectedIds], groupId),
        [moveImagesTo, selectedIds]
    );

    const reorderGroups = React.useCallback(
        async (fromId: number, toId: number) => {
            const fromIndex = groups.findIndex((group) => group.id === fromId);
            const toIndex = groups.findIndex((group) => group.id === toId);
            if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return;

            const nextGroups = [...groups];
            const [moved] = nextGroups.splice(fromIndex, 1);
            nextGroups.splice(toIndex, 0, moved);

            setLocalGroups(nextGroups);
            await persist(nextGroups, images);
        },
        [groups, images, persist]
    );

    const reorderImagesWithin = React.useCallback(
        async (key: GroupKey, fromId: number, toId: number) => {
            const within = images.filter((image) => image.sow_image_group_id === key);
            const fromIndex = within.findIndex((image) => image.id === fromId);
            const toIndex = within.findIndex((image) => image.id === toId);
            if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return;

            const reordered = [...within];
            const [moved] = reordered.splice(fromIndex, 1);
            reordered.splice(toIndex, 0, moved);

            // Rebuild the flat list with this group's slice replaced.
            const others = images.filter((image) => image.sow_image_group_id !== key);
            const nextImages = [...others, ...reordered];

            setLocalImages(nextImages);
            await persist(groups, nextImages);
        },
        [images, groups, persist]
    );

    return {
        groups,
        imagesFor,
        ungrouped,
        selectedIds,
        busy,
        toggleSelection,
        clearSelection,
        createGroup,
        renameGroup,
        deleteGroup,
        moveSelectedTo,
        moveImagesTo,
        reorderGroups,
        reorderImagesWithin,
    };
}
