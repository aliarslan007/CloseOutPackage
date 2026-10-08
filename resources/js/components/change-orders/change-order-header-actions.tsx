import { ChangeOrderStatusBadge } from '@/components/change-orders/change-order-status-badge';
import { sendBlocker } from '@/components/change-orders/change-order-send-rules';
import type { ChangeOrder } from '@/components/change-orders/change-order-types';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { FileEdit, MoreVertical, Send, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ChangeOrderHeaderActionsProps {
    projectIdentifier: number | string;
    changeOrder: ChangeOrder;
}

/**
 * Status badge plus the send/resend and draft lifecycle actions shown in the
 * page header.
 */
export function ChangeOrderHeaderActions({
    projectIdentifier,
    changeOrder,
}: Readonly<ChangeOrderHeaderActionsProps>) {
    const permissions = usePermissions();
    const [isSending, setIsSending] = React.useState(false);
    const [showSendDialog, setShowSendDialog] = React.useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = React.useState(false);

    const isDraft = changeOrder.status === 'draft';
    const isFinalized = changeOrder.status === 'approved' || changeOrder.status === 'rejected';
    const canSend = permissions.canManageChangeOrders && !isFinalized;

    // The backend refuses the send either way; this only lets the button say why
    // before the round trip.
    const blockedReason = sendBlocker(changeOrder);
    const isSendBlocked = canSend && blockedReason !== null;

    let sendButtonLabel = 'Resend to Customer';
    if (isSending) {
        sendButtonLabel = 'Sending...';
    } else if (isDraft) {
        sendButtonLabel = 'Send to Customer';
    }

    const handleSend = () => {
        setIsSending(true);
        router.post(
            route('projects.change-orders.send', [projectIdentifier, changeOrder.id]),
            {},
            {
                onFinish: () => {
                    setIsSending(false);
                    setShowSendDialog(false);
                },
                onSuccess: () => {
                    toast.success('Change order sent to Verizon customer contacts.');
                },
                onError: () => {
                    toast.error('Failed to send change order.');
                },
            }
        );
    };

    const handleDelete = () => {
        router.delete(
            route('projects.change-orders.destroy', [projectIdentifier, changeOrder.id]),
            {
                onSuccess: () => {
                    toast.success('Change order draft deleted.');
                },
            }
        );
    };

    return (
        <>
            <div className="flex items-center gap-2 shrink-0">
                <ChangeOrderStatusBadge status={changeOrder.status} />
                {canSend && (
                    // A disabled button swallows hover, so the explanation goes on
                    // a wrapper the pointer can actually reach.
                    <span className="inline-flex" title={blockedReason ?? undefined}>
                        <Button
                            onClick={() => setShowSendDialog(true)}
                            disabled={isSending || isSendBlocked}
                            variant={isDraft ? 'default' : 'outline'}
                            size="sm"
                            aria-disabled={isSendBlocked}
                            className={cn(
                                'h-9 gap-1.5 text-xs shadow-2xs cursor-pointer',
                                isDraft ? 'font-bold' : 'font-semibold'
                            )}
                        >
                            <Send className="size-3.5" />
                            <span>{sendButtonLabel}</span>
                        </Button>
                    </span>
                )}

                {isDraft && permissions.canManageChangeOrders && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                aria-label="More options"
                                className="flex size-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shadow-2xs cursor-pointer"
                            >
                                <MoreVertical className="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44 text-xs">
                            <DropdownMenuItem asChild>
                                <Link
                                    href={route('projects.change-orders.edit', [
                                        projectIdentifier,
                                        changeOrder.id,
                                    ])}
                                    className="cursor-pointer gap-2"
                                >
                                    <FileEdit className="size-3.5 mr-2" />
                                    <span>Edit Draft</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => setShowDeleteDialog(true)}
                                className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                            >
                                <Trash2 className="size-3.5 mr-2" />
                                <span>Delete Draft</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            <ConfirmDialog
                open={showSendDialog}
                onOpenChange={setShowSendDialog}
                title={isDraft ? 'Send Change Order to Customer' : 'Resend Change Order to Customer'}
                description={
                    isDraft
                        ? 'This will email decision access links to the assigned Verizon customer contacts and open the change order for review. Are you sure you want to proceed?'
                        : 'This will send fresh decision access reminder email links to the assigned Verizon customer contacts. Are you sure you want to proceed?'
                }
                confirmLabel={isDraft ? 'Send to Customer' : 'Resend Email'}
                onConfirm={handleSend}
            />

            <ConfirmDialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
                title="Delete Change Order Draft"
                description="Are you sure you want to delete this change order draft? This action cannot be undone."
                confirmLabel="Delete Draft"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </>
    );
}

export default ChangeOrderHeaderActions;
