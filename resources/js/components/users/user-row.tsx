import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmailLink } from '@/components/shared/contact-links';
import { KeyRound, Pencil, Trash2, X } from 'lucide-react';

export interface UserRoleItem {
    id: number;
    name: string;
}

export interface UserListItem {
    id: number;
    hash_id?: string;
    name: string;
    email: string;
    company?: string | null;
    phone?: string | null;
    profile_photo_url?: string | null;
    is_active?: boolean;
    roles?: UserRoleItem[];
    markets?: Array<{ id: number; name: string }>;
}

export const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    manager: 'Manager',
    field_supervisor: 'Field Technician',
    customer: 'Customer',
};

export interface UserRowProps {
    user: UserListItem;
    markets: Array<{ id: number; name: string }>;
    isSelf: boolean;
    onPassword: (user: UserListItem) => void;
    onToggleActive: (user: UserListItem) => void;
    onDelete: (user: UserListItem) => void;
}

export function UserRow({
    user,
    markets,
    isSelf,
    onPassword,
    onToggleActive,
    onDelete,
}: Readonly<UserRowProps>) {
    const roles = user.roles ?? [];
    const assignedMarkets = user.markets ?? [];
    const isCustomer = roles.some((r) => r.name === 'customer');
    const hasPhoto = Boolean(user.profile_photo_url);

    const handleAssignMarket = (marketId: string) => {
        if (!marketId) return;
        router.post(route('admin.markets.users.store', Number(marketId)), {
            user_id: user.id,
        });
    };

    const handleRevokeMarket = (marketId: number) => {
        if (confirm('Revoke market access for this user?')) {
            router.delete(route('admin.markets.users.destroy', [marketId, user.id]));
        }
    };

    const unassignedMarkets = markets.filter(
        (m) => !assignedMarkets.some((am) => am.id === m.id)
    );

    const [expandedMarkets, setExpandedMarkets] = React.useState(false);

    const renderAssignedMarkets = () => {
        if (isCustomer) {
            return <span className="text-muted-foreground text-[10px]">Project-level access only</span>;
        }

        if (assignedMarkets.length === 0) {
            return <span className="text-muted-foreground text-[10px]">None</span>;
        }

        const visibleMarkets = expandedMarkets ? assignedMarkets : assignedMarkets.slice(0, 3);
        const remainingCount = assignedMarkets.length - 3;

        return (
            <div className="flex flex-wrap gap-1 max-w-[260px] items-center">
                {visibleMarkets.map((m) => (
                    <Badge
                        key={m.id}
                        variant="secondary"
                        className="gap-1 px-1.5 py-0.5 text-[10px] text-foreground flex items-center bg-muted/65 border shrink-0"
                    >
                        <span className="truncate max-w-[100px]" title={m.name}>{m.name}</span>
                        <button
                            type="button"
                            onClick={() => handleRevokeMarket(m.id)}
                            className="hover:text-destructive shrink-0 ml-0.5 cursor-pointer"
                            title={`Revoke access for ${m.name}`}
                            aria-label={`Revoke access for ${m.name}`}
                        >
                            <X className="size-2.5" />
                        </button>
                    </Badge>
                ))}
                {!expandedMarkets && remainingCount > 0 && (
                    <button
                        type="button"
                        onClick={() => setExpandedMarkets(true)}
                        className="text-[10px] font-medium text-primary hover:underline px-1 py-0.5 rounded bg-primary/10 border border-primary/20 shrink-0 cursor-pointer"
                        title="Click to view all assigned markets"
                    >
                        +{remainingCount} more
                    </button>
                )}
                {expandedMarkets && remainingCount > 0 && (
                    <button
                        type="button"
                        onClick={() => setExpandedMarkets(false)}
                        className="text-[10px] font-medium text-muted-foreground hover:underline px-1 py-0.5 shrink-0 cursor-pointer"
                    >
                        Show less
                    </button>
                )}
            </div>
        );
    };

    const renderQuickAssign = () => {
        const isManager = roles.some((r) => r.name === 'manager');

        if (!isManager) {
            return <span className="text-muted-foreground text-[10px]">—</span>;
        }

        if (unassignedMarkets.length > 0) {
            return (
                <select
                    value=""
                    onChange={(e) => handleAssignMarket(e.target.value)}
                    className="h-8 rounded border border-input bg-card px-2 pr-8 py-1 text-[11px] focus-visible:outline-none max-w-[140px]"
                >
                    <option value="">Assign market...</option>
                    {unassignedMarkets.map((m) => (
                        <option key={m.id} value={m.id}>
                            {m.name}
                        </option>
                    ))}
                </select>
            );
        }

        return <span className="text-muted-foreground text-[10px]">All Assigned</span>;
    };

    return (
        <tr className="hover:bg-muted/20 transition-colors">
            <td className="p-3.5">
                <div className="flex items-center gap-2">
                    {hasPhoto ? (
                        <img
                            src={user.profile_photo_url ?? undefined}
                            alt={user.name}
                            className="size-8 shrink-0 rounded-full object-cover"
                        />
                    ) : (
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-mono text-xs font-bold uppercase">
                            {(user.name || '?').charAt(0)}
                        </div>
                    )}
                    <div className="min-w-0">
                        <p className="font-bold text-foreground">
                            {user.name}
                            {isSelf && (
                                <span className="ml-1.5 text-[10px] font-normal text-muted-foreground">
                                    (you)
                                </span>
                            )}
                        </p>
                        <p className="text-[11px] text-muted-foreground">
                            <EmailLink email={user.email} className="text-[11px]" />
                        </p>
                        {(user.company || user.phone) && (
                            <p className="text-[10px] text-muted-foreground/80 truncate">
                                {user.company}
                                {user.company && user.phone ? ' · ' : ''}
                                {user.phone}
                            </p>
                        )}
                    </div>
                </div>
            </td>
            <td className="p-3.5">
                <div className="flex flex-wrap gap-1">
                    {roles.length === 0 ? (
                        <Badge variant="outline" className="text-[10px]">
                            No role
                        </Badge>
                    ) : (
                        roles.map((role) => (
                            <Badge key={role.id} variant="secondary" className="capitalize text-[10px] whitespace-nowrap">
                                {ROLE_LABELS[role.name] ?? role.name.replace('_', ' ')}
                            </Badge>
                        ))
                    )}
                </div>
            </td>
            <td className="p-3.5">
                {user.is_active ? (
                    <Badge
                        variant="outline"
                        className="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px]"
                    >
                        Active
                    </Badge>
                ) : (
                    <Badge variant="secondary" className="gap-1 text-muted-foreground text-[10px]">
                        Inactive
                    </Badge>
                )}
            </td>
            <td className="p-3.5 min-w-[200px]">
                {renderAssignedMarkets()}
            </td>
            <td className="p-3.5">
                {renderQuickAssign()}
            </td>
            <td className="p-3.5">
                <div className="flex items-center justify-end gap-1.5">
                    <Button asChild variant="outline" size="sm" className="h-7 text-[11px] gap-1 px-2">
                        <Link href={route('admin.users.edit', user.hash_id || user.id)}>
                            <Pencil className="size-3" /> Edit
                        </Link>
                    </Button>
                    {!isSelf && (
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onPassword(user)}
                                className="h-7 text-[11px] gap-1 px-2"
                            >
                                <KeyRound className="size-3" /> Password
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onToggleActive(user)}
                                className="h-7 text-[11px] px-2"
                            >
                                {user.is_active ? 'Deactivate' : 'Activate'}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => onDelete(user)}
                                className="h-7 text-[11px] text-destructive hover:bg-destructive/10 px-2"
                                aria-label={`Delete ${user.name}`}
                            >
                                <Trash2 className="size-3" />
                            </Button>
                        </>
                    )}
                </div>
            </td>
        </tr>
    );
}
