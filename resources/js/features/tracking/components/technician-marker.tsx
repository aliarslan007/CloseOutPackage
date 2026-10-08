import { cn } from '@/lib/utils';
import L from 'leaflet';
import { Clock, Crosshair, Mail, MapPin, Phone, PhoneCall } from 'lucide-react';
import { useMemo } from 'react';
import { Marker, Popup } from 'react-leaflet';
import { TechLocation, TrackingStatus } from '../types/tracking.types';
import { CopyButton } from './copy-button';

const RING_BY_STATUS: Record<TrackingStatus, string> = {
    active: '#22c55e',
    idle: '#f59e0b',
    offline: '#6b7280',
};

function formatRoleName(role?: string | null): string {
    if (!role) return 'Supervisor';
    if (role === 'field_supervisor') return 'Supervisor';
    if (role === 'manager') return 'Manager';
    if (role === 'admin') return 'Admin';
    return role.replace(/_/g, ' ');
}

function avatarHtml(photoUrl: string | null | undefined, status: TrackingStatus, name?: string | null): string {
    const safeName = (name || 'Technician').trim();
    const initial = (safeName.charAt(0) || 'T').toUpperCase();
    const photo = photoUrl
        ? `<img src="${photoUrl}" alt="" style="width: 26px; height: 26px; min-width: 26px; min-height: 26px; max-width: 26px; max-height: 26px; border-radius: 50%; object-fit: cover; aspect-ratio: 1/1; display: block;" />`
        : `<span style="width: 26px; height: 26px; min-width: 26px; min-height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: #2563eb; color: #ffffff; font-size: 11px; font-weight: bold; aspect-ratio: 1/1;">${initial}</span>`;

    const ringColor = RING_BY_STATUS[status] ?? '#22c55e';

    return `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; width: 32px; height: 46px;">
            <div class="tech-avatar-circle" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; max-width: 32px; max-height: 32px; border-radius: 50%; padding: 1px; background-color: #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.25); border: 2.5px solid ${ringColor}; display: flex; align-items: center; justify-content: center; box-sizing: border-box; overflow: hidden; aspect-ratio: 1/1;">
                ${photo}
            </div>
            <div style="margin-top: 1px; width: 2px; height: 4px; background-color: #6b7280;"></div>
            <div style="width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 7px solid ${ringColor};"></div>
        </div>`;
}

/**
 * Custom DivIcon avatar bubble (plan 3-C) — 32px circle + stem + status-ring
 * colour. Uses a plain string HTML icon so no default Leaflet asset bundling
 * is needed (avoids the classic broken marker image).
 */
export interface TechnicianMarkerProps {
    location: TechLocation;
    pixelOffsetX?: number;
    pixelOffsetY?: number;
    displayLat?: number;
    displayLng?: number;
}

