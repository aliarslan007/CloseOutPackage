import { UserAvatar } from '@/components/shared/user-avatar';
import { cn } from '@/lib/utils';
import { formatRelativeTime } from '@/lib/relative-time';
import { Check } from 'lucide-react';
import * as React from 'react';
import type { EntityFeedbackAuthor } from '../types/entity-feedback.types';
import { roleBadgeLabel } from '../utils/feedback-copy';

export interface FeedbackMessageAuthor {
    name: string;
    avatarUrl?: string | null;
}

export function authorAvatarUrl(author?: EntityFeedbackAuthor | null): string | null {
    return author?.profile_photo_url ?? author?.avatar_url ?? null;
}

export function authorRoleLabel(
    author: EntityFeedbackAuthor | null | undefined,
    fallback: string
): string {
    if (author?.role_name) {
        return roleBadgeLabel(author.role_name);
    }
    return fallback;
}

/**
 * WhatsApp's own bubble greens (light #d9fdd3 / #005c4b, dark #005c4b / #202c33).
 * Hard-coded on purpose: these hexes ARE the design reference, and they live in
 * exactly one file so a re-theme is a two-line edit, not a hunt.
 */
const BUBBLE_OUTGOING = 'bg-[#d9fdd3] dark:bg-[#005c4b]';
const BUBBLE_INCOMING =
    'border border-border/70 bg-[#f7f8fa] dark:border-white/10 dark:bg-[#202c33]';

export interface FeedbackMessageProps {
    author: FeedbackMessageAuthor;
    roleLabel: string;
    body: string;
    editedAt?: string | null;
    verb?: string;
    createdAt?: string | null;
    /** Which edge the bubble hugs. "right" = the viewer's own side, like a chat app. */
    side?: 'left' | 'right';
    status?: React.ReactNode;
    /** Single tick on the sender's own bubble once the backend stored it (WhatsApp's "sent"). Ignored on incoming bubbles. */
    delivered?: boolean;
    /** Same speaker as the message above - header shows once per run, like a chat group. */
    grouped?: boolean;
    children?: React.ReactNode;
}

/**
 * A WhatsApp-grade chat bubble: hugs its content (a "??" reads as a pill, not
 * a full-width card), the sender's name sits inside the bubble only when it
 * adds information, and the timestamp floats at the end of the last text line
 * - dropping to the bubble's bottom-right corner behind a photo, exactly like
 * the real client. Position and a quiet two-tone green carry who spoke; no
 * header bars, no accent stripes.
 */
export function FeedbackMessage({
    author,
    roleLabel,
    body,
    editedAt,
    verb,
    createdAt,
    side = 'left',
    status,
    delivered = false,
    grouped = false,
    children,
}: Readonly<FeedbackMessageProps>) {
    const outgoing = side === 'right';
    // Right side names every speaker on every message (photos included);
    // the left side shows its header once per run, like a chat group.
    const showIdentity = outgoing || !grouped;

    return (
        <div
            className={cn(
                'flex w-full max-w-[85%] flex-col gap-1 sm:max-w-[75%]',
                outgoing && 'ml-auto items-end'
            )}
        >
            {/* Identity rides ABOVE the bubble - avatar + name belong to the
                thread, the color belongs to the speaker. */}
            {showIdentity && (
                <div className="flex items-center gap-1.5 text-[11px] leading-none text-muted-foreground">
                    <UserAvatar
                        name={author.name}
                        src={author.avatarUrl}
                        className="size-5 text-[9px]"
                    />
                    <span className="max-w-[12rem] truncate font-semibold text-primary dark:text-[#7fd9c6]">
                        {author.name}
                    </span>
                    <span className="text-muted-foreground/70">{roleLabel}</span>
                </div>
            )}

            <div
                className={cn(
                    'w-fit max-w-full rounded-lg px-2.5 py-1.5 text-sm shadow-2xs',
                    outgoing
                        ? `${BUBBLE_OUTGOING} rounded-tr-[2px]`
                        : `${BUBBLE_INCOMING} rounded-tl-[2px]`
                )}
            >
                {verb && (
                    <p className="mb-0.5 text-[11px] font-medium text-muted-foreground">{verb}</p>
                )}

                <span className="inline whitespace-pre-wrap break-words leading-relaxed text-foreground [overflow-wrap:anywhere]">
                    {body}
                </span>

                {/* Reserves the meta row's width on the last text line so the
                    float below lands beside short messages instead of being
                    pushed onto its own row - WhatsApp's spacer trick. */}
                {(createdAt || editedAt) && (
                    <span
                        aria-hidden
                        className={cn('inline-block align-bottom', status ? 'w-28' : 'w-14')}
                    />
                )}

                {children && <div className="mt-1.5">{children}</div>}

                {/* Float rides the last text line when there is room, and lands
                    in the bottom-right corner behind a photo - WhatsApp's own
                    trick: one floated span, no absolute positioning. */}
                <span className="float-right ml-2 flex items-center gap-1 pt-1 text-[10px] leading-none tabular-nums text-muted-foreground select-none">
                    {editedAt && (
                        <span
                            className="italic opacity-80"
                            title={`Edited ${formatRelativeTime(editedAt)}`}
                        >
                            edited
                        </span>
                    )}

                    {status}

                    {createdAt && <span>{formatRelativeTime(createdAt)}</span>}

                    {outgoing && delivered && (
                        <Check className="size-3 shrink-0" role="img" aria-label="Delivered" />
                    )}
                </span>

                {/* Clear so the next bubble never wraps beside this float. */}
                <span aria-hidden className="block clear-both" />
            </div>
        </div>
    );
}

/**
 * Photo inside a chat bubble, WhatsApp-style: the image is the bubble's width
 * with its own rounding, and a slim caption line sits underneath it.
 */
export function MessagePhotoLink({
    url,
    title,
    subtitle,
    alt,
}: Readonly<{ url: string; title: string; subtitle: string; alt: string }>) {
    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className="group/photo block w-56 max-w-full overflow-hidden rounded-lg border border-black/10 transition-shadow hover:shadow-md dark:border-white/10"
        >
            <img
                src={url}
                alt={alt}
                loading="lazy"
                decoding="async"
                className="max-h-56 w-full object-cover"
            />
            <span className="flex items-baseline gap-1.5 px-2 py-1.5 text-[11px] leading-tight">
                <span className="shrink-0 font-semibold text-foreground">{title}</span>
                <span className="truncate text-muted-foreground">{subtitle}</span>
            </span>
        </a>
    );
}
