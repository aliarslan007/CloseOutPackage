import axios from 'axios';
import type { PingsResponse } from '../types/tracking.types';

export const PING_INTERVAL_MS = 15_000;
export const POLL_INTERVAL_MS = 10_000;

/** Accuracy above this is a junk fix — accept approximate laptop/network fixes up to 5km (m). */
export const MAX_ACCURACY_M = 5000;

/** Movement below this is noise — skip the network call (m). */
export const MIN_MOVEMENT_M = 5;

export function pingLocation(projectId: number, payload: {
    latitude: number;
    longitude: number;
    accuracy: number | null;
}): Promise<void> {
    return axios.post(
        route('projects.tracking.ping', { project: projectId }),
        { ...payload, status: 'active' },
        { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    );
}

export function markOffline(projectId: number): void {
    if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function') {
        return;
    }
    // sendBeacon survives page unload; auth cookie rides along same-origin.
    try {
        navigator.sendBeacon(
            route('projects.tracking.offline', { project: projectId }),
            new Blob([], { type: 'application/json' }),
        );
    } catch {
        // Beacon failure during page lifecycle teardown is non-blocking.
    }
}

export function fetchPings(projectId: number): Promise<PingsResponse> {
    return axios
        .get(route('projects.tracking.pings', { project: projectId }))
        .then((res) => res.data as PingsResponse);
}

export function fetchAllPings(projectId?: number | null): Promise<PingsResponse> {
    const params = typeof projectId === 'number' ? { project_id: projectId } : {};
    return axios
        .get(route('tracking.pings', params))
        .then((res) => res.data as PingsResponse);
}

/** Haversine distance in metres — cheap and sufficient for movement gating. */
export function distanceMeters(aLat: number, aLng: number, bLat: number, bLng: number): number {
    const toRad = (deg: number): number => (deg * Math.PI) / 180;
    const earthRadiusM = 6_371_000;
    const dLat = toRad(bLat - aLat);
    const dLng = toRad(bLng - aLng);
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng / 2) ** 2;

    return 2 * earthRadiusM * Math.asin(Math.sqrt(h));
}