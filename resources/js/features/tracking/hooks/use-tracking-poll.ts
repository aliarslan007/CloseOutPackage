import { useEffect, useState } from 'react';
import { fetchAllPings, fetchPings, POLL_INTERVAL_MS } from '../services/tracking.service';
import type { TechLocation } from '../types/tracking.types';

/**
 * Manager side: polls /pings every 10 s while the Tracking page is mounted.
 * The cleanup clearInterval guarantees zero background requests once the
 * manager navigates away (same pattern as notification-center.tsx).
 *
 * On transient failure the last known locations are kept and `isConnected`
 * flips false so the UI can show the "disconnected — retrying" banner.
 */
export function normalizeTechLocation(raw: TechLocation): TechLocation {
    const userId = raw.userId ?? raw.user_id ?? 0;
    const userName = raw.userName || raw.user_name || 'Technician';
    const userRole = raw.userRole ?? raw.user_role ?? null;
    const userPhotoUrl = raw.userPhotoUrl ?? raw.user_photo_url ?? null;
    const userEmail = raw.userEmail ?? raw.user_email ?? null;
    const userPhone = raw.userPhone ?? raw.user_phone ?? null;
    const projectId = raw.projectId ?? raw.project_id;
    const projectName = raw.projectName ?? raw.project_name ?? null;
    const pingedAt = raw.pingedAt ?? raw.pinged_at ?? new Date().toISOString();

    return {
        userId,
        userName,
        userRole,
        userPhotoUrl,
        userEmail,
        userPhone,
        projectId,
        projectName,
        latitude: Number(raw.latitude) || 0,
        longitude: Number(raw.longitude) || 0,
        accuracy: typeof raw.accuracy === 'number' ? raw.accuracy : null,
        status: raw.status ?? 'active',
        pingedAt,
        user_id: userId,
        user_name: userName,
        user_role: userRole,
        user_photo_url: userPhotoUrl,
        user_email: userEmail,
        user_phone: userPhone,
        project_id: projectId,
        project_name: projectName,
        pinged_at: pingedAt,
    };
}

function toLocationMap(pings: TechLocation[]): Map<number, TechLocation> {
    const map = new Map<number, TechLocation>();
    if (!Array.isArray(pings)) {
        return map;
    }
    for (const raw of pings) {
        const normalized = normalizeTechLocation(raw);
        if (normalized.userId > 0) {
            map.set(normalized.userId, normalized);
        }
    }
    return map;
}

export function useTrackingPoll(projectId: number, initialPings: TechLocation[]) {
    const [locations, setLocations] = useState<Map<number, TechLocation>>(
        () => toLocationMap(initialPings),
    );
    const [isConnected, setIsConnected] = useState(true);

    useEffect(() => {
        const handleSuccess = (response: { data: TechLocation[] }): void => {
            setLocations(toLocationMap(response.data));
            setIsConnected(true);
        };

        const handleError = (): void => {
            setIsConnected(false);
        };

        const poll = (): void => {
            fetchPings(projectId).then(handleSuccess).catch(handleError);
        };

        poll();
        const timer = setInterval(poll, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [projectId]);

    return { locations, isConnected };
}

export function useGlobalTrackingPoll(
    initialPings: TechLocation[],
    projectId?: number | null,
) {
    const [locations, setLocations] = useState<Map<number, TechLocation>>(
        () => toLocationMap(initialPings),
    );
    const [isConnected, setIsConnected] = useState(true);

    useEffect(() => {
        setLocations(toLocationMap(initialPings));
    }, [initialPings]);

    useEffect(() => {
        const handleSuccess = (response: { data: TechLocation[] }): void => {
            setLocations(toLocationMap(response.data));
            setIsConnected(true);
        };

        const handleError = (): void => {
            setIsConnected(false);
        };

        const poll = (): void => {
            fetchAllPings(projectId).then(handleSuccess).catch(handleError);
        };

        poll();
        const timer = setInterval(poll, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [projectId]);

    return { locations, isConnected };
}

export default useTrackingPoll;