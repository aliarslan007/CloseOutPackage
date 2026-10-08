import * as React from 'react';
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
import { Label } from '@/components/ui/label';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export interface TechnicianOption {
    id: number;
    name: string;
    email?: string;
}

export interface ForwardTechnicianDialogProps {
    open: boolean;
    technicians?: TechnicianOption[];
    /** Technician already assigned to this round, preselected on reopen. */
    assignedToUserId?: number | null;
    isSubmitting?: boolean;
    onClose: () => void;
    onForward: (data: {
        assignedToUserId: number | null;
        instructions: string;
    }) => Promise<void> | void;
}

export function ForwardTechnicianDialog({
    open,
    technicians = [],
    assignedToUserId = null,
    isSubmitting = false,
    onClose,
    onForward,
}: Readonly<ForwardTechnicianDialogProps>) {
    const [selectedTechId, setSelectedTechId] = React.useState<string>('');
    const [instructions, setInstructions] = React.useState('');

    React.useEffect(() => {
        if (!open) return;

        // Reopening after a refresh must show who the round is already assigned
        // to, not reset to "All Project Technicians".
        if (assignedToUserId != null && technicians.some((t) => t.id === assignedToUserId)) {
            setSelectedTechId(String(assignedToUserId));
            return;
        }

        setSelectedTechId(technicians.length === 1 ? String(technicians[0].id) : '');
    }, [open, technicians, assignedToUserId]);

    const handleConfirm = async () => {
        const trimmed = instructions.trim();
        if (!trimmed) {
            toast.error(
                'Please enter instructions for the technician explaining what needs revision.'
            );
            return;
        }

        try {
            await onForward({
                assignedToUserId: selectedTechId ? Number(selectedTechId) : null,
                instructions: trimmed,
            });
            onClose();
            setInstructions('');
        } catch {
            toast.error('Failed to assign feedback.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={(val) => !val && !isSubmitting && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="space-y-1.5">
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-foreground">
                        <span>Assign Review to Field Technician</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Delegate customer change requests to a technician for field correction or
                        replacement photos.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3.5 py-1">
                    {technicians.length > 0 && (
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="tech-select"
                                className="text-xs font-semibold text-foreground"
                            >
                                Select Technician (Optional)
                            </Label>
                            <select
                                id="tech-select"
                                value={selectedTechId}
                                onChange={(e) => setSelectedTechId(e.target.value)}
                                className="w-full h-8 px-2 text-xs rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                disabled={isSubmitting}
                            >
                                <option value="">All Project Technicians</option>
                                {technicians.map((t) => (
                                    <option key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label
                            htmlFor="instructions"
                            className="text-xs font-semibold text-foreground"
                        >
                            Manager Instructions for Field Tech{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="instructions"
                            value={instructions}
                            onChange={(e) => setInstructions(e.target.value)}
                            placeholder="e.g. 'Customer marked the grounding bar on photo 3. Please re-terminate the ground wire and upload a new clear photo.'"
                            className="min-h-[85px] text-xs resize-none bg-muted/30 focus-visible:ring-1"
                            disabled={isSubmitting}
                        />
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t border-border">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onClose}
                        disabled={isSubmitting}
                        className="h-8 text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={handleConfirm}
                        disabled={isSubmitting}
                        className="h-8 text-xs font-semibold gap-1.5 shadow-xs"
                    >
                        {isSubmitting && <Loader2 className="size-3.5 animate-spin" />}
                        <span>Assign to Field Tech</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
