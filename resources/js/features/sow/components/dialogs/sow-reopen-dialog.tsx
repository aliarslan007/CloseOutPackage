import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Info, Undo2 } from 'lucide-react';
import * as React from 'react';

export interface SowReopenDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    technicianName?: string | null;
    loading?: boolean;
    onConfirm: (reason: string) => void;
}

const REOPEN_REASON_LIMIT = 1000;

export function SowReopenDialog({
    open,
    onOpenChange,
    technicianName,
    loading = false,
    onConfirm,
}: Readonly<SowReopenDialogProps>) {
    const [reason, setReason] = React.useState('');

    React.useEffect(() => {
        if (open) {
            setReason('');
        }
    }, [open]);

    const reasonLength = reason.length;
    const isNearLimit = reasonLength > 900;

    const handleConfirm = () => {
        onConfirm(reason.trim());
    };

    return (
        <Dialog open={open} onOpenChange={(val) => !loading && onOpenChange(val)}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="space-y-1.5">
                    <DialogTitle className="flex items-center gap-2 text-base font-semibold">
                        <Undo2 className="size-4 text-primary" />
                        Reopen Report for Technician Editing
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground leading-relaxed">
                        Reopening this report unlocks full edit access for the field technician
                        {technicianName ? <strong className="text-foreground"> ({technicianName})</strong> : ''}.
                        They will be able to correct survey checklist answers, update photos & annotations, and resubmit the report for manager review.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2 py-2">
                    <div className="flex items-center justify-between gap-2">
                        <label
                            htmlFor="sow-reopen-reason"
                            className="text-xs font-medium text-foreground flex items-center gap-1"
                        >
                            Notes or Reason for Technician
                            <span className="text-muted-foreground font-normal">(Optional)</span>
                        </label>
                        <span
                            className={cn(
                                'text-[10px] tabular-nums',
                                isNearLimit ? 'text-destructive font-semibold' : 'text-muted-foreground'
                            )}
                        >
                            {reasonLength} / {REOPEN_REASON_LIMIT}
                        </span>
                    </div>
                    <Textarea
                        id="sow-reopen-reason"
                        value={reason}
                        onChange={(e) => setReason(e.target.value.slice(0, REOPEN_REASON_LIMIT))}
                        maxLength={REOPEN_REASON_LIMIT}
                        placeholder="Explain what needs to be changed (e.g. Please take a clearer photo of the demarc box and clarify question #4)..."
                        rows={3}
                        className="text-xs resize-none"
                        disabled={loading}
                    />
                    <div className="flex items-start gap-2 rounded-lg border border-border/70 bg-muted/40 p-2.5 text-xs text-muted-foreground">
                        <Info className="size-3.5 shrink-0 mt-0.5 text-foreground/70" />
                        <span>
                            This note will be displayed prominently to the technician upon opening the report.
                        </span>
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={loading}
                        onClick={() => onOpenChange(false)}
                        className="text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        loading={loading}
                        onClick={handleConfirm}
                        className="text-xs gap-1.5"
                    >
                        {!loading && <Undo2 className="size-3.5" />}
                        Reopen for Technician
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
