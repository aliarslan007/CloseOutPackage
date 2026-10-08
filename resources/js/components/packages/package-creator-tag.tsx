import { cn } from '@/lib/utils';

export interface PackageCreator {
    id: number;
    name: string;
    profile_photo_url?: string | null;
}

/** First letters of a person's name, e.g. "Dana Whitfield" → "DW". */
function getInitials(name: string): string {
    return name
        .split(' ')
        .filter((part) => part.length > 0)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');
}

/**
 * "Created by" attribution for a close-out package: circular profile photo
 * (initials when no avatar was uploaded) plus the creator's full name.
 *
 * Managers and field technicians can both create packages, so the list makes
 * the author obvious at a glance. Renders nothing when the creator is unknown
 * (legacy rows or a deleted account).
 */
export function PackageCreatorTag({
    creator,
    className,
}: Readonly<{ creator?: PackageCreator | null; className?: string }>) {
    if (!creator?.name) {
        return null;
    }

    return (
        <span
            className={cn('flex items-center gap-1.5', className)}
            title={`Created by ${creator.name}`}
        >
            <span>Created by</span>
            {creator.profile_photo_url ? (
                <img
                    src={creator.profile_photo_url}
                    alt={creator.name}
                    loading="lazy"
                    className="size-5 shrink-0 rounded-full border border-border object-cover"
                />
            ) : (
                <span
                    aria-hidden
                    className="inline-flex size-5 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-[9px] font-bold text-muted-foreground"
                >
                    {getInitials(creator.name)}
                </span>
            )}
            <span className="font-semibold text-foreground">{creator.name}</span>
        </span>
    );
}

export default PackageCreatorTag;