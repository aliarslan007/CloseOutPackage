import * as React from 'react';
import { Megaphone } from 'lucide-react';
import { EmailLink } from '@/components/shared/contact-links';
import {
    PrimaryTechnicianBadge,
    PrimaryTechnicianStar,
} from '@/components/projects/primary-technician-star';
import { UserAvatar } from '@/components/shared/user-avatar';

/** One word revealed per tick. Fast enough to feel live, slow enough to read. */
const TYPE_INTERVAL_MS = 55;

/** How long the finished text rests before it retypes itself. */
const HOLD_MS = 4500;

export interface PrimaryTechnicianContact {
    id: number;
    name: string;
    email?: string | null;
    profile_photo_url?: string | null;
}

/** The star is read-only here, so its click handler never runs. */
const noop = () => {};

/**
 * `prefers-reduced-motion`. Someone who has asked their OS for less movement
 * gets the full sentence immediately and no loop at all.
 */
function usePrefersReducedMotion(): boolean {
    const [reduced, setReduced] = React.useState(false);

    React.useEffect(() => {
        const query = globalThis.matchMedia('(prefers-reduced-motion: reduce)');
        setReduced(query.matches);

        const onChange = (event: MediaQueryListEvent) => setReduced(event.matches);
        query.addEventListener('change', onChange);

        return () => query.removeEventListener('change', onChange);
    }, []);

    return reduced;
}

/**
 * Reveals text one word at a time, holds, then starts over.
 *
 * A chained timeout rather than an interval: pausing is just declining to
 * schedule the next tick, so there is no timer to leak and no drift to pile up.
 * Every effect run returns its own cleanup, so unmounting mid-sentence cannot
 * leave a stray timeout firing into a dead component.
 */
function useTypewriter(text: string, animate: boolean) {
    // Keep trailing whitespace with its word so the line never renders
    // mid-word-spacing and then jumps.
    const words = React.useMemo(() => text.match(/\S+\s*/g) ?? [], [text]);
    const [revealed, setRevealed] = React.useState(0);

    React.useEffect(() => {
        setRevealed(0);
    }, [text]);

    React.useEffect(() => {
        if (!animate) {
            return;
        }

        if (revealed >= words.length) {
            const timer = setTimeout(() => setRevealed(0), HOLD_MS);

            return () => clearTimeout(timer);
        }

        const timer = setTimeout(() => setRevealed((count) => count + 1), TYPE_INTERVAL_MS);

        return () => clearTimeout(timer);
    }, [animate, revealed, words.length]);

    return {
        text: words.slice(0, revealed).join(''),
        isComplete: revealed >= words.length,
    };
}

/**
 * Owns the animation so the parent can early-return for the empty case without
 * a conditional hook call.
 */
function AdminInstructionText({ notes }: Readonly<{ notes: string }>) {
    const reduceMotion = usePrefersReducedMotion();
    const [isPaused, setIsPaused] = React.useState(false);
    const { text: typed, isComplete } = useTypewriter(notes, !reduceMotion && !isPaused);
    const showCaret = !reduceMotion && !isPaused && !isComplete;

    return (
        <p
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
            onFocus={() => setIsPaused(true)}
            onBlur={() => setIsPaused(false)}
            // One line on desktop, where the title tooltip can reveal the rest.
            // Two on mobile, where there is no hover to fall back on.
            className="line-clamp-2 min-w-0 flex-1 text-sm text-muted-foreground sm:truncate"
            title={notes}
        >
            {reduceMotion ? notes : typed}
            {showCaret ? (
                <span
                    aria-hidden
                    className="ml-0.5 inline-block h-3.5 w-px translate-y-0.5 bg-primary align-middle motion-safe:animate-pulse"
                />
            ) : null}
            {isPaused && !isComplete ? <span className="sr-only">(paused)</span> : null}
        </p>
    );
}

export interface AdminNotesCenterProps {
    notes?: string | null;
    /** The project's primary field technician, for contact. */
    primaryTechnician?: PrimaryTechnicianContact | null;
    children: React.ReactNode;
}

/**
 * The stage tabs followed by a single unboxed line: the admin's instructions on
 * the left, the primary technician on the right.
 *
 * No panel, no border, no background, no disclosure — the guidance is simply
 * always on screen, which is the whole point of it. The instruction types itself
 * out so a technician glancing at the screen catches it; hovering or focusing
 * pauses the loop, which is also the WCAG 2.2.2 mechanism a looping animation
 * past five seconds legally requires. The technician is contact information, so
 * it renders statically and stays readable at a glance.
 *
 * Renders nothing extra when there is neither instructions nor a technician.
 */
export function AdminNotesCenter({
    notes,
    primaryTechnician,
    children,
}: Readonly<AdminNotesCenterProps>) {
    const hasNotes = Boolean(notes && notes.trim());
    const instruction = hasNotes ? (notes as string).replace(/\s+/g, ' ').trim() : null;

    if (!instruction && !primaryTechnician) {
        return <>{children}</>;
    }

    return (
        <div className="space-y-2.5">
            {children}

            <div className="flex flex-col gap-1.5 text-sm lg:flex-row lg:items-center lg:gap-4">
                {instruction ? (
                    <div className="flex min-w-0 flex-1 items-baseline gap-2">
                        <span className="inline-flex shrink-0 items-center gap-1.5">
                            <Megaphone className="size-3.5 shrink-0 text-muted-foreground" />
                            <span className="text-xs font-semibold uppercase tracking-wide text-foreground">
                                Admin Instructions
                            </span>
                        </span>
                        <AdminInstructionText notes={instruction} />
                    </div>
                ) : null}

                {primaryTechnician ? (
                    <div className="flex min-w-0 items-center gap-2 lg:shrink-0 lg:border-l lg:border-border/70 lg:pl-4">
                        <UserAvatar
                            name={primaryTechnician.name}
                            src={primaryTechnician.profile_photo_url}
                            className="size-6"
                        />
                        <span className="flex min-w-0 flex-col leading-tight">
                            <span className="flex items-center gap-1.5">
                                <span className="truncate text-xs font-semibold text-foreground">
                                    {primaryTechnician.name}
                                </span>
                                {/* Yellow star once there is room for it; the word
                                    "Primary" carries the meaning on a phone. */}
                                <PrimaryTechnicianStar
                                    name={primaryTechnician.name}
                                    isPrimary
                                    canEdit={false}
                                    onClick={noop}
                                    className="hidden size-4 shrink-0 lg:inline-flex"
                                />
                                <PrimaryTechnicianBadge className="shrink-0 text-[10px] text-amber-600 lg:hidden dark:text-amber-400" />
                            </span>
                            {primaryTechnician.email ? (
                                <EmailLink
                                    email={primaryTechnician.email}
                                    className="truncate text-xs"
                                />
                            ) : null}
                        </span>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

export default AdminNotesCenter;
