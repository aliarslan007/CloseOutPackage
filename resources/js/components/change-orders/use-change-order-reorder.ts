import { ChangeOrder } from '@/components/change-orders/change-order-types';
import axios from 'axios';
import * as React from 'react';
import { toast } from 'sonner';

/** One notification slot, so back-to-back drags update the bar instead of
 *  stacking a toast per row. */
const SAVE_TOAST_ID = 'change-order-reorder';

export interface ReorderHandleProps {
    draggable: boolean;
    onDragStart: (event: React.DragEvent<HTMLElement>) => void;
    onDragEnd: () => void;
}

export interface ReorderRowProps {
    onDragOver: (event: React.DragEvent<HTMLTableRowElement>) => void;
    onDrop: (event: React.DragEvent<HTMLTableRowElement>) => void;
}

interface UseChangeOrderReorderResult {
    /** The full list in its current (optimistically reordered) position. */
    orders: ChangeOrder[];
    /** True while a request is in flight — the handle is inert until it settles. */
    isSaving: boolean;
    /** Spread onto the drag handle; drag is inert unless `enabled`. */
    handleProps: (order: ChangeOrder) => ReorderHandleProps;
    /** Spread onto each <tr> so it can receive the drop. */
    rowProps: (order: ChangeOrder) => ReorderRowProps;
    isDragSource: (order: ChangeOrder) => boolean;
    isDragTarget: (order: ChangeOrder) => boolean;
}

/** Moves `draggedId` to `targetId`'s position. Null when either is gone. */
function moveWithin(orders: ChangeOrder[], draggedId: number, targetId: number): ChangeOrder[] | null {
    const fromIndex = orders.findIndex((order) => order.id === draggedId);
    const toIndex = orders.findIndex((order) => order.id === targetId);
    if (fromIndex < 0 || toIndex < 0) return null;

    const reordered = [...orders];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);
    return reordered;
}

/**
 * Manager/admin drag-to-reorder for a change order list. Native HTML5 drag
 * (same approach as the SOW image strip) — no drag library.
 *
 * Ordering lives in two independent sequences, so the caller passes the
 * endpoint that owns the one it is showing: the project page saves the
 * project's order, the all-projects page saves the global order. The whole
 * displayed list is sent, the move is applied optimistically, and it is
 * rolled back with a toast if the request fails (offline, 403, 422, …).
 */
export function useChangeOrderReorder(
    changeOrders: ChangeOrder[],
    enabled: boolean,
    reorderUrl: string
): UseChangeOrderReorderResult {
    const [orders, setOrders] = React.useState(changeOrders);
    const [draggingId, setDraggingId] = React.useState<number | null>(null);
    const [overId, setOverId] = React.useState<number | null>(null);
    const [isSaving, setIsSaving] = React.useState(false);

    // Inertia partial reloads deliver fresh props (after create/delete) — follow them.
    React.useEffect(() => {
        setOrders(changeOrders);
    }, [changeOrders]);

    const persist = React.useCallback(
        async (reordered: ChangeOrder[], fallback: ChangeOrder[]): Promise<void> => {
            const items = reordered.map((order, index) => ({
                id: order.id,
                sort_order: index,
            }));

            setIsSaving(true);
            try {
                await axios.post(reorderUrl, { items });
                toast.success('Display order saved.', { id: SAVE_TOAST_ID });
            } catch (err) {
                setOrders(fallback);
                toast.error('Could not save the new order. Check your connection and try again.', {
                    id: SAVE_TOAST_ID,
                });
                window.console.error(err);
            } finally {
                setIsSaving(false);
            }
        },
        [reorderUrl]
    );

    const handleProps = React.useCallback(
        (order: ChangeOrder): ReorderHandleProps => ({
            draggable: enabled && !isSaving,
            onDragStart: (event) => {
                // Firefox refuses to start a drag without payload data.
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(order.id));
                setDraggingId(order.id);
            },
            onDragEnd: () => {
                setDraggingId(null);
                setOverId(null);
            },
        }),
        [enabled, isSaving]
    );

    const rowProps = React.useCallback(
        (order: ChangeOrder): ReorderRowProps => ({
            onDragOver: (event) => {
                if (draggingId === null || draggingId === order.id) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                setOverId(order.id);
            },
            onDrop: (event) => {
                event.preventDefault();
                setDraggingId(null);
                setOverId(null);

                if (draggingId === null || draggingId === order.id) return;

                const reordered = moveWithin(orders, draggingId, order.id);
                if (reordered === null) return;

                setOrders(reordered);
                void persist(reordered, orders);
            },
        }),
        [draggingId, orders, persist]
    );

    return {
        orders,
        isSaving,
        handleProps,
        rowProps,
        isDragSource: (order: ChangeOrder) => draggingId === order.id,
        isDragTarget: (order: ChangeOrder) => overId === order.id && draggingId !== order.id,
    };
}
