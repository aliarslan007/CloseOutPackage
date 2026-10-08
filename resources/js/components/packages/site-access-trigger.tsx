import { SiteAccessModal } from '@/components/packages/site-access-modal';
import { Button } from '@/components/ui/button';
import { Compass } from 'lucide-react';
import { useState } from 'react';

export interface SiteAccessTriggerProps {
    /** Route key (hash) of the package the details belong to. */
    packageId: number | string;
    scopeOfWork?: string | null;
    siteAccessNotes?: string | null;
    /** False for locked packages and on the manager review page. */
    canEdit: boolean;
}

/**
 * Header entry point to this package's scope of work and site access notes.
 * Shown on the pre-construction step only; hidden when there is nothing to
 * read and nothing to edit.
 */
export function SiteAccessTrigger({
    packageId,
    scopeOfWork,
    siteAccessNotes,
    canEdit,
}: Readonly<SiteAccessTriggerProps>) {
    const [open, setOpen] = useState(false);

    const hasContent = Boolean((scopeOfWork ?? '').trim() || (siteAccessNotes ?? '').trim());

    if (!canEdit && !hasContent) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
                className="relative inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 px-3.5 text-xs font-medium shadow-2xs transition-all border-teal-500/30 bg-teal-500/10 text-teal-900 hover:border-teal-500/50 hover:bg-teal-500/20 hover:text-teal-950 dark:border-teal-400/30 dark:bg-teal-500/15 dark:text-teal-200 dark:hover:bg-teal-500/25 dark:hover:text-teal-100"
                title={
                    canEdit
                        ? 'Add or edit the scope of work and site access notes for this package.'
                        : 'View the scope of work and site access notes for this package.'
                }
            >
                <Compass className="size-3.5 shrink-0 text-teal-600 dark:text-teal-400" />
                <span className="truncate">Scope &amp; Site Access</span>
            </Button>

            <SiteAccessModal
                open={open}
                onOpenChange={setOpen}
                packageId={packageId}
                scopeOfWork={scopeOfWork}
                siteAccessNotes={siteAccessNotes}
                canEdit={canEdit}
            />
        </>
    );
}

export default SiteAccessTrigger;
