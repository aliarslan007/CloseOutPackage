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
import { router } from '@inertiajs/react';
import { Mail, Users } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ForwardCustomerOption {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string | null;
}

export interface ForwardIssuesToCustomerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    projectId: number | string;
    selectedIssueIds: number[];
    customers: ForwardCustomerOption[];
    onSuccess?: () => void;
}

function getInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

export function ForwardIssuesToCustomerDialog({
    open,
    onOpenChange,
    projectId,
    selectedIssueIds,
    customers,
    onSuccess,
}: Readonly<ForwardIssuesToCustomerDialogProps>) {
    const [recipientEmails, setRecipientEmails] = React.useState<string[]>([]);
    const [note, setNote] = React.useState('');
    const [processing, setProcessing] = React.useState(false);

    React.useEffect(() => {
        if (!open) {
            setRecipientEmails([]);
            setNote('');
            setProcessing(false);
        }
    }, [open]);

    const toggleRecipient = (email: string) => {
        setRecipientEmails((prev) =>
            prev.includes(email) ? prev.filter((e) => e !== email) : [...prev, email]
        );
    };

    const toggleAll = () => {
        if (recipientEmails.length === customers.length) {
            setRecipientEmails([]);
        } else {
            setRecipientEmails(customers.map((c) => c.email));
        }
    };

    const canSubmit = recipientEmails.length > 0 && selectedIssueIds.length > 0 && !processing;
    const issueCount = selectedIssueIds.length;
    const issuePlural = issueCount === 1 ? '' : 's';
    const buttonLabel = processing ? 'Forwarding...' : `Forward ${issueCount} Issue${issuePlural}`;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) {
            if (recipientEmails.length === 0) {
                toast.error('Select at least one customer recipient.');
            }
            return;
        }

        setProcessing(true);
        router.post(
            route('projects.issues.forward', projectId),
            {
                issue_ids: selectedIssueIds,
                recipient_emails: recipientEmails,
                note: note.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Issues forwarded to ${recipientEmails.length} customer(s).`);
                    onOpenChange(false);
                    onSuccess?.();
                },
                onError: () => {
                    toast.error('Failed to forward issues. Please try again.');
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                            <Mail className="size-4" />
                        </div>
                        <div>
                            <DialogTitle className="text-base font-bold">Forward Issues to Customer</DialogTitle>
                            <DialogDescription className="text-xs">
                                {issueCount} issue{issuePlural} selected • Choose Verizon contacts to notify
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Recipients */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-foreground">
                                Verizon Customers {customers.length > 0 && `(${recipientEmails.length}/${customers.length})`}
                            </span>
                            {customers.length > 0 && (
                                <button
                                    type="button"
                                    onClick={toggleAll}
                                    className="text-xs font-semibold text-primary hover:underline cursor-pointer"
                                >
                                    {recipientEmails.length === customers.length ? 'Deselect All' : 'Select All'}
                                </button>
                            )}
                        </div>

                        {customers.length === 0 ? (
                            <div className="py-8 text-center rounded-xl border border-dashed border-border bg-muted/20">
                                <Users className="size-7 mx-auto mb-2 text-muted-foreground/60" />
                                <p className="text-xs font-semibold text-foreground">No customer contacts assigned yet</p>
                                <p className="text-xs text-muted-foreground mt-1 max-w-xs mx-auto">
                                    Ask an admin or manager to grant a Verizon customer access to this project before forwarding.
                                </p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-2 max-h-56 overflow-y-auto pr-1">
                                {customers.map((customer) => {
                                    const isSelected = recipientEmails.includes(customer.email);
                                    return (
                                        <button
                                            key={customer.email}
                                            type="button"
                                            onClick={() => toggleRecipient(customer.email)}
                                            className={cn(
                                                'flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer text-left w-full',
                                                isSelected
                                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                                    : 'border-border bg-card hover:bg-muted/30'
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => {}}
                                                tabIndex={-1}
                                                className="size-4 rounded border-border text-primary focus:ring-primary/20 shrink-0 pointer-events-none"
                                            />
                                            {customer.profile_photo_url ? (
                                                <img
                                                    src={customer.profile_photo_url}
                                                    alt={customer.name}
                                                    className="size-8 rounded-full object-cover border border-border shrink-0 aspect-square"
                                                />
                                            ) : (
                                                <div className="size-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0">
                                                    {getInitials(customer.name)}
                                                </div>
                                            )}
                                            <div className="flex flex-col min-w-0 flex-1">
                                                <span className="text-xs font-bold text-foreground truncate">{customer.name}</span>
                                                <span className="text-xs text-muted-foreground truncate">{customer.email}</span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Manager note */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <label htmlFor="forward_note" className="text-xs font-bold text-foreground">
                                Manager Note <span className="font-normal text-muted-foreground">(Optional)</span>
                            </label>
                            <span className="text-[11px] text-muted-foreground">{note.length} / 2000</span>
                        </div>
                        <Textarea
                            id="forward_note"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            maxLength={2000}
                            rows={4}
                            placeholder="Add context for the customer — e.g. what action you need, timeline, or next steps..."
                            className="text-xs resize-none rounded-xl"
                        />
                        <p className="text-[11px] text-muted-foreground">
                            This note will appear as a highlighted callout at the top of the email.
                        </p>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            loading={processing}
                            disabled={!canSubmit}
                            className="text-xs font-semibold bg-foreground text-background hover:bg-foreground/90"
                        >
                            {buttonLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
