import { cn } from '@/lib/utils';

interface UserAvatarProps {
    name?: string | null;
    src?: string | null;
    className?: string;
}

/**
 * Profile photo in a circle, falling back to the person's initial. The image is
 * decorative here — the name always sits next to it — so it stays out of the
 * accessibility tree to avoid announcing the same person twice.
 */
export function UserAvatar({ name, src, className }: Readonly<UserAvatarProps>) {
    if (src) {
        return (
            <span
                className={cn(
                    'relative inline-flex size-7 shrink-0 overflow-hidden rounded-full aspect-square border border-border/80',
                    className
                )}
            >
                <img
                    src={src}
                    alt=""
                    className="block h-full w-full object-cover"
                />
            </span>
        );
    }

    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold uppercase text-muted-foreground aspect-square border border-border/80',
                className
            )}
        >
            {(name || '?').charAt(0)}
        </span>
    );
}

export default UserAvatar;
