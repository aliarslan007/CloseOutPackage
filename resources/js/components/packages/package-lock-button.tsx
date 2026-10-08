import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Lock, LockOpen } from 'lucide-react';
import * as React from 'react';

export interface PackageLockButtonProps {
    /** Route key (may be an obfuscated hash id). */
    readonly packageId: number | string;
    readonly locked: boolean;
    /** Backend-computed via the `lock` policy — the source of truth. */
    readonly canLock: boolean;
    /** Backend-computed via the `unlock` policy — the source of truth. */
    readonly canUnlock: boolean;
    readonly size?: 'sm' | 'default';
}

export function PackageLockButton({
    packageId,
    locked,
    canLock,
    canUnlock,
    size = 'sm',
}: Readonly<PackageLockButtonProps>) {
    const [confirmOpen, setConfirmOpen] = React.useState(false);
    const [processing, setProcessing] = React.useState(false);

    const canAct = locked ? canUnlock : canLock;

    if (!canAct) {
        return null;
    }

    const handleConfirm = () => {
        setProcessing(true);
        router.post(
            route(locked ? 'packages.unlock' : 'packages.lock', packageId),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setConfirmOpen(false);
                },
            }
        );
    };

    return (
        <>
            <Button
                type="button"
                variant={locked ? 'success' : 'warning'}
                size={size}
                className="h-8 px-3 gap-1.5 text-[11px]"
                onClick={() => setConfirmOpen(true)}
                title={
                    locked
                        ? 'Restore access to this package for all project members.'
                        : 'Restrict this package to admins and yourself.'
                }
            >
                {locked ? <LockOpen className="size-3.5" /> : <Lock className="size-3.5" />}
                {locked ? 'Unlock' : 'Lock'}
            </Button>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={(open) => {
                    if (!open && !processing) {
                        setConfirmOpen(false);
                    }
                }}
                title={locked ? 'Unlock package' : 'Lock package'}
                description={
                    locked
                        ? 'Unlocking this package will restore access for all project members.'
                        : 'Locking this package will prevent anyone except you and admins from opening or downloading it.'
                }
                confirmLabel={locked ? 'Unlock' : 'Lock'}
                loading={processing}
                onConfirm={handleConfirm}
            />
        </>
    );
}

export default PackageLockButton;
