import { Button } from '@/components/ui/button';
import {
    MAX_AVATAR_UPLOAD_MB,
    preparePhotoForUpload,
    rejectedPhotoMessage,
    SUPPORTED_AVATAR_TYPES,
} from '@/services/compression.service';
import { Camera, Upload } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ProfilePhotoInputProps {
    /** Existing photo URL to show (from the server) until a new one is chosen. */
    photoUrl?: string | null;
    /** Form key used for the input's name/id, e.g. "profile_photo". */
    fileName: string;
    /** Fallback initial rendered when no photo exists. */
    initial: string;
    /** Called with the chosen file; the parent stores it in its form state. */
    onFileChange: (file: File) => void;
}

/**
 * Avatar preview + upload button + hidden file input with a local object-URL
 * preview. Shared by the admin user form and the self-service profile form so
 * the preview/fallback/revoke logic can never drift apart.
 */
export function ProfilePhotoInput({
    photoUrl = null,
    fileName,
    initial,
    onFileChange,
}: Readonly<ProfilePhotoInputProps>) {
    const [previewUrl, setPreviewUrl] = React.useState<string | null>(null);
    const inputRef = React.useRef<HTMLInputElement>(null);

    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;

        void (async () => {
            const prepared = await preparePhotoForUpload(file, {
                maxMb: MAX_AVATAR_UPLOAD_MB,
                allowedTypes: SUPPORTED_AVATAR_TYPES,
                maxSideLength: 512,
            });

            if (!prepared.ok) {
                toast.error(
                    rejectedPhotoMessage(file, prepared.reason, {
                        maxMb: MAX_AVATAR_UPLOAD_MB,
                        allowedTypes: SUPPORTED_AVATAR_TYPES,
                    })
                );
                return;
            }

            onFileChange(prepared.file);
            setPreviewUrl(URL.createObjectURL(prepared.file));
        })();
    };

    // Revoke the object URL once it is no longer needed.
    React.useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const photoSrc = previewUrl ?? photoUrl;

    return (
        <div className="flex items-center gap-4">
            <div className="relative size-20 shrink-0 overflow-hidden rounded-full border border-border bg-muted">
                {photoSrc ? (
                    <img
                        src={photoSrc}
                        alt="Profile preview"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-primary/10 text-2xl font-bold uppercase text-primary">
                        {initial}
                    </div>
                )}
            </div>
            <div className="flex flex-col gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => inputRef.current?.click()}
                    className="h-8 gap-1.5 text-xs"
                >
                    {photoSrc ? (
                        <>
                            <Camera className="size-3.5" /> Change Photo
                        </>
                    ) : (
                        <>
                            <Upload className="size-3.5" /> Upload Photo
                        </>
                    )}
                </Button>
                <p className="text-[11px] text-muted-foreground">
                    JPG, PNG or WEBP up to 2MB.
                </p>
            </div>
            <input
                ref={inputRef}
                id={fileName}
                name={fileName}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={handlePhotoChange}
                className="hidden"
                aria-label="Upload profile picture"
            />
        </div>
    );
}

export default ProfilePhotoInput;
