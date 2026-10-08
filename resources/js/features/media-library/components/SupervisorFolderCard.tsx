import type { TeamSupervisor } from '../types/media-library.types';
import { formatRoleLabel, getUploaderInitials } from '../utils/format-uploader';

const AVATAR_SIZES = {
    xs: 'size-5 text-[9px]',
    sm: 'h-8 w-8 text-[11px]',
    md: 'h-10 w-10 text-xs',
} as const;

export interface SupervisorAvatarProps {
    readonly name: string;
    readonly photoUrl?: string | null;
    readonly size?: keyof typeof AVATAR_SIZES;
}

export function SupervisorAvatar({ name, photoUrl, size = 'md' }: SupervisorAvatarProps) {
    const sizeClass = AVATAR_SIZES[size];

    return photoUrl ? (
        <img
            src={photoUrl}
            alt={name}
            className={`${sizeClass} shrink-0 rounded-full border border-border object-cover`}
        />
    ) : (
        <div
            className={`flex ${sizeClass} shrink-0 items-center justify-center rounded-full bg-primary/10 font-bold text-primary`}
        >
            {getUploaderInitials(name)}
        </div>
    );
}

export interface SupervisorFolderCardProps {
    readonly supervisor: TeamSupervisor;
    readonly showRole?: boolean;
    readonly onOpen: (supervisor: TeamSupervisor) => void;
}

export function SupervisorFolderCard({ supervisor, showRole = false, onOpen }: SupervisorFolderCardProps) {
    return (
        <button
            type="button"
            onClick={() => onOpen(supervisor)}
            title={`${supervisor.name} — ${supervisor.email}`}
            className="group flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-transparent px-1 py-2.5 text-center transition-colors hover:border-border hover:bg-muted/40"
        >
            <span className="relative">
                <SupervisorAvatar name={supervisor.name} photoUrl={supervisor.profile_photo_url} />
                <span
                    className="absolute -top-1 -right-1.5 min-w-[18px] rounded-full bg-primary px-1 text-center text-[10px] font-bold leading-4 text-primary-foreground"
                    title={`${supervisor.assets_count} photos`}
                >
                    {supervisor.assets_count}
                </span>
            </span>
            <span className="w-full min-w-0">
                <span className="block truncate text-xs font-medium text-foreground">
                    {supervisor.name}
                </span>
                {showRole && (
                    <span className="block text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                        {formatRoleLabel(supervisor.role_name)}
                    </span>
                )}
            </span>
        </button>
    );
}
