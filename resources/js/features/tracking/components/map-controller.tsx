import * as React from 'react';
import { useMap } from 'react-leaflet';
import L from 'leaflet';
import { TechLocation } from '../types/tracking.types';

export interface MapControllerProps {
    locations?: TechLocation[];
    center?: [number, number];
    zoom?: number;
    autoFit?: boolean;
}

export function MapController({
    locations = [],
    center,
    zoom = 16,
    autoFit = true,
}: Readonly<MapControllerProps>) {
    const map = useMap();
    const hasInitialFitted = React.useRef(false);

    // Invalidate map size on mount and across animation frames to eliminate partial tile renders
    React.useEffect(() => {
        const triggerInvalidate = () => {
            if (map) {
                map.invalidateSize();
            }
        };

        // Immediate invalidation + staggered timers to handle initial render, flex reflow, and font loading
        triggerInvalidate();
        const timer1 = setTimeout(triggerInvalidate, 100);
        const timer2 = setTimeout(triggerInvalidate, 300);
        const timer3 = setTimeout(triggerInvalidate, 600);
        const timer4 = setTimeout(triggerInvalidate, 1000);

        // Listen for container resize via ResizeObserver
        let observer: ResizeObserver | null = null;
        const container = map.getContainer();
        if (typeof ResizeObserver !== 'undefined' && container) {
            observer = new ResizeObserver(() => {
                triggerInvalidate();
            });
            observer.observe(container);
        }

        window.addEventListener('resize', triggerInvalidate);

        return () => {
            clearTimeout(timer1);
            clearTimeout(timer2);
            clearTimeout(timer3);
            clearTimeout(timer4);
            if (observer) {
                observer.disconnect();
            }
            window.removeEventListener('resize', triggerInvalidate);
        };
    }, [map]);

    // Update map center or auto-fit bounds when locations change
    React.useEffect(() => {
        if (!map) {
            return;
        }

        const validLocs = locations.filter(
            (loc) =>
                typeof loc.latitude === 'number' &&
                typeof loc.longitude === 'number' &&
                (loc.latitude !== 0 || loc.longitude !== 0)
        );

        if (validLocs.length === 0) {
            if (center) {
                map.setView(center, zoom);
            }
            return;
        }

        if (validLocs.length === 1) {
            const single = validLocs[0];
            if (single) {
                map.setView([single.latitude, single.longitude], Math.max(map.getZoom(), 15));
            }
            return;
        }

        if (autoFit && !hasInitialFitted.current && validLocs.length > 1) {
            const bounds = L.latLngBounds(
                validLocs.map((loc) => [loc.latitude, loc.longitude] as [number, number])
            );
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
            hasInitialFitted.current = true;
        }
    }, [map, locations, center, zoom, autoFit]);

    return null;
}

export default MapController;
