import { Lock } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface PackageLocker {
    readonly id?: number;
    readonly name: string;
    readonly profile_photo_url?: string | null;
}

export interface PackageLockInfo {
    readonly locked_by?: PackageLocker | null;
    readonly locked_at?: string | null;
}

/**
 * Human-readable lock timestamp, e.g. "Sep 15, 2026, 2:30 PM". Returns null for
 * a missing or unparseable value so callers can degrade gracefully.
 */
export function formatLockedAt(value?: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? null
        : date.toLocaleString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          });
}

/**
 * "by Jane Doe on Sep 15, 2026, 2:30 PM" — omits whichever half is unknown
 * (e.g. a locker whose account was deleted).
 */
export function describeLock({ locked_by, locked_at }: PackageLockInfo): string {
    const locker = locked_by?.name?.trim();
    const lockedAt = formatLockedAt(locked_at);

    if (locker && lockedAt) {
        return `by ${locker} on ${lockedAt}`;
    }
    if (locker) {
        return `by ${locker}`;
    }
    if (lockedAt) {
        return `on ${lockedAt}`;
    }

    return '';
}

type LockerAvatarSize = 'sm' | 'md';

const AVATAR_BOX: Record<LockerAvatarSize, string> = {
    sm: 'size-5',
    md: 'size-10',
};

const AVATAR_ICON: Record<LockerAvatarSize, string> = {
    sm: 'size-3',
    md: 'size-5',
};

/**
 * The locker's profile photo, falling back to a lock glyph when they have no
 * avatar uploaded (or the account was deleted).
 */
function LockerAvatar({
    locker,
    size = 'sm',
}: Readonly<{ locker?: PackageLocker | null; size?: LockerAvatarSize }>) {
    const photoUrl = locker?.profile_photo_url;

    if (photoUrl) {
        return (
            <img
                src={photoUrl}
                alt={locker?.name ? `${locker.name} — locked this package` : 'Package locker'}
                className={cn(
                    AVATAR_BOX[size],
                    'shrink-0 rounded-full border border-border object-cover'
                )}
                loading="lazy"
            />
        );
    }

    return (
        <span
            aria-hidden
            className={cn(
                AVATAR_BOX[size],
                'inline-flex shrink-0 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground'
            )}
        >
            <Lock className={AVATAR_ICON[size]} />
        </span>
    );
}

export interface PackageLockNoticeProps extends PackageLockInfo {
    readonly className?: string;
}

/**
 * Full-width locked banner for the package detail pages (Show / Review).
 */
export function PackageLockNotice({
    locked_by,
    locked_at,
    className,
}: Readonly<PackageLockNoticeProps>) {
    const detail = describeLock({ locked_by, locked_at });

    return (
        <div
            className={cn(
                'rounded-xl border border-amber-500/30 dark:border-amber-500/20 cop-locked-stripes p-4 flex items-start gap-3',
                className
            )}
        >
            <LockerAvatar locker={locked_by} size="md" />
            <div className="min-w-0">
                <p className="text-sm font-bold text-foreground break-words">
                    Package locked{detail ? ` ${detail}` : ''}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    Only admins and the person who locked it can open, download, or email this
                    package.
                </p>
            </div>
        </div>
    );
}

export interface PackageLockMetaProps extends PackageLockInfo {
    readonly className?: string;
}

/**
 * Compact single-line lock attribution for package cards and lists. Renders
 * nothing when neither the locker nor the timestamp is known.
 */
export function PackageLockMeta({
    locked_by,
    locked_at,
    className,
}: Readonly<PackageLockMetaProps>) {
    const detail = describeLock({ locked_by, locked_at });

    if (detail === '') {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground',
                className
            )}
        >
            <LockerAvatar locker={locked_by} size="sm" />
            <span className="min-w-0 break-words">Locked {detail}</span>
        </span>
    );
}

export default PackageLockNotice;
