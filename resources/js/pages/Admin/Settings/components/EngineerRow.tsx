import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FolderKanban, Mail, Pencil, Phone, Star, Trash2 } from 'lucide-react';
import type { EngineerItem } from './customer-hierarchy-types';

function initialsOf(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]!.toUpperCase())
        .join('');
}

/**
 * One engineer: avatar identity, contact meta, workload chip, and row actions.
 * Dense enough to scan a region at a glance without a wall of chrome.
 */
export function EngineerRow({
    engineer,
    onSetDefault,
    onWorkload,
    onEdit,
    onRemove,
}: Readonly<{
    engineer: EngineerItem;
    onSetDefault: (engineer: EngineerItem) => void;
    onWorkload: (engineer: EngineerItem) => void;
    onEdit: (engineer: EngineerItem) => void;
    onRemove: (engineer: EngineerItem) => void;
}>) {
    const assigned = engineer.assigned_projects.length;

    return (
        <li className="group flex items-start gap-3 px-4 py-3.5 transition-colors hover:bg-muted/40">
            <Avatar className="size-9 border border-border/70">
                {engineer.profile_photo_url ? (
                    <img
                        src={engineer.profile_photo_url}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : (
                    <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                        {initialsOf(engineer.name)}
                    </AvatarFallback>
                )}
            </Avatar>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="text-sm font-semibold text-foreground">{engineer.name}</span>
                    {engineer.title ? (
                        <span className="text-xs text-muted-foreground">{engineer.title}</span>
                    ) : null}
                    {engineer.is_default && (
                        <Badge className="gap-1 border-amber-500/30 bg-amber-500/10 px-1.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                            <Star className="size-2.5 fill-current" /> Default
                        </Badge>
                    )}
                    {!engineer.is_active && (
                        <Badge variant="secondary" className="px-1.5 text-[10px]">
                            Inactive
                        </Badge>
                    )}
                </div>

                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                    <span className="inline-flex items-center gap-1">
                        <Mail className="size-3 shrink-0" />
                        <span className="truncate">{engineer.email}</span>
                    </span>
                    {engineer.phone ? (
                        <span className="inline-flex items-center gap-1">
                            <Phone className="size-3 shrink-0" />
                            {engineer.phone}
                        </span>
                    ) : null}
                    <button
                        type="button"
                        onClick={() => onWorkload(engineer)}
                        className="inline-flex cursor-pointer items-center gap-1 rounded-full border border-border/70 bg-card px-2 py-0.5 font-medium text-foreground transition-colors hover:border-primary/40 hover:bg-primary/5"
                        title="Show projects assigned to this engineer"
                    >
                        <FolderKanban className="size-3" />
                        {assigned} assigned
                    </button>
                    {engineer.has_portal_account ? (
                        <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                            <span className="size-1.5 rounded-full bg-emerald-500" aria-hidden />
                            Portal account
                        </span>
                    ) : null}
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-0.5 opacity-70 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                {!engineer.is_default && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 cursor-pointer gap-1 px-2 text-xs"
                        title="Set as the default engineer for this region"
                        onClick={() => onSetDefault(engineer)}
                    >
                        <Star className="size-3.5" /> Set default
                    </Button>
                )}
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-7 cursor-pointer gap-1 px-2 text-xs"
                    title="Edit this engineer"
                    onClick={() => onEdit(engineer)}
                >
                    <Pencil className="size-3.5" /> Edit
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-7 cursor-pointer gap-1 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                    title="Remove this engineer and their portal login"
                    onClick={() => onRemove(engineer)}
                >
                    <Trash2 className="size-3.5" /> Remove
                </Button>
            </div>
        </li>
    );
}
