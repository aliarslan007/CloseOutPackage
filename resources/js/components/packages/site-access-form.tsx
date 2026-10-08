import { SiteAccessBlock, type SiteAccessCopyTarget } from '@/components/packages/site-access-block';
import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Compass, Eraser, Navigation, Pencil } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

/** Mirrors UpdateSiteInfoRequest — the backend rejects anything longer. */
const SCOPE_OF_WORK_MAX = 2500;
const SITE_ACCESS_NOTES_MAX = 2500;

export interface SiteAccessFormProps {
    /** Route key (hash) of the package the details belong to. */
    packageId: number | string;
    scopeOfWork?: string | null;
    siteAccessNotes?: string | null;
    /** False for locked packages and on the manager review page. */
    canEdit: boolean;
}

/** Empties one field in the form; saving then deletes the stored value. */
function ClearFieldButton({ visible, onClick }: Readonly<{ visible: boolean; onClick: () => void }>) {
    if (!visible) return null;

    return (
        <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={onClick}
            className="h-5 cursor-pointer px-1.5 text-[11px] font-medium text-muted-foreground hover:text-destructive"
            title="Clear this field (saved when you press Save)"
        >
            <Eraser className="mr-0.5 size-3" /> Clear
        </Button>
    );
}

/**
 * Reads the saved values by default and only swaps them for inputs while the
 * user is editing, so a saved value is never hidden behind a textarea scroll.
 */
export function SiteAccessForm({
    packageId,
    scopeOfWork,
    siteAccessNotes,
    canEdit,
}: Readonly<SiteAccessFormProps>) {
    const [editing, setEditing] = useState(false);
    const [copied, setCopied] = useState<SiteAccessCopyTarget | null>(null);
    const form = useForm({ scope_of_work: '', site_access_notes: '' });

    const startEditing = () => {
        // Seed from the saved values every time, so a cancelled edit leaves nothing behind.
        form.setData({
            scope_of_work: scopeOfWork ?? '',
            site_access_notes: siteAccessNotes ?? '',
        });
        form.clearErrors();
        setEditing(true);
    };

    const stopEditing = () => {
        form.clearErrors();
        setEditing(false);
    };

    const handleCopy = (text: string, target: SiteAccessCopyTarget) => {
        navigator.clipboard.writeText(text).then(() => {
            setCopied(target);
            setTimeout(() => setCopied(null), 2000);
            toast.success('Copied to clipboard.');
        });
    };

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        // On success the modal returns to the read view, which renders the saved props.
        form.put(route('packages.site-info.update', packageId), {
            preserveScroll: true,
            // A redirect back with errors is still a successful visit, so only
            // leave edit mode when nothing came back to correct.
            onSuccess: (page) => {
                if (Object.keys(page.props.errors ?? {}).length === 0) stopEditing();
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
            <div className="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
                {editing ? (
                    <>
                        <FormField
                            label="Scope of Work"
                            htmlFor="scope_of_work"
                            maxLength={SCOPE_OF_WORK_MAX}
                            valueLength={form.data.scope_of_work.length}
                            error={form.errors.scope_of_work}
                            helper="What this package covers at the site."
                            badge={
                                <ClearFieldButton
                                    visible={form.data.scope_of_work !== ''}
                                    onClick={() => form.setData('scope_of_work', '')}
                                />
                            }
                            className="rounded-xl border bg-card p-4 shadow-2xs"
                        >
                            {/* ~1,000 characters visible before the field itself scrolls. */}
                            <Textarea
                                id="scope_of_work"
                                value={form.data.scope_of_work}
                                onChange={(e) => form.setData('scope_of_work', e.target.value)}
                                maxLength={SCOPE_OF_WORK_MAX}
                                placeholder="e.g. Replace 3 RRUs and re-terminate fiber at the sector 2 antenna."
                                className="min-h-[260px] text-sm"
                                error={Boolean(form.errors.scope_of_work)}
                            />
                        </FormField>

                        <FormField
                            label="Site Access Notes"
                            htmlFor="site_access_notes"
                            maxLength={SITE_ACCESS_NOTES_MAX}
                            valueLength={form.data.site_access_notes.length}
                            error={form.errors.site_access_notes}
                            helper="Gate codes, check-in steps, parking and route notes for the crew."
                            badge={
                                <ClearFieldButton
                                    visible={form.data.site_access_notes !== ''}
                                    onClick={() => form.setData('site_access_notes', '')}
                                />
                            }
                            className="rounded-xl border bg-card p-4 shadow-2xs"
                        >
                            {/* ~1,200 characters visible before the field itself scrolls. */}
                            <Textarea
                                id="site_access_notes"
                                value={form.data.site_access_notes}
                                onChange={(e) =>
                                    form.setData('site_access_notes', e.target.value)
                                }
                                maxLength={SITE_ACCESS_NOTES_MAX}
                                placeholder="e.g. Check in at the north gate, gate code #4921, then gravel road 200m east."
                                className="min-h-[300px] text-sm"
                                error={Boolean(form.errors.site_access_notes)}
                            />
                        </FormField>
                    </>
                ) : (
                    <>
                        <SiteAccessBlock
                            icon={<Compass className="size-3 text-teal-600 dark:text-teal-400" />}
                            label="Scope of Work"
                            text={(scopeOfWork ?? '').trim()}
                            empty="No scope of work captured yet."
                            copied={copied === 'scope_of_work'}
                            onCopy={() => handleCopy(scopeOfWork ?? '', 'scope_of_work')}
                        />
                        <SiteAccessBlock
                            icon={<Navigation className="size-3 text-teal-600 dark:text-teal-400" />}
                            label="Site Access Notes"
                            text={(siteAccessNotes ?? '').trim()}
                            empty="No site access notes captured yet."
                            copied={copied === 'site_access_notes'}
                            onCopy={() => handleCopy(siteAccessNotes ?? '', 'site_access_notes')}
                        />
                    </>
                )}
            </div>

            {canEdit && (
                <DialogFooter className="border-t bg-muted/20 p-3.5 sm:justify-end">
                    {editing ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={stopEditing}
                                disabled={form.processing}
                                className="h-8 text-xs"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                loading={form.processing}
                                className="h-8 text-xs bg-indigo-600 text-white font-semibold hover:bg-indigo-700"
                            >
                                Save
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={startEditing}
                            className="h-8 cursor-pointer gap-1.5 text-xs"
                            title="Edit the scope of work and site access notes."
                        >
                            <Pencil className="size-3.5" /> Edit
                        </Button>
                    )}
                </DialogFooter>
            )}
        </form>
    );
}

export default SiteAccessForm;
