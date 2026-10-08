import { MapPin } from 'lucide-react';
import { cn } from '@/lib/utils';

export function GpsBadge({
    latitude,
    longitude,
    className,
}: Readonly<{
    latitude: number;
    longitude: number;
    className?: string;
}>) {
    const url = `https://www.google.com/maps?q=${latitude},${longitude}`;

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            title={`Open ${latitude.toFixed(6)}, ${longitude.toFixed(6)} on Google Maps`}
            className={cn(
                'inline-flex items-center gap-1 rounded-full bg-black/75 px-2 py-0.5 font-mono text-[10px] text-white backdrop-blur',
                'hover:bg-black/90 hover:underline focus-visible:outline-2 focus-visible:outline-offset-1',
                className
            )}
        >
            <MapPin className="size-3 shrink-0 text-emerald-400" />
            <span>
                {latitude.toFixed(4)}, {longitude.toFixed(4)}
            </span>
        </a>
    );
}
