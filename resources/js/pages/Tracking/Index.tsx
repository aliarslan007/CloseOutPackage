import { MapController } from '@/features/tracking/components/map-controller';
import { MapZoomControl } from '@/features/tracking/components/map-zoom-control';
import { NoTechsState } from '@/features/tracking/components/no-techs-state';
import { ProjectFilterDropdown } from '@/components/shared/project-filter-dropdown';
import { TechInfoCard } from '@/features/tracking/components/tech-info-card';
import { TechnicianMarker } from '@/features/tracking/components/technician-marker';
import { useGlobalTrackingPoll } from '@/features/tracking/hooks/use-tracking-poll';
import { TechLocation, TrackingProject } from '@/features/tracking/types/tracking.types';
import { disperseCoLocatedLocations } from '@/features/tracking/utils/dispersion';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, router, usePage } from '@inertiajs/react';
import 'leaflet/dist/leaflet.css';
import { Building2, Radio, WifiOff } from 'lucide-react';
import * as React from 'react';
import { MapContainer, TileLayer } from 'react-leaflet';

export type TrackingIndexProps = {
    projects: TrackingProject[];
    selectedProjectId: number | null;
    pings: { data: TechLocation[] };
};

export type TrackingIndexPageProps = PageProps<TrackingIndexProps>;

export function TrackingIndex() {
    const { projects, selectedProjectId, pings } = usePage<TrackingIndexPageProps>().props;
    const initialPings = React.useMemo(() => pings?.data ?? [], [pings]);
    const { locations, isConnected } = useGlobalTrackingPoll(initialPings, selectedProjectId);

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

    const handleProjectSelect = (projectId: number | 'all') => {
        // allowAll is false, so 'all' never reaches here — the guard keeps the
        // shared component's union type honest without widening the page.
        if (projectId === 'all') return;
        router.get(route('tracking.index'), { project_id: projectId }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const selectedProject = React.useMemo(
        () => projects.find((proj) => proj.id === selectedProjectId) ?? null,
        [projects, selectedProjectId]
    );

    const mapCenter = React.useMemo((): [number, number] => {
        if (mapTechs.length > 0 && typeof mapTechs[0]?.latitude === 'number' && mapTechs[0].latitude !== 0) {
            return [mapTechs[0].latitude, mapTechs[0].longitude];
        }
        return [39.8283, -98.5795]; // Geographical center of the contiguous US
    }, [mapTechs]);

    return (
        <AppLayout
            title="Live Field Tracking"
            breadcrumb={[{ label: 'Live Field Tracking' }]}
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
            headerActions={
                selectedProjectId !== null && projects.length > 0 ? (
                    <ProjectFilterDropdown
                        projects={projects}
                        selectedProjectId={selectedProjectId}
                        onSelectProject={handleProjectSelect}
                        allowAll={false}
                        className="h-8"
                    />
                ) : null
            }
        >
            <Head title="Live Field Tracking" />

            <div className="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-3">
                {/* Tech list sidebar */}
                <aside className="space-y-2 rounded-xl border border-border/80 bg-card p-3 overflow-y-auto max-h-[250px] sm:max-h-[300px] lg:max-h-[calc(100vh-140px)]">
                    <div className="flex items-center justify-between pb-1">
                        <h2 className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Radio className="size-3 text-primary" /> Assigned Crew
                        </h2>
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                            {activeCount} Active · {techs.length} Total
                        </span>
                    </div>

                    {techs.length === 0 ? (
                        <div className="py-8 text-center">
                            <Building2 className="mx-auto size-6 text-muted-foreground/50 mb-1" />
                            <p className="text-xs font-medium text-foreground">No assigned technicians</p>
                            <p className="text-[11px] text-muted-foreground mt-0.5">
                                Assigned field technicians for this project will appear here.
                            </p>
                        </div>
                    ) : (
                        techs.map((location) => (
                            <TechInfoCard key={`${location.projectId ?? 'g'}-${location.userId}`} location={location} />
                        ))
                    )}
                </aside>

                {/* Leaflet map */}
                <div
                    className="isolate relative z-0 overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm h-[380px] sm:h-[480px] lg:h-[calc(100vh-140px)] min-h-[320px]"
                >
                    {mapTechs.length === 0 ? (
                        <NoTechsState projectName={selectedProject?.name ?? 'this project'} />
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
                                    key={`${loc.projectId ?? 'm'}-${loc.userId}`}
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

export default TrackingIndex;