export function TechnicianMarker({
    location,
    pixelOffsetX = 0,
    pixelOffsetY = 0,
    displayLat,
    displayLng,
}: Readonly<TechnicianMarkerProps>) {
    const safeName = location.userName || location.user_name || 'Technician';
    const initial = (safeName.charAt(0) || 'T').toUpperCase();
    const photoUrl = location.userPhotoUrl ?? location.user_photo_url ?? null;
    const userRole = location.userRole ?? location.user_role;
    const userEmail = location.userEmail ?? location.user_email;
    const userPhone = location.userPhone ?? location.user_phone;
    const projectName = location.projectName ?? location.project_name;
    const pingedAt = location.pingedAt ?? location.pinged_at;
    const lat = displayLat ?? location.latitude;
    const lng = displayLng ?? location.longitude;

    const icon = useMemo(
        () =>
            L.divIcon({
                className: 'tracking-marker',
                html: avatarHtml(photoUrl, location.status, safeName),
                iconSize: [32, 46],
                iconAnchor: [16 - pixelOffsetX, 44 - pixelOffsetY],
                popupAnchor: [pixelOffsetX, -42 + pixelOffsetY],
            }),
        [photoUrl, location.status, safeName, pixelOffsetX, pixelOffsetY]
    );

    return (
        <Marker position={[lat, lng]} icon={icon}>
            <Popup className="custom-tech-popup" minWidth={260} maxWidth={280} closeButton={true}>
                <div className="flex flex-col text-xs text-foreground">
                    {/* Header: Photo + Name + Status & Role */}
                    <div className="flex items-center gap-2.5 pr-3.5">
                        {photoUrl ? (
                            <img
                                src={photoUrl}
                                alt={safeName}
                                className="size-10 rounded-full object-cover shrink-0 border border-border/80"
                            />
                        ) : (
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm border border-border/80">
                                {initial}
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <h3 className="font-heading text-sm font-bold text-foreground leading-tight truncate" title={safeName}>
                                {safeName}
                            </h3>
                            <div className="flex items-center gap-1.5 mt-0.5">
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded-full px-1.5 py-0.2 text-[9px] font-bold uppercase tracking-wider',
                                        location.status === 'active' && 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
                                        location.status === 'idle' && 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
                                        location.status === 'offline' && 'bg-muted text-muted-foreground'
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            location.status === 'active' && 'bg-emerald-500 animate-pulse',
                                            location.status === 'idle' && 'bg-amber-500',
                                            location.status === 'offline' && 'bg-muted-foreground'
                                        )}
                                    />
                                    {location.status}
                                </span>
                                <span className="text-[11px] text-muted-foreground capitalize font-normal truncate">
                                    {formatRoleName(userRole)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Site / Project Location Row */}
                    <div className="flex items-center gap-1.5 text-[11px] text-foreground/85 mt-1.5">
                        <MapPin className="size-3.5 shrink-0 text-muted-foreground" />
                        <span className="truncate font-medium">{projectName ?? 'Assigned Cell Site'}</span>
                    </div>

                    {/* Contact Details Box */}
                    <div className="mt-1.5 rounded-md border border-border/70 bg-background/50 p-2 space-y-1.5">
                        <p className="text-[9px] font-bold tracking-wider text-muted-foreground uppercase leading-none">
                            CONTACT DETAILS
                        </p>

                        {userEmail ? (
                            <div className="flex items-center justify-between gap-1.5">
                                <div className="flex items-center gap-1.5 min-w-0 flex-1 text-foreground/90">
                                    <Mail className="size-3.5 shrink-0 text-muted-foreground" />
                                    <a
                                        href={`mailto:${userEmail}`}
                                        title={userEmail}
                                        className="truncate text-[11px] font-normal hover:text-primary hover:underline"
                                    >
                                        {userEmail}
                                    </a>
                                </div>
                                <CopyButton value={userEmail} label="Email" className="size-5 p-0.5" />
                            </div>
                        ) : (
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <Mail className="size-3.5 shrink-0 text-muted-foreground/60" />
                                <span className="text-[11px] text-muted-foreground/80 italic">No email provided</span>
                            </div>
                        )}

                        <div className="border-t border-border/50" />

                        {userPhone ? (
                            <div className="flex items-center justify-between gap-1.5">
                                <div className="flex items-center gap-1.5 min-w-0 flex-1">
                                    <Phone className="size-3.5 shrink-0 text-muted-foreground" />
                                    <span className="font-mono text-[11px] font-medium text-foreground truncate">{userPhone}</span>
                                    <span className="rounded bg-muted px-1 py-0.2 text-[9px] text-muted-foreground font-normal">
                                        Primary
                                    </span>
                                </div>
                                <div className="flex items-center gap-1 shrink-0">
                                    <CopyButton value={userPhone} label="Phone number" className="size-5 p-0.5" />
                                    <a
                                        href={`tel:${userPhone.replace(/\s+/g, '')}`}
                                        title={`Call ${safeName}`}
                                        aria-label={`Call ${safeName}`}
                                        className="tech-call-btn inline-flex items-center gap-1 rounded-full bg-emerald-600 hover:bg-emerald-700 active:scale-95 px-2.5 py-0.5 text-[11px] font-bold !text-white shadow-xs transition-all"
                                        style={{ color: '#ffffff' }}
                                    >
                                        <PhoneCall className="size-3 !text-white" style={{ color: '#ffffff' }} />
                                        <span className="!text-white font-bold" style={{ color: '#ffffff' }}>Call</span>
                                    </a>
                                </div>
                            </div>
                        ) : (
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <Phone className="size-3.5 shrink-0 text-muted-foreground/60" />
                                <span className="text-[11px] text-muted-foreground/80 italic">No phone provided</span>
                            </div>
                        )}
                    </div>

                    {/* Footer: Location Accuracy & Updated At */}
                    <div className="mt-1.5 pt-1 flex items-center justify-between gap-2 text-[10px] text-muted-foreground border-t border-border/40">
                        <div className="flex items-center gap-1">
                            <Crosshair className="size-3 shrink-0 text-muted-foreground" />
                            <span>Accuracy: {typeof location.accuracy === 'number' ? `±${Math.round(location.accuracy)} m` : '±1 m'}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <Clock className="size-3 shrink-0 text-muted-foreground" />
                            <span>
                                Updated: {pingedAt
                                    ? new Date(pingedAt).toLocaleTimeString([], {
                                          hour: '2-digit',
                                          minute: '2-digit',
                                          second: '2-digit',
                                      })
                                    : 'Just now'}
                            </span>
                        </div>
                    </div>
                </div>
            </Popup>
        </Marker>
    );
}
