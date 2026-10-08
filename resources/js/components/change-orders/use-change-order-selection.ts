import * as React from 'react';

export interface UseChangeOrderSelectionReturn {
    selectedIds: number[];
    selectedCount: number;
    isSelected: (id: number) => boolean;
    isAllSelected: (visibleIds: number[]) => boolean;
    isSomeSelected: (visibleIds: number[]) => boolean;
    toggleSelect: (id: number) => void;
    toggleSelectAll: (visibleIds: number[]) => void;
    clearSelection: () => void;
    setSelectedIds: React.Dispatch<React.SetStateAction<number[]>>;
}

export function useChangeOrderSelection(): UseChangeOrderSelectionReturn {
    const [selectedIds, setSelectedIds] = React.useState<number[]>([]);

    const selectedSet = React.useMemo(() => new Set(selectedIds), [selectedIds]);

    const isSelected = React.useCallback(
        (id: number) => selectedSet.has(id),
        [selectedSet]
    );

    const isAllSelected = React.useCallback(
        (visibleIds: number[]) => {
            if (visibleIds.length === 0) return false;
            return visibleIds.every((id) => selectedSet.has(id));
        },
        [selectedSet]
    );

    const isSomeSelected = React.useCallback(
        (visibleIds: number[]) => {
            if (visibleIds.length === 0) return false;
            const hasAny = visibleIds.some((id) => selectedSet.has(id));
            const hasAll = visibleIds.every((id) => selectedSet.has(id));
            return hasAny && !hasAll;
        },
        [selectedSet]
    );

    const toggleSelect = React.useCallback((id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    }, []);

    const toggleSelectAll = React.useCallback((visibleIds: number[]) => {
        setSelectedIds((prev) => {
            const allVisibleSelected = visibleIds.every((id) => prev.includes(id));
            if (allVisibleSelected) {
                // Deselect only the visible ones, preserve any selections outside current page if desired
                return prev.filter((id) => !visibleIds.includes(id));
            }
            // Add all visible IDs that are not yet selected
            const newSet = new Set([...prev, ...visibleIds]);
            return Array.from(newSet);
        });
    }, []);

    const clearSelection = React.useCallback(() => {
        setSelectedIds([]);
    }, []);

    return {
        selectedIds,
        selectedCount: selectedIds.length,
        isSelected,
        isAllSelected,
        isSomeSelected,
        toggleSelect,
        toggleSelectAll,
        clearSelection,
        setSelectedIds,
    };
}
