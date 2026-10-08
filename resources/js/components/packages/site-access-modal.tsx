import { SiteAccessForm } from '@/components/packages/site-access-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface SiteAccessModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Route key (hash) of the package the details belong to. */
    packageId: number | string;
    scopeOfWork?: string | null;
    siteAccessNotes?: string | null;
    /** False for locked packages and on the manager review page. */
    canEdit: boolean;
}

/**
 * Scope of work and site access notes for one package. The form is mounted on
 * open only, so every open starts read-only from the values the page holds.
 */
export function SiteAccessModal({
    open,
    onOpenChange,
    packageId,
    scopeOfWork,
    siteAccessNotes,
    canEdit,
}: Readonly<SiteAccessModalProps>) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] max-w-2xl flex-col gap-0 overflow-hidden p-0">
                <DialogHeader className="space-y-1 border-b bg-muted/20 p-4 pb-3 sm:p-6 sm:pb-3">
                    <DialogTitle className="text-base font-bold sm:text-lg">
                        Scope of Work &amp; Site Access
                    </DialogTitle>
                    <DialogDescription className="mt-0.5 text-xs text-muted-foreground">
                        {canEdit
                            ? 'Captured on the pre-construction step. Saving updates this package only.'
                            : 'Captured on the pre-construction step. Read-only — the package is no longer editable.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <SiteAccessForm
                        packageId={packageId}
                        scopeOfWork={scopeOfWork}
                        siteAccessNotes={siteAccessNotes}
                        canEdit={canEdit}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

export default SiteAccessModal;
