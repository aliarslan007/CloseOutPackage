import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle2, ClipboardCheck, XCircle } from 'lucide-react';
import { CustomerSiteNoteImage } from '../types';

export interface ChecklistResponseItem {
    id: number;
    answer?: boolean | string | number | null;
    explain?: string | null;
    is_na?: boolean;
    quantity?: number | null;
    checklist_item?: {
        id: number;
        label?: string | null;
        name?: string | null;
        has_explain_field?: boolean;
        allows_na?: boolean;
    } | null;
    checklistItem?: {
        id: number;
        label?: string | null;
        name?: string | null;
        has_explain_field?: boolean;
        allows_na?: boolean;
    } | null;
    label?: string | null;
    item_label?: string | null;
}

interface CustomerChecklistTabProps {
    responses?: ChecklistResponseItem[];
    adminNotes?: string | null;
    noteImages?: CustomerSiteNoteImage[];
}

function renderChecklistStatus(res: ChecklistResponseItem): React.ReactElement {
    if (res.is_na) {
        return (
            <span className="inline-flex items-center gap-1 font-bold text-zinc-700 dark:text-zinc-300 bg-zinc-500/10 px-2.5 py-0.5 rounded-full border border-zinc-500/20 text-[11px]">
                Not Available
            </span>
        );
    }

    const isYes =
        res.answer === true ||
        res.answer === 'yes' ||
        res.answer === '1' ||
        res.answer === 1;

    if (isYes) {
        return (
            <span className="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-0.5 rounded-full border border-emerald-500/20 text-[11px]">
                <CheckCircle2 className="size-3.5" /> YES
            </span>
        );
    }

    const isNo =
        res.answer === false ||
        res.answer === 'no' ||
        res.answer === '0' ||
        res.answer === 0;

    if (isNo) {
        return (
            <span className="inline-flex items-center gap-1 font-bold text-destructive bg-destructive/10 px-2.5 py-0.5 rounded-full border border-destructive/20 text-[11px]">
                <XCircle className="size-3.5" /> NO
            </span>
        );
    }

    return (
        <span className="font-semibold text-muted-foreground">
            {res.answer ?? '—'}
        </span>
    );
}

export function CustomerChecklistTab({
    responses = [],
    adminNotes,
    noteImages = [],
}: Readonly<CustomerChecklistTabProps>) {
    return (
        <div className="space-y-4">
            <Card className="overflow-hidden rounded-md border border-border/80 shadow-2xs text-xs">
                <CardHeader className="p-3 bg-muted/30 border-b border-border/60">
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-2">
                            <span className="flex size-5 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <ClipboardCheck className="size-3" />
                            </span>
                            Site Quality Verification Checklist
                        </CardTitle>
                        <Badge variant="outline" className="text-[10px] font-bold tabular-nums">
                            {responses.length} Checklist Items
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent className="p-0">
                    {responses.length === 0 ? (
                        <p className="p-6 text-center text-muted-foreground">
                            No checklist responses available for this package.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-muted/20 text-muted-foreground uppercase text-[10px] tracking-wider border-b border-border/60">
                                    <tr>
                                        <th className="p-3 w-3/4">Requirement / Verification Item</th>
                                        <th className="p-3 w-1/4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60 text-xs">
                                    {responses.map((res, idx) => {
                                        const itemLabel =
                                            res.checklist_item?.label ||
                                            res.checklist_item?.name ||
                                            res.checklistItem?.label ||
                                            res.checklistItem?.name ||
                                            res.label ||
                                            res.item_label ||
                                            `Checklist Item #${idx + 1}`;

                                        return (
                                            <React.Fragment key={res.id || idx}>
                                                <tr className="hover:bg-muted/10 transition-colors">
                                                    <td className="p-3 font-medium text-foreground">
                                                        {itemLabel}
                                                    </td>
                                                    <td className="p-3 text-center whitespace-nowrap">
                                                        {renderChecklistStatus(res)}
                                                    </td>
                                                </tr>
                                                {!res.is_na && res.quantity != null && (
                                                    <tr className="bg-muted/10">
                                                        <td colSpan={2} className="p-2.5 pl-5 text-[11px]">
                                                            <span className="font-bold text-muted-foreground mr-2">QUANTITY:</span>
                                                            <span className="text-foreground">{res.quantity}</span>
                                                        </td>
                                                    </tr>
                                                )}
                                                {!res.is_na && res.explain && (
                                                    <tr className="bg-amber-500/5">
                                                        <td colSpan={2} className="p-2.5 pl-5 text-[11px]">
                                                            <span className="font-bold text-amber-600 dark:text-amber-400 mr-2">EXPLAIN:</span>
                                                            <span className="text-foreground">{res.explain}</span>
                                                        </td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Additional Notes and Comments Block */}
            <div className="rounded-md border-2 border-black overflow-hidden bg-white text-black shadow-card">
                <div className="bg-black text-white px-3 py-1.5 text-xs font-black uppercase tracking-wider">
                    ADDITIONAL NOTES AND COMMENTS
                </div>
                <div className="p-3.5 space-y-3 text-xs font-sans leading-relaxed text-black break-words">
                    {adminNotes && adminNotes.trim() !== '' && adminNotes.trim() !== '&nbsp;' ? (
                        <div className="whitespace-pre-wrap">{adminNotes}</div>
                    ) : (
                        noteImages.length === 0 && (
                            <span className="text-gray-500 italic">No additional notes available.</span>
                        )
                    )}

                    {/* Site-note images the technician/manager attached, read-only. */}
                    {noteImages.length > 0 && (
                        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                            {noteImages.map((image) => (
                                <a
                                    key={image.id}
                                    href={image.url || image.thumb_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label={`Open ${image.file_name ?? 'image'} in a new tab`}
                                    title="Open full size in a new tab"
                                    className="group relative block aspect-square overflow-hidden rounded-lg border border-border/60 bg-muted/20 cursor-zoom-in"
                                >
                                    <img
                                        src={image.thumb_url || image.url}
                                        alt={image.file_name || 'Site note image'}
                                        loading="lazy"
                                        className="size-full object-cover transition-transform group-hover:scale-105"
                                    />
                                </a>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default CustomerChecklistTab;
