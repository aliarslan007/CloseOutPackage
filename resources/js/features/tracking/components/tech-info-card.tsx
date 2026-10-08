import { Mail, Phone, PhoneCall } from 'lucide-react';
import type { TechLocation } from '../types/tracking.types';
import { CopyButton } from './copy-button';
import { TrackingStatusBadge } from './tracking-status-badge';

/**
 * Says what the pin means rather than repeating the badge. "Offline" used to
 * appear here too, which is the same logged-in-vs-not misread the badge caused.
 */
function formatLocationSubtitle(location: TechLocation, formattedTime: string | null): string {
    if (location.status === 'active') {
        const accuracyText =
            typeof location.accuracy === 'number' ? `±${Math.round(location.accuracy)} m · ` : '';

        return `${accuracyText}Location updating now`;
    }

    if (location.status === 'idle') {
        return formattedTime
            ? `Location paused · last sent ${formattedTime}`
            : 'Location paused';
    }

    if (formattedTime) {
        return `Last location ${formattedTime}`;
    }

    // Assigned but never reported: the case most likely to be read as "offline
    // worker", so name it explicitly.
    return 'Has not sent any location yet';
}

export function TechInfoCard({ location }: Readonly<{ location: TechLocation }>) {
    const safeName = (location.userName || location.user_name || 'Technician').trim();
    const initial = (safeName.charAt(0) || 'T').toUpperCase();
    const photoUrl = location.userPhotoUrl ?? location.user_photo_url ?? null;
    const userEmail = location.userEmail ?? location.user_email;
    const userPhone = location.userPhone ?? location.user_phone;
    const projectName = location.projectName ?? location.project_name;
    const pingedAt = location.pingedAt ?? location.pinged_at ?? null;

    const formattedTime = pingedAt
        ? new Date(pingedAt).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        })
        : null;

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-card border border-border/80 p-3 shadow-2xs transition-colors hover:border-border">
            <div className="flex items-center gap-2.5">
                {photoUrl ? (
                    <img
                        src={photoUrl}
                        alt={safeName}
                        className="size-10 shrink-0 rounded-full object-cover border border-border/70"
                    />
                ) : (
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-xs border border-border/70">
                        {initial}
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-1.5">
                        <p className="truncate text-xs font-bold text-foreground">
                            {safeName}
                        </p>
                        <TrackingStatusBadge status={location.status} />
                    </div>
                    {projectName && (
                        <p className="truncate text-[11px] font-medium text-muted-foreground mt-0.5">
                            {projectName}
                        </p>
                    )}
                    <p className="text-[10px] text-muted-foreground mt-0.5">
                        {formatLocationSubtitle(location, formattedTime)}
                    </p>
                </div>
            </div>

            {(userEmail || userPhone) && (
                <div className="flex flex-col gap-1.5 border-t border-border/50 pt-2 text-[11px]">
                    {userEmail && (
                        <div className="flex items-center justify-between gap-1.5 text-muted-foreground">
                            <div className="flex items-center gap-1.5 min-w-0 flex-1">
                                <Mail className="size-3.5 shrink-0 text-foreground/70" />
                                <a
                                    href={`mailto:${userEmail}`}
                                    title={userEmail}
                                    className="truncate hover:underline text-foreground text-xs font-normal"
                                >
                                    {userEmail}
                                </a>
                            </div>
                            <CopyButton value={userEmail} label="Email" className="size-6 p-1" />
                        </div>
                    )}
                    {userPhone && (
                        <div className="flex items-center justify-between gap-1.5 text-muted-foreground">
                            <div className="flex items-center gap-1.5 min-w-0 flex-1">
                                <Phone className="size-3.5 shrink-0 text-foreground/70" />
                                <span className="font-mono text-foreground text-xs font-semibold truncate">{userPhone}</span>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                                <CopyButton value={userPhone} label="Phone number" className="size-6 p-1" />
                                <a
                                    href={`tel:${userPhone.replace(/\s+/g, '')}`}
                                    title={`Call ${safeName}`}
                                    aria-label={`Call ${safeName} at ${userPhone}`}
                                    className="inline-flex items-center gap-1 rounded-full bg-emerald-600 hover:bg-emerald-700 active:scale-95 px-2.5 py-0.5 text-xs font-bold !text-white shadow-xs transition-colors"
                                    style={{ color: '#ffffff' }}
                                >
                                    <PhoneCall className="size-3 !text-white" style={{ color: '#ffffff' }} />
                                    <span className="!text-white font-bold" style={{ color: '#ffffff' }}>Call</span>
                                </a>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
