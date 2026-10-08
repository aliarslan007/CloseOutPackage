import type { TechLocation } from '../types/tracking.types';

export interface DispersedTechLocation extends TechLocation {
    pixelOffsetX: number;
    pixelOffsetY: number;
    isCoLocated?: boolean;
    coLocatedCount?: number;
}

const PROXIMITY_THRESHOLD = 0.0005; // ~50 meters in lat/lng coordinates

function areLocationsNear(a: TechLocation, b: TechLocation): boolean {
    return (
        Math.abs(a.latitude - b.latitude) <= PROXIMITY_THRESHOLD &&
        Math.abs(a.longitude - b.longitude) <= PROXIMITY_THRESHOLD
    );
}

function groupLocations(locations: TechLocation[]): TechLocation[][] {
    const visited = new Set<number>();
    const groups: TechLocation[][] = [];

    for (const loc of locations) {
        const id = loc.userId || loc.user_id || 0;
        if (!loc || visited.has(id)) continue;

        const group: TechLocation[] = [loc];
        visited.add(id);

        for (const other of locations) {
            const otherId = other.userId || other.user_id || 0;
            if (other && !visited.has(otherId) && areLocationsNear(loc, other)) {
                group.push(other);
                visited.add(otherId);
            }
        }

        groups.push(group);
    }

    return groups;
}

function calculatePixelOffset(index: number, count: number): { offsetX: number; offsetY: number } {
    if (count <= 1) {
        return { offsetX: 0, offsetY: 0 };
    }

    if (count === 2) {
        const sign = index === 0 ? -1 : 1;
        return { offsetX: sign * 20, offsetY: 0 };
    }

    const radius = 24;
    const angle = (2 * Math.PI * index) / count - Math.PI / 2;
    return {
        offsetX: Math.round(Math.cos(angle) * radius),
        offsetY: Math.round(Math.sin(angle) * radius),
    };
}

/**
 * Detects co-located GPS points and calculates deterministic pixel-based
 * screen offsets for Leaflet DivIcons so multiple technicians on the same
 * site are always 100% visible and never occlude each other at any zoom level.
 */
export function disperseCoLocatedLocations(locations: TechLocation[]): DispersedTechLocation[] {
    const groups = groupLocations(locations);
    const result: DispersedTechLocation[] = [];

    for (const group of groups) {
        const count = group.length;

        group.forEach((loc, index) => {
            const { offsetX, offsetY } = calculatePixelOffset(index, count);
            result.push({
                ...loc,
                pixelOffsetX: offsetX,
                pixelOffsetY: offsetY,
                isCoLocated: count > 1,
                coLocatedCount: count,
            });
        });
    }

    return result;
}
