import { Calendar, MapPin, HardDrive, FileType, ExternalLink, Trash2, Paintbrush } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { canDeleteMediaAsset, usePermissions } from '@/lib/permissions';
import type { MediaAsset } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';
import { UploaderProfileCard } from './UploaderProfileCard';

export interface MediaAssetDetailProps {
    asset: MediaAsset | null;
    isOpen: boolean;
    onClose: () => void;
    onDeleteRequest?: (asset: MediaAsset) => void;
    onAnnotate?: (asset: MediaAsset) => void;
}

export function MediaAssetDetail({
    asset,
    isOpen,
    onClose,
    onDeleteRequest,
    onAnnotate,
}: MediaAssetDetailProps) {
    const { user } = usePermissions();

    if (!asset) return null;

    const fileUrl = asset.url || (asset.id ? route('media-library.file', { asset: asset.id }) : '');
    const hasGps = asset.gps_latitude !== null && asset.gps_longitude !== null;
    const mapsUrl = hasGps
        ? `https://www.google.com/maps?q=${asset.gps_latitude},${asset.gps_longitude}`
        : null;

    const formattedDate = asset.captured_at
        ? new Date(asset.captured_at).toLocaleString()
        : new Date(asset.created_at).toLocaleString();

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-3xl overflow-hidden p-0 gap-0">
                <DialogHeader className="border-b border-border p-4 flex flex-row items-center justify-between">
                    <DialogTitle className="truncate text-base font-semibold pr-4" title={asset.original_filename}>
                        {asset.original_filename}
                    </DialogTitle>
                </DialogHeader>

                <div className="grid grid-cols-1 md:grid-cols-2 max-h-[80vh] overflow-y-auto">
                    {/* Image Preview Container */}
                    <div className="relative flex items-center justify-center bg-black/90 p-4 min-h-[260px] md:min-h-[380px]">
                        <img
                            src={fileUrl}
                            alt={asset.original_filename}
                            className="max-h-[360px] w-auto max-w-full rounded-md object-contain shadow-lg"
                        />
                        <div className="absolute bottom-3 right-3 flex items-center gap-1.5">
                            {onAnnotate && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-7 gap-1 rounded-md bg-primary/90 text-primary-foreground text-xs hover:bg-primary"
                                    onClick={() => {
                                        onClose();
                                        onAnnotate(asset);
                                    }}
                                >
                                    <Paintbrush className="h-3 w-3" /> Edit in Canvas
                                </Button>
                            )}
                            <a
                                href={fileUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 rounded-md bg-black/60 backdrop-blur-xs px-2.5 py-1 text-xs text-white hover:bg-black/80"
                            >
                                <ExternalLink className="h-3.5 w-3.5" /> Full Resolution
                            </a>
                        </div>
                    </div>

                    {/* Metadata Details Column */}
                    <div className="p-5 space-y-4 flex flex-col justify-between">
                        <div className="space-y-4">
                            <UploaderProfileCard uploader={asset.owner ?? asset.user} />

                            <div>
                                <h4 className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                    File Information
                                </h4>
                                <div className="space-y-2 text-xs">
                                    <div className="flex items-center justify-between border-b border-border pb-1.5">
                                        <span className="text-muted-foreground flex items-center gap-1.5">
                                            <HardDrive className="h-3.5 w-3.5" /> File Size:
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {formatFileSize(asset.file_size_bytes)}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between border-b border-border pb-1.5">
                                        <span className="text-muted-foreground flex items-center gap-1.5">
                                            <FileType className="h-3.5 w-3.5" /> MIME Type:
                                        </span>
                                        <span className="font-mono text-foreground">{asset.mime_type}</span>
                                    </div>

                                    <div className="flex items-center justify-between border-b border-border pb-1.5">
                                        <span className="text-muted-foreground flex items-center gap-1.5">
                                            <Calendar className="h-3.5 w-3.5" /> Captured Date:
                                        </span>
                                        <span className="font-medium text-foreground">{formattedDate}</span>
                                    </div>

                                    {asset.annotation_layer?.nodes && asset.annotation_layer.nodes.length > 0 && (
                                        <div className="flex items-center justify-between border-b border-border pb-1.5">
                                            <span className="text-muted-foreground flex items-center gap-1.5">
                                                <Paintbrush className="h-3.5 w-3.5 text-primary" /> Annotations:
                                            </span>
                                            <span className="font-medium text-primary">
                                                {asset.annotation_layer.nodes.length} {asset.annotation_layer.nodes.length === 1 ? 'shape' : 'shapes'} (re-editable)
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Location / GPS Details */}
                            <div>
                                <h4 className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                    Geotag & Location
                                </h4>
                                {hasGps ? (
                                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-xs space-y-1.5">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground flex items-center gap-1">
                                                <MapPin className="h-3.5 w-3.5 text-primary" /> GPS Coordinates:
                                            </span>
                                            <span className="font-mono font-medium text-foreground">
                                                {asset.gps_latitude?.toFixed(5)}, {asset.gps_longitude?.toFixed(5)}
                                            </span>
                                        </div>
                                        {mapsUrl && (
                                            <a
                                                href={mapsUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-[11px] text-primary hover:underline pt-1"
                                            >
                                                View on Google Maps <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-xs text-muted-foreground italic">
                                        No GPS coordinates embedded in this image.
                                    </p>
                                )}
                            </div>

                            {/* Project Scope Information */}
                            <div>
                                <h4 className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    Project Scope
                                </h4>
                                <p className="text-xs text-foreground">
                                    {asset.project?.name || asset.market?.name || 'Unassigned / Global Gallery'}
                                </p>
                            </div>
                        </div>

                        {/* Footer Controls */}
                        <div className="pt-4 border-t border-border flex items-center justify-between">
                            {onDeleteRequest && canDeleteMediaAsset(user, asset) ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive gap-1.5"
                                    onClick={() => {
                                        onClose();
                                        onDeleteRequest(asset);
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" /> Delete
                                </Button>
                            ) : <div />}

                            <div className="flex items-center gap-2">
                                {onAnnotate && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="gap-1.5"
                                        onClick={() => {
                                            onClose();
                                            onAnnotate(asset);
                                        }}
                                    >
                                        <Paintbrush className="h-4 w-4" /> Edit in Canvas
                                    </Button>
                                )}
                                <Button type="button" variant="secondary" size="sm" onClick={onClose}>
                                    Close
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
