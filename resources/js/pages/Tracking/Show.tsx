import { MapController } from '@/features/tracking/components/map-controller';
import { MapZoomControl } from '@/features/tracking/components/map-zoom-control';
import { NoTechsState } from '@/features/tracking/components/no-techs-state';
import { TechInfoCard } from '@/features/tracking/components/tech-info-card';
import { TechnicianMarker } from '@/features/tracking/components/technician-marker';
import { useTrackingPoll } from '@/features/tracking/hooks/use-tracking-poll';
import { TechLocation, TrackingProject } from '@/features/tracking/types/tracking.types';
import { disperseCoLocatedLocations } from '@/features/tracking/utils/dispersion';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, usePage } from '@inertiajs/react';
import 'leaflet/dist/leaflet.css';
import { Radio, WifiOff } from 'lucide-react';
import * as React from 'react';
import { MapContainer, TileLayer } from 'react-leaflet';

export type TrackingShowProps = {
    project: TrackingProject;
    pings: { data: TechLocation[] };
};

export type TrackingShowPageProps = PageProps<TrackingShowProps>;

export function TrackingShow() {
    const { project, pings } = usePage<TrackingShowPageProps>().props;
    const initialPings = React.useMemo(() => (pings?.data ?? []) as TechLocation[], [pings]);
    const { locations, isConnected } = useTrackingPoll(project.id, initialPings);

    const techs = React.useMemo(() => Array.from(locations.values()), [locations]);
    const mapTechs = React.useMemo(
        () => techs.filter((t) => t.latitude !== 0 || t.longitude !== 0),
        [techs]
    );
    const dispersedTechs = React.useMemo(() => disperseCoLocatedLocations(mapTechs), [mapTechs]);

    const activeCount = React.useMemo(
        () => techs.filter((t) => t.status === 'active').length,
        [techs]
    );

    const mapCenter = React.useMemo((): [number, number] => {
        if (mapTechs.length > 0 && typeof mapTechs[0]?.latitude === 'number' && mapTechs[0].latitude !== 0) {
            return [mapTechs[0].latitude, mapTechs[0].longitude];
        }
        return [39.8283, -98.5795];
    }, [mapTechs]);

    return (
        <AppLayout
            title={`Tracking — ${project.name}`}
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', project.id) },
                { label: 'Tracking' },
            ]}
            headerCenter={
                <div className="flex flex-wrap items-center gap-1.5 sm:gap-2 text-xs">
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                        <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
                        <span>{activeCount} Active</span>
                    </div>
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-muted/60 border border-border/80 px-2.5 py-1 text-muted-foreground text-xs font-medium">
                        <span>{techs.length} Assigned</span>
                    </div>
                    {!isConnected && (
                        <div className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                            <WifiOff className="size-3.5" />
                            <span>Reconnecting…</span>
                        </div>
                    )}
                </div>
            }
        >
            <Head title={`Tracking — ${project.name}`} />

            <div className="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-3">
                {/* Tech list sidebar */}
                <aside className="space-y-2 rounded-xl border border-border/80 bg-card p-3 overflow-y-auto max-h-[300px] lg:max-h-[calc(100vh-140px)]">
                    <div className="flex items-center justify-between pb-1">
                        <h2 className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Radio className="size-3 text-primary" /> Assigned Crew
                        </h2>
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                            {activeCount} Active · {techs.length} Total
                        </span>
                    </div>
                    {techs.length === 0 ? (
                        <p className="text-[11px] text-muted-foreground">
                            No technicians assigned to this project yet.
                        </p>
                    ) : (
                        techs.map((location) => (
                            <TechInfoCard key={location.userId} location={location} />
                        ))
                    )}
                </aside>

                {/* Leaflet map */}
                <div
                    className="isolate relative z-0 overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm"
                    style={{ height: 'calc(100vh - 140px)', minHeight: 480 }}
                >
                    {mapTechs.length === 0 ? (
                        <NoTechsState projectName={project.name} />
                    ) : (
                        <MapContainer
                            center={mapCenter}
                            zoom={16}
                            minZoom={2}
                            maxZoom={18}
                            zoomControl={false}
                            className="size-full"
                            scrollWheelZoom={true}
                        >
                            <TileLayer
                                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                            />
                            <MapController locations={dispersedTechs} center={mapCenter} />
                            <MapZoomControl minZoom={2} maxZoom={18} />
                            {dispersedTechs.map((loc) => (
                                <TechnicianMarker
                                    key={loc.userId}
                                    location={loc}
                                    pixelOffsetX={loc.pixelOffsetX}
                                    pixelOffsetY={loc.pixelOffsetY}
                                />
                            ))}
                        </MapContainer>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

export default TrackingShow;
