import * as React from 'react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Camera, RotateCcw, Check, X, MapPin } from 'lucide-react';
import { CompressionService } from '@/services/compression.service';

export interface CapturedPhoto {
    file: File;
    previewUrl: string;
    latitude?: number;
    longitude?: number;
    capturedAt: string;
}

export interface InAppCameraProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCapture: (photo: CapturedPhoto) => void;
    categoryTitle?: string;
}

function renderCameraContent(
    error: string | null,
    captured: CapturedPhoto | null,
    videoRef: React.RefObject<HTMLVideoElement>
) {
    if (error) {
        return <div className="p-6 text-center text-sm text-destructive">{error}</div>;
    }
    if (captured) {
        return <img src={captured.previewUrl} alt="Captured" className="size-full object-contain" />;
    }
    return <video ref={videoRef} autoPlay playsInline muted className="size-full object-cover" />;
}

export function InAppCamera({
    open,
    onOpenChange,
    onCapture,
    categoryTitle = 'Site Photo',
}: Readonly<InAppCameraProps>) {
    const videoRef = React.useRef<HTMLVideoElement | null>(null);
    const canvasRef = React.useRef<HTMLCanvasElement | null>(null);
    const [captured, setCaptured] = React.useState<CapturedPhoto | null>(null);
    const [coords, setCoords] = React.useState<{ latitude?: number; longitude?: number }>({});
    const [error, setError] = React.useState<string | null>(null);

    // Initialize Camera Stream & Geolocation
    React.useEffect(() => {
        if (!open) {
            setCaptured(null);
            setError(null);
            return undefined;
        }

        let activeStream: MediaStream | null = null;

        // Fetch Geolocation
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (pos) => setCoords({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
                (err) => console.warn('GPS location access denied:', err.message),
                { enableHighAccuracy: false, timeout: 5000, maximumAge: 300_000 }
            );
        }

        // Access Back Camera
        navigator.mediaDevices
            ?.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
            })
            .then((mediaStream) => {
                activeStream = mediaStream;
                if (videoRef.current) {
                    videoRef.current.srcObject = mediaStream;
                }
            })
            .catch((err) => {
                console.error('Camera access error:', err);
                setError('Unable to access camera. Please check camera permissions.');
            });

        return () => {
            if (activeStream) {
                activeStream.getTracks().forEach((t) => t.stop());
            }
        };
    }, [open]);

    const handleTakePhoto = async () => {
        if (!videoRef.current || !canvasRef.current) return;

        const video = videoRef.current;
        const canvas = canvasRef.current;
        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(async (blob) => {
            if (!blob) return;

            const rawFile = new File([blob], `capture_${Date.now()}.jpg`, { type: 'image/jpeg' });

            // Auto-compress photo to ~500KB target
            const compression = await CompressionService.compressImage(rawFile, 1920, 0.85);

            setCaptured({
                file: compression.file,
                previewUrl: URL.createObjectURL(compression.file),
                latitude: coords.latitude,
                longitude: coords.longitude,
                capturedAt: new Date().toISOString(),
            });
        }, 'image/jpeg', 0.9);
    };

    const handleConfirmPhoto = () => {
        if (captured) {
            onCapture(captured);
            onOpenChange(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md p-0 overflow-hidden bg-black text-white border-0 h-[90vh] flex flex-col">
                {/* Header */}
                <div className="p-4 bg-black/60 backdrop-blur flex items-center justify-between z-10">
                    <div>
                        <span className="font-bold text-sm">{categoryTitle}</span>
                        {coords.latitude && (
                            <div className="flex items-center gap-1 text-[10px] text-emerald-400 font-mono">
                                <MapPin className="size-3" />
                                {coords.latitude.toFixed(4)}, {coords.longitude?.toFixed(4)}
                            </div>
                        )}
                    </div>
                    <Button variant="ghost" size="icon" onClick={() => onOpenChange(false)} className="text-white">
                        <X className="size-5" />
                    </Button>
                </div>

                {/* Viewport Area */}
                <div className="flex-1 relative bg-black flex items-center justify-center overflow-hidden">
                    {renderCameraContent(error, captured, videoRef)}
                    <canvas ref={canvasRef} className="hidden" />
                </div>

                {/* Controls Bar */}
                <div className="p-6 bg-black/80 backdrop-blur flex items-center justify-around z-10">
                    {captured ? (
                        <>
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => setCaptured(null)}
                                className="rounded-full bg-white/10 text-white border-white/20 hover:bg-white/20 gap-2"
                            >
                                <RotateCcw className="size-5" /> Retake
                            </Button>
                            <Button
                                variant="success"
                                size="lg"
                                onClick={handleConfirmPhoto}
                                className="rounded-full gap-2 px-8 font-bold"
                            >
                                <Check className="size-5" /> Use Photo
                            </Button>
                        </>
                    ) : (
                        <button
                            type="button"
                            onClick={handleTakePhoto}
                            disabled={Boolean(error)}
                            className="size-18 rounded-full border-4 border-white flex items-center justify-center p-1 active:scale-95 transition-transform disabled:opacity-50"
                            aria-label="Take Photo"
                        >
                            <div className="size-full bg-white rounded-full flex items-center justify-center">
                                <Camera className="size-8 text-black" />
                            </div>
                        </button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default InAppCamera;
