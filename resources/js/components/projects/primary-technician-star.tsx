import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface PrimaryTechnicianStarProps {
    name: string;
    isPrimary: boolean;
    /** Managers and admins can click the star; everyone else just sees it. */
    canEdit: boolean;
    disabled?: boolean;
    onClick: () => void;
    /** Extra classes for the hit area, so inline use can size it down. */
    className?: string;
}

const STAR_BASE_CLASS =
    'inline-flex size-6 shrink-0 items-center justify-center rounded-full transition-colors';

/**
 * The star that marks a project's single primary field technician.
 *
 * Filled amber means "this is the primary"; hollow means "make this person the
 * primary". A slow opacity breath draws the eye without buzzing like a spinner;
 * the label is spelled out for screen readers and on hover, so the meaning
 * never relies on colour alone.
 */
export function PrimaryTechnicianStar({
    name,
    isPrimary,
    canEdit,
    disabled = false,
    onClick,
    className,
}: Readonly<PrimaryTechnicianStarProps>) {
    const label = isPrimary
        ? `${name} is the primary field technician`
        : `Make ${name} the primary field technician`;

    const star = (
        <Star
            className={cn(
                'size-3.5',
                isPrimary && 'fill-current primary-star-pulse'
            )}
        />
    );

    if (!canEdit) {
        return (
            <span
                aria-label={label}
                title={label}
                className={cn(
                    STAR_BASE_CLASS,
                    isPrimary ? 'text-amber-500' : 'text-muted-foreground/40'
                )}
            >
                {star}
            </span>
        );
    }

    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            aria-pressed={isPrimary}
            aria-label={label}
            title={label}
            className={cn(
                STAR_BASE_CLASS,
                'cursor-pointer',
                isPrimary
                    ? 'text-amber-500 hover:bg-amber-500/10 hover:text-amber-600'
                    : 'text-muted-foreground/60 hover:bg-muted hover:text-amber-500',
                disabled && 'cursor-default opacity-60',
                className
            )}
        >
            {star}
        </button>
    );
}

/** Renders the star's meaning in words next to the primary technician's name. */
export function PrimaryTechnicianBadge({ className }: Readonly<{ className?: string }>) {
    return (
        <span
            className={cn(
                'shrink-0 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground',
                className
            )}
        >
            Primary
        </span>
    );
}
