export type TrackingStatus = 'active' | 'idle' | 'offline';

export interface TechLocation {
    readonly userId: number;
    readonly userName: string;
    readonly userRole?: string | null;
    readonly userPhotoUrl: string | null;
    readonly userEmail?: string | null;
    readonly userPhone?: string | null;
    readonly projectId?: number;
    readonly projectName?: string | null;
    readonly latitude: number;
    readonly longitude: number;
    readonly accuracy: number | null;
    readonly status: TrackingStatus;
    readonly pingedAt: string;

    readonly user_id?: number;
    readonly user_name?: string;
    readonly user_role?: string | null;
    readonly user_email?: string | null;
    readonly user_phone?: string | null;
    readonly user_photo_url?: string | null;
    readonly project_id?: number;
    readonly project_name?: string | null;
    readonly pinged_at?: string;
}

export interface TrackingProject {
    readonly id: number;
    readonly hashId?: string;
    readonly name: string;
    readonly address: string;
}

export interface PingsResponse {
    data: TechLocation[];
}

export type GeoPermissionState = 'idle' | 'requesting' | 'streaming' | 'denied' | 'error';

/**
 * These three states describe whether the technician's *phone is reporting
 * location* — nothing to do with whether they are logged in. A technician can be
 * signed in all day and still read "Not Reporting" if their device stops sending
 * fixes, so the labels deliberately avoid "Online"/"Offline" wording.
 *
 * Server-side truth: `active` = fix within ~5 min, `idle` = 5–30 min since the
 * last fix, `offline` = older than the 30-min window or never sent a fix at all
 * (`LocationPing::ACTIVE_WINDOW_MINUTES`, `tracking:mark-idle`).
 */
export const STATUS_LABELS: Record<TrackingStatus, string> = {
    active: 'Live',
    idle: 'Paused',
    offline: 'Not Reporting',
};

/** Plain-language "what does this mean", shown as a tooltip on the badge. */
export const STATUS_DESCRIPTIONS: Record<TrackingStatus, string> = {
    active: 'Live — the technician’s phone is sending location right now.',
    idle: 'Paused — the phone stopped sending location a few minutes ago.',
    offline: 'Not Reporting — the phone is not sending location. This does not mean they are logged out.',
};

export const STATUS_RING_CLASSES: Record<TrackingStatus, string> = {
    active: 'ring-2 ring-emerald-500',
    idle: 'ring-2 ring-amber-500',
    offline: 'ring-2 ring-gray-500 opacity-60',
};