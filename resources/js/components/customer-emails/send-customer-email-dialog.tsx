import * as React from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { EmailLogItem } from '@/components/email-logs/email-log-labels';
import { AlertTriangle, CalendarClock, Send } from 'lucide-react';

interface SendCustomerEmailDialogProps {
    log: EmailLogItem | null;
    open: boolean;
    /** Server-provided ISO time the presets are anchored to (stale-tab safe). */
    now: string;
    customerEmailsEnabled?: boolean;
    onClose: () => void;
}

/** Preset offsets in minutes, computed against the server-provided "now". */
const PRESETS: Array<{ minutes: number; label: string }> = [
    { minutes: 15, label: '15 min' },
    { minutes: 30, label: '30 min' },
    { minutes: 60, label: '1 hour' },
    { minutes: 120, label: '2 hours' },
    { minutes: 600, label: '10 hours' },
    { minutes: 1440, label: '1 day' },
    { minutes: 2880, label: '2 days' },
    { minutes: 4320, label: '3 days' },
];

/**
 * Convert a datetime-local input value to an ISO instant.
 */
function localToIso(localValue: string): string {
    return new Date(localValue).toISOString();
}

function getRouteName(isResend: boolean, mode: 'now' | 'schedule'): string {
    if (isResend) return 'manager.customer-emails.resend';
    if (mode === 'now') return 'manager.customer-emails.send';
    return 'manager.customer-emails.schedule';
}

function getSuccessMessage(isResend: boolean, mode: 'now' | 'schedule'): string {
    if (isResend) return 'Fresh copy of email queued for delivery.';
    if (mode === 'now') return 'Email queued for delivery.';
    return 'Email scheduled.';
}

function getDialogTitle(isResend: boolean, mode: 'now' | 'schedule'): string {
    if (isResend) return 'Resend email';
    if (mode === 'now') return 'Send email now';
    return 'Schedule email';
}

function getSubmitButtonLabel(isSubmitting: boolean, mode: 'now' | 'schedule'): string {
    if (isSubmitting) return 'Working…';
    if (mode === 'now') return 'Send now';
    return 'Schedule';
}

export function SendCustomerEmailDialog({
    log,
    open,
    now,
    customerEmailsEnabled = true,
    onClose,
}: Readonly<SendCustomerEmailDialogProps>) {
    const [mode, setMode] = React.useState<'now' | 'schedule'>('now');
    const [customTime, setCustomTime] = React.useState('');
    const [presetMinutes, setPresetMinutes] = React.useState<number | null>(null);
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [confirmDuplicate, setConfirmDuplicate] = React.useState(false);

    // Reset the form whenever a different row opens the dialog.
    React.useEffect(() => {
        if (open) {
            setMode('now');
            setCustomTime('');
            setPresetMinutes(null);
            setConfirmDuplicate(false);
        }
    }, [open, log?.id]);

    const isResend = Boolean(log && (log.status === 'sent' || log.status === 'cancelled'));
    const alreadySent = Boolean(log && (log.status === 'sent' || log.status === 'failed'));
    const minLocal = new Date(new Date(now).getTime() + 60_000).toISOString().slice(0, 16);

    const selectedIso = React.useMemo(() => {
        if (mode === 'now') return null;
        if (presetMinutes !== null) {
            return new Date(new Date(now).getTime() + presetMinutes * 60_000).toISOString();
        }
        return customTime ? localToIso(customTime) : null;
    }, [mode, presetMinutes, customTime, now]);

    if (!log) return null;

    const submit = () => {
        if (mode === 'schedule' && !selectedIso) return;

        // Duplicate sends need an explicit two-step confirm, rendered inline.
        if (mode === 'now' && alreadySent && !confirmDuplicate) {
            setConfirmDuplicate(true);
            return;
        }

        setIsSubmitting(true);

        const routeName = getRouteName(isResend, mode);
        const data = mode === 'schedule' ? { scheduled_at: selectedIso } : {};

        router.post(route(routeName, log.id), data, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(getSuccessMessage(isResend, mode));
                onClose();
            },
            onError: () => toast.error('Could not deliver this email. Please try again.'),
            onFinish: () => setIsSubmitting(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && !isSubmitting && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{getDialogTitle(isResend, mode)}</DialogTitle>
                    <DialogDescription>
                        {log.subject} — {log.recipient_email}
                    </DialogDescription>
                </DialogHeader>

                {!customerEmailsEnabled && (
                    <div className="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-2.5 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <p className="font-medium">Customer emails are currently globally disabled</p>
                            <p className="mt-0.5 text-[11px] text-amber-700 dark:text-amber-400">
                                Delivery is suspended by server configuration (<code className="font-mono">CUSTOMER_EMAILS_ENABLED=false</code>).
                            </p>
                        </div>
                    </div>
                )}

                {!alreadySent && (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant={mode === 'now' ? 'default' : 'outline'}
                            size="sm"
                            className="flex-1 gap-1.5"
                            onClick={() => setMode('now')}
                        >
                            <Send className="size-3.5" /> Send now
                        </Button>
                        <Button
                            type="button"
                            variant={mode === 'schedule' ? 'default' : 'outline'}
                            size="sm"
                            className="flex-1 gap-1.5"
                            onClick={() => setMode('schedule')}
                        >
                            <CalendarClock className="size-3.5" /> Schedule
                        </Button>
                    </div>
                )}

                {mode === 'schedule' && (
                    <div className="space-y-3">
                        <div className="flex flex-wrap gap-1.5">
                            {PRESETS.map((preset) => (
                                <Button
                                    key={preset.minutes}
                                    type="button"
                                    variant={presetMinutes === preset.minutes ? 'default' : 'outline'}
                                    size="sm"
                                    className="h-7 px-2.5 text-[11px]"
                                    onClick={() => {
                                        setPresetMinutes(preset.minutes);
                                        setCustomTime('');
                                    }}
                                >
                                    +{preset.label}
                                </Button>
                            ))}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="scheduled-at" className="text-xs">
                                Or pick a custom time
                            </Label>
                            <Input
                                id="scheduled-at"
                                type="datetime-local"
                                value={customTime}
                                min={minLocal}
                                onChange={(e) => {
                                    setCustomTime(e.target.value);
                                    setPresetMinutes(null);
                                }}
                            />
                            {selectedIso && (
                                <p className="text-[11px] text-muted-foreground">
                                    Will send at {new Date(selectedIso).toLocaleString()}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {mode === 'now' && alreadySent && (
                    <div className="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-medium">This email was already sent{log.sent_at ? ` on ${new Date(log.sent_at).toLocaleDateString()}` : ''}.</p>
                            <p className="mt-0.5">Sending again delivers a fresh copy to the recipient. Confirm to continue.</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-2 h-7 text-[11px]"
                                disabled={confirmDuplicate}
                                onClick={() => setConfirmDuplicate(true)}
                            >
                                {confirmDuplicate ? 'Confirmed — press Send again' : 'Yes, send a fresh copy'}
                            </Button>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button type="button" variant="ghost" size="sm" onClick={onClose} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={submit}
                        disabled={
                            !customerEmailsEnabled ||
                            isSubmitting ||
                            (mode === 'schedule' && !selectedIso) ||
                            (mode === 'now' && alreadySent && !confirmDuplicate)
                        }
                    >
                        {getSubmitButtonLabel(isSubmitting, mode)}
                    </Button>
                    {mode === 'now' && !alreadySent && (
                        <p className="sr-only">This will queue the email immediately.</p>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
