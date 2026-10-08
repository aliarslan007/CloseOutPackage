import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    preparePhotoForUpload,
    rejectedPhotoMessage,
} from '@/services/compression.service';
import { Camera, Upload } from 'lucide-react';
import { toast } from 'sonner';

export interface SitePhotoCardProps {
    projectId: number | string;
    sitePhotoUrl?: string | null;
    canEdit: boolean;
}

const SITE_PHOTO_TOAST_ID = 'site-photo-upload';

export function SitePhotoCard({
    projectId,
    sitePhotoUrl,
    canEdit,
}: Readonly<SitePhotoCardProps>) {
    const inputRef = React.useRef<HTMLInputElement>(null);
    const form = useForm({ photo: null as File | null });

    const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;

        toast.loading('Compressing photo…', { id: SITE_PHOTO_TOAST_ID });
        const prepared = await preparePhotoForUpload(file);
        if (!prepared.ok) {
            toast.error(rejectedPhotoMessage(file, prepared.reason), { id: SITE_PHOTO_TOAST_ID });
            return;
        }

        form.setData('photo', prepared.file);
        toast.loading('Uploading site photo…', { id: SITE_PHOTO_TOAST_ID });
        form.post(route('projects.site-photo.store', projectId), {
            onSuccess: () => {
                form.reset();
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
                toast.success('Site photo updated successfully.', { id: SITE_PHOTO_TOAST_ID });
            },
            onError: () => {
                toast.error('Failed to upload site photo. Please try again.', { id: SITE_PHOTO_TOAST_ID });
            },
        });
    };

    const renderContent = () => {
        if (sitePhotoUrl) {
            return (
                <div className="flex flex-1 flex-col justify-between gap-3">
                    <img
                        src={sitePhotoUrl}
                        alt="Project site"
                        className="max-h-72 w-full flex-1 rounded-lg object-cover"
                    />
                    {canEdit && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => inputRef.current?.click()}
                            className="h-8 w-fit text-xs gap-1"
                        >
                            <Upload className="size-3.5" /> Replace Photo
                        </Button>
                    )}
                </div>
            );
        }

        if (canEdit) {
            return (
                <div className="flex flex-1 items-center justify-center">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => inputRef.current?.click()}
                        loading={form.processing}
                        className="h-8 text-xs gap-1"
                    >
                        <Upload className="size-3.5" /> Upload Site Photo
                    </Button>
                </div>
            );
        }

        return (
            <p className="flex flex-1 items-center justify-center font-mono text-xs text-muted-foreground">
                No site photo uploaded.
            </p>
        );
    };

    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <Camera className="size-4 text-primary" /> Site Photo
                </CardTitle>
            </CardHeader>
            <CardContent className={cn('flex flex-1 flex-col')}>{renderContent()}

                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/heic"
                    onChange={handleFileChange}
                    className="hidden"
                    aria-label="Upload site photo"
                />
            </CardContent>
        </Card>
    );
}
