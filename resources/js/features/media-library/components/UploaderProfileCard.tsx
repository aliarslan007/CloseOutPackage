import type { MediaAssetUploader } from '../types/media-library.types';
import { getUploaderInitials, getUploaderRoleName } from '../utils/format-uploader';

export interface UploaderProfileCardProps {
    uploader?: MediaAssetUploader | null;
}

export function UploaderProfileCard({ uploader }: Readonly<UploaderProfileCardProps>) {
    if (!uploader) return null;

    const roleName = getUploaderRoleName(uploader);
    const initials = getUploaderInitials(uploader.name);

    return (
        <div className="flex items-center justify-between rounded-lg border border-border/80 bg-muted/30 px-3 py-2 text-xs">
            <div className="flex items-center gap-2.5 min-w-0">
                {uploader.profile_photo_url ? (
                    <img
                        src={uploader.profile_photo_url}
                        alt={uploader.name}
                        className="h-7 w-7 rounded-full object-cover border border-border shrink-0"
                    />
                ) : (
                    <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                        {initials}
                    </div>
                )}
                <div className="min-w-0">
                    <p className="font-semibold text-xs text-foreground truncate">
                        {uploader.name}
                    </p>
                    {uploader.email && (
                        <p className="text-[10px] text-muted-foreground truncate">
                            {uploader.email}
                        </p>
                    )}
                </div>
            </div>
            <span className="rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary shrink-0">
                {roleName}
            </span>
        </div>
    );
}
