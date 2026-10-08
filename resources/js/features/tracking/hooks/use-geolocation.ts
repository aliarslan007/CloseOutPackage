import { useCallback, useEffect, useRef, useState } from 'react';
import {
    distanceMeters,
    markOffline,
    MAX_ACCURACY_M,
    MIN_MOVEMENT_M,
    PING_INTERVAL_MS,
    pingLocation,
} from '../services/tracking.service';
import type { GeoPermissionState } from '../types/tracking.types';

interface Position {
    latitude: number;
    longitude: number;
    accuracy: number;
}

const LAST_POSITION_CACHE_KEY = 'tracking:last-position';

export interface UseGeolocationOptions {
    readonly enabled?: boolean;
}

/**
 * Field-tech side: reads GPS via watchPosition and POSTs each meaningful
 * fix. Handles the plan's state machine (idle | requesting → streaming | denied |
 * error), throttles junk fixes (accuracy + movement), and fires a beacon on
 * unmount so the manager map flips the tech to offline.
 */
export function useGeolocation(
    projectId: number | null | undefined,
    options: UseGeolocationOptions = {}
) {
    const { enabled = true } = options;
    const isTrackingEnabled = enabled && typeof projectId === 'number' && projectId > 0;

    const [state, setState] = useState<GeoPermissionState>(
        isTrackingEnabled ? 'requesting' : 'idle'
    );
    const lastPositionRef = useRef<Position | null>(null);
    const postedRef = useRef<Position | null>(null);

    const postIfWorthwhile = useCallback(
        (position: Position) => {
            if (!isTrackingEnabled || typeof projectId !== 'number') {
                return;
            }

            lastPositionRef.current = position;

            // Junk fix (low accuracy) — keep for reference but never send.
            if (position.accuracy > MAX_ACCURACY_M) {
                return;
            }

            // No meaningful movement from the last POSTed fix — skip.
            if (
                postedRef.current !== null &&
                distanceMeters(
                    postedRef.current.latitude,
                    postedRef.current.longitude,
                    position.latitude,
                    position.longitude
                ) < MIN_MOVEMENT_M
            ) {
                return;
            }

            postedRef.current = position;
            void pingLocation(projectId, position).catch(() => {
                setState('error');
            });
        },
        [projectId, isTrackingEnabled]
    );

    useEffect(() => {
        if (!isTrackingEnabled || typeof projectId !== 'number') {
            setState('idle');
            return;
        }

        if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
            setState('error');
            return;
        }

        setState('requesting');

        // Restore the last good fix from this session so the map has a marker
        // even while the first fresh fix is pending.
        const cached = sessionStorage.getItem(LAST_POSITION_CACHE_KEY);
        if (cached !== null) {
            try {
                lastPositionRef.current = JSON.parse(cached) as Position;
            } catch {
                // corrupt cache — ignore
            }
        }

        const watchId = navigator.geolocation.watchPosition(
            (raw) => {
                const position: Position = {
                    latitude: raw.coords.latitude,
                    longitude: raw.coords.longitude,
                    accuracy: raw.coords.accuracy ?? 0,
                };
                sessionStorage.setItem(LAST_POSITION_CACHE_KEY, JSON.stringify(position));
                setState('streaming');
                postIfWorthwhile(position);
            },
            (error) => {
                setState(error.code === error.PERMISSION_DENIED ? 'denied' : 'error');
            },
            { enableHighAccuracy: false, maximumAge: 60_000, timeout: 20_000 }
        );

        // Heartbeat interval: keep active window and manager map updated even when stationary.
        const heartbeatTimer = setInterval(() => {
            if (lastPositionRef.current) {
                void pingLocation(projectId, lastPositionRef.current).catch(() => {
                    setState('error');
                });
            }
        }, PING_INTERVAL_MS);

        return () => {
            clearInterval(heartbeatTimer);
            navigator.geolocation.clearWatch(watchId);
            markOffline(projectId);
        };
    }, [projectId, isTrackingEnabled, postIfWorthwhile]);

    const retry = useCallback(() => {
        if (!isTrackingEnabled || typeof projectId !== 'number') {
            return;
        }

        if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
            setState('error');
            return;
        }

        setState('requesting');
        navigator.geolocation.getCurrentPosition(
            (raw) => {
                const position: Position = {
                    latitude: raw.coords.latitude,
                    longitude: raw.coords.longitude,
                    accuracy: raw.coords.accuracy ?? 0,
                };
                postIfWorthwhile(position);
                setState('streaming');
            },
            (error) => setState(error.code === error.PERMISSION_DENIED ? 'denied' : 'error'),
            { enableHighAccuracy: false, timeout: 10_000, maximumAge: 60_000 }
        );
    }, [isTrackingEnabled, projectId, postIfWorthwhile]);

    return { state, retry };
}

export default useGeolocation;
