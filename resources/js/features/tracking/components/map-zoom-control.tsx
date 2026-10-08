import * as React from 'react';
import { useMap, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import { Minus, Plus } from 'lucide-react';

export interface MapZoomControlProps {
    minZoom?: number;
    maxZoom?: number;
}

export function MapZoomControl({
    minZoom = 2,
    maxZoom = 18,
}: Readonly<MapZoomControlProps>) {
    const map = useMap();
    const [currentZoom, setCurrentZoom] = React.useState<number>(() => map.getZoom());
    const controlRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        if (controlRef.current) {
            L.DomEvent.disableClickPropagation(controlRef.current);
            L.DomEvent.disableScrollPropagation(controlRef.current);
        }
    }, []);

    useMapEvents({
        zoom: () => setCurrentZoom(map.getZoom()),
        zoomend: () => setCurrentZoom(map.getZoom()),
    });

    const zoomPercentage = React.useMemo(() => {
        const pct = ((currentZoom - minZoom) / (maxZoom - minZoom)) * 100;
        return Math.round(Math.min(100, Math.max(0, pct)));
    }, [currentZoom, minZoom, maxZoom]);

    const handleZoomIn = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        e.preventDefault();
        map.zoomIn();
    };

    const handleZoomOut = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        e.preventDefault();
        map.zoomOut();
    };

    return (
        <div className="leaflet-top leaflet-right pointer-events-auto">
            <div
                ref={controlRef}
                className="leaflet-control m-3 flex flex-col items-center overflow-hidden rounded-lg border border-border/80 bg-card text-card-foreground shadow-md backdrop-blur-sm select-none"
            >
                <button
                    type="button"
                    onClick={handleZoomIn}
                    disabled={currentZoom >= maxZoom}
                    className="flex size-8 items-center justify-center border-b border-border/60 hover:bg-muted active:bg-muted/80 disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer text-foreground"
                    title="Zoom In"
                    aria-label="Zoom In"
                >
                    <Plus className="size-4" strokeWidth={2} />
                </button>

                <div
                    className="flex min-w-[40px] items-center justify-center px-1 py-1 text-[11px] font-bold text-foreground bg-muted/40 select-none tracking-tight"
                    title={`Zoom Level: ${zoomPercentage}% (${Math.round(currentZoom)}/${maxZoom})`}
                    aria-label={`Zoom level ${zoomPercentage} percent`}
                >
                    {zoomPercentage}%
                </div>

                <button
                    type="button"
                    onClick={handleZoomOut}
                    disabled={currentZoom <= minZoom}
                    className="flex size-8 items-center justify-center border-t border-border/60 hover:bg-muted active:bg-muted/80 disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer text-foreground"
                    title="Zoom Out"
                    aria-label="Zoom Out"
                >
                    <Minus className="size-4" strokeWidth={2} />
                </button>
            </div>
        </div>
    );
}

export default MapZoomControl;
