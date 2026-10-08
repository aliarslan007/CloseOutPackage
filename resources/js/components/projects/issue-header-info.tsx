import { ROLE_LABELS } from '@/components/users/user-row';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, HelpCircle, MapPin } from 'lucide-react';
import * as React from 'react';
import { ISSUE_TYPE_LABELS, IssueItem } from './issue-types';

export interface ProjectLocationInfo {
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    vzw_engineer_phone?: string | null;
}

export interface IssueHeaderInfoProps {
    issue: IssueItem;
    projectLocation?: ProjectLocationInfo | null;
    isResolved: boolean;
}

function getInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatDateOnly(isoString: string): string {
    try {
        const date = new Date(isoString);
        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return isoString;
    }
}

function getIssueHeadline(issue: IssueItem): string {
    const categoryLabel = ISSUE_TYPE_LABELS[issue.type] ?? 'Site Issue';
    if (!issue.description) return categoryLabel;

    const firstLine = issue.description.split('\n')[0]?.trim();
    if (firstLine && firstLine.length <= 60 && !firstLine.endsWith('.')) {
        return firstLine;
    }

    if (issue.type === 'site_access' || issue.type === 'lock') {
        return 'Site Access Blocked & Locked';
    }

    return `${categoryLabel} at Site`;
}

function formatAddress(loc?: ProjectLocationInfo | null): string {
    if (!loc) return 'Site Location';
    const parts = [loc.address, loc.city, loc.state, loc.zip].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : 'Site Location';
}

/** Roughly the point where the text stops being a short note. */
const LONG_TEXT_THRESHOLD = 400;

/**
 * 3,000 characters is the field's limit, not its typical length. Rendering all
 * of it pushes the media, feedback and action rows off screen, so a long report
 * collapses to a few lines and stays one click away.
 *
 * Two details earn the collapse: the fade, so the text looks like it continues
 * rather than like the box cut it off mid-sentence, and the character count, so
 * you know what you are expanding into before you do. An inner scroll box was
 * the alternative and it is worse — a wheel nobody expects, hiding the content
 * that matters most.
 */
function ExpandableText({
    text,
    clampClass = 'line-clamp-5',
    className,
}: Readonly<{ text: string; clampClass?: string; className?: string }>) {
    const [expanded, setExpanded] = React.useState(false);
    const isLong = text.length > LONG_TEXT_THRESHOLD;
    const collapsed = isLong && !expanded;
    const fade = 'linear-gradient(to bottom, #000 calc(100% - 1.75rem), transparent)';

    return (
        <div>
            <p
                className={cn('whitespace-pre-wrap break-words', className, collapsed && clampClass)}
                /* An alpha mask rather than a colour fade, so it works over the
                   muted panel and inside the purple guidance callout without
                   having to match either surface. */
                style={collapsed ? { maskImage: fade, WebkitMaskImage: fade } : undefined}
            >
                {text}
            </p>

            {isLong && (
                <button
                    type="button"
                    onClick={() => setExpanded((prev) => !prev)}
                    aria-expanded={expanded}
                    className="mt-1.5 inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline cursor-pointer"
                >
                    <span>{expanded ? 'Show less' : 'Show full text'}</span>
                    {expanded ? (
                        <ChevronUp className="size-3" />
                    ) : (
                        <ChevronDown className="size-3" />
                    )}
                    {!expanded && (
                        <span className="font-normal tabular-nums text-muted-foreground">
                            ({text.length.toLocaleString()} characters)
                        </span>
                    )}
                </button>
            )}
        </div>
    );
}

export function IssueHeaderInfo({
    issue,
    projectLocation,
    isResolved,
}: Readonly<IssueHeaderInfoProps>) {
    const reporterName = issue.reporter?.name ?? 'Field Technician';
    const reporterPhotoUrl = issue.reporter?.profile_photo_url;
    const reporterRoleKey = issue.reporter?.roles?.[0]?.name ?? 'field_supervisor';
    const reporterRoleLabel = ROLE_LABELS[reporterRoleKey] ?? 'Field Technician';
    const addressString = formatAddress(projectLocation);

    return (
        <div className="space-y-3">
            {/* Title & Status */}
            <div className="space-y-2">
                <h2 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground leading-snug">
                    {getIssueHeadline(issue)}
                </h2>

                <div className="flex items-center gap-2 text-xs text-foreground flex-wrap">
                    {isResolved ? (
                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wider uppercase bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                            RESOLVED
                        </span>
                    ) : (
                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wider uppercase bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                            IN PROGRESS
                        </span>
                    )}
                    <span className="text-muted-foreground/60">•</span>
                    <div className="flex items-center gap-1.5">
                        <span className="text-muted-foreground font-normal">Reported by</span>
                        <div className="flex items-center gap-1">
                            {reporterPhotoUrl ? (
                                <div className="size-5 w-5 h-5 min-w-5 min-h-5 rounded-full overflow-hidden shrink-0 border border-border">
                                    <img
                                        src={reporterPhotoUrl}
                                        alt={reporterName}
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                            ) : (
                                <div className="size-5 w-5 h-5 min-w-5 min-h-5 flex items-center justify-center rounded-full bg-primary/10 text-[9px] font-bold text-primary shrink-0 border border-border/60">
                                    {getInitials(reporterName)}
                                </div>
                            )}
                            <span className="font-semibold text-foreground text-xs">
                                {reporterName}
                            </span>
                            <span className="text-muted-foreground text-[11px]">
                                ({reporterRoleLabel})
                            </span>
                        </div>
                    </div>
                    <span className="text-muted-foreground/60">•</span>
                    <span className="text-muted-foreground text-xs">
                        {formatDateOnly(issue.created_at)}
                    </span>
                </div>

                {addressString && (
                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground pt-0.5">
                        <MapPin className="size-3.5 text-muted-foreground shrink-0" />
                        <span className="truncate font-medium text-foreground/80">{addressString}</span>
                    </div>
                )}
            </div>

            {/* Problem Description & Verizon Guidance */}
            <div className="space-y-1.5 pt-1">
                <span className="text-xs font-bold text-foreground block">Problem Description</span>
                <div className="rounded-xl bg-muted/40 border border-border/60 p-3.5 space-y-2.5">
                    <ExpandableText
                        text={issue.description}
                        className="text-xs text-foreground/90 leading-relaxed"
                    />

                    {issue.verizon_guidance && (
                        <div className="rounded-lg border border-purple-500/20 bg-purple-500/10 p-2.5 flex items-start gap-2 text-xs text-purple-900 dark:text-purple-300 break-words">
                            <HelpCircle className="size-3.5 shrink-0 text-purple-600 dark:text-purple-400 mt-0.5" />
                            <div className="min-w-0 flex-1">
                                <strong className="block text-[11px] font-bold text-purple-950 dark:text-purple-200">
                                    Verizon Guidance:
                                </strong>
                                <ExpandableText
                                    text={issue.verizon_guidance}
                                    clampClass="line-clamp-4"
                                    className="text-[11px] leading-relaxed mt-0.5"
                                />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
