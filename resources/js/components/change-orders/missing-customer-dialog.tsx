import { ChangeOrder } from '@/components/change-orders/change-order-types';
import {
    categorizeOrders,
    getConfirmButtonLabel,
    getDialogDescription,
    getDialogHeaderIcon,
    getDialogTitle,
    getHeaderColorClass,
} from '@/components/change-orders/missing-customer-dialog-helpers';
import {
    AlreadySentOrdersSection,
    DraftOrdersSection,
    UnassignedOrdersSection,
} from '@/components/change-orders/missing-customer-dialog-sections';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Loader2, Send } from 'lucide-react';
import * as React from 'react';

export interface MissingCustomerDialogProps {
    isOpen: boolean;
    onClose: () => void;
    selectedOrders: ChangeOrder[];
    onConfirmSend: (ordersToSend: ChangeOrder[]) => void;
    isSending?: boolean;
}

export function MissingCustomerDialog({
    isOpen,
    onClose,
    selectedOrders = [],
    onConfirmSend,
    isSending = false,
}: Readonly<MissingCustomerDialogProps>) {
    const { unassignedOrders, validOrders, alreadySentOrders, draftOrders } = React.useMemo(
        () => categorizeOrders(selectedOrders),
        [selectedOrders]
    );

    const hasUnassigned = unassignedOrders.length > 0;
    const allUnassigned = hasUnassigned && validOrders.length === 0;
    const hasAlreadySent = alreadySentOrders.length > 0;

    const title = getDialogTitle(
        hasUnassigned,
        allUnassigned,
        hasAlreadySent,
        draftOrders.length,
        alreadySentOrders.length,
        validOrders.length,
        selectedOrders.length
    );
    const description = getDialogDescription(hasUnassigned, hasAlreadySent);
    const headerIcon = getDialogHeaderIcon(hasUnassigned, allUnassigned, hasAlreadySent);
    const headerColorClass = getHeaderColorClass(hasUnassigned, hasAlreadySent);
    const confirmButtonLabel = getConfirmButtonLabel(
        hasUnassigned,
        hasAlreadySent,
        draftOrders.length,
        validOrders.length
    );

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && !isSending && onClose()}>
            <DialogContent className="sm:max-w-lg max-h-[85vh] flex flex-col gap-4 p-6 overflow-hidden">
                <DialogHeader className="text-left space-y-1">
                    <div className={`flex items-center gap-2 ${headerColorClass}`}>
                        {headerIcon}
                        <DialogTitle className="text-base font-bold text-foreground">
                            {title}
                        </DialogTitle>
                    </div>

                    <DialogDescription className="text-xs text-muted-foreground">
                        {description}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 overflow-y-auto max-h-72 pr-1">
                    <UnassignedOrdersSection orders={unassignedOrders} />
                    <AlreadySentOrdersSection orders={alreadySentOrders} />
                    <DraftOrdersSection orders={draftOrders} />
                </div>

                <DialogFooter className="flex items-center justify-between sm:justify-between gap-2 border-t pt-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={isSending}
                        onClick={onClose}
                        className="text-xs h-8 cursor-pointer"
                    >
                        {allUnassigned ? 'Close' : 'Cancel'}
                    </Button>

                    <div className="flex items-center gap-2">
                        {validOrders.length > 0 && (
                            <Button
                                type="button"
                                size="sm"
                                disabled={isSending}
                                onClick={() => onConfirmSend(validOrders)}
                                className="text-xs h-8 gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90 cursor-pointer"
                            >
                                {isSending ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Send className="size-3.5" />
                                )}
                                <span>{confirmButtonLabel}</span>
                            </Button>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
