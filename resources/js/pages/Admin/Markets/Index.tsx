import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ROLE_LABELS } from '@/components/users/user-row';
import { Activity, CheckCircle2, MapPin, Pencil, Plus, Trash2, X, XCircle } from 'lucide-react';
import * as React from 'react';

export interface MarketUser {
    id: number;
    name: string;
    email: string;
    roles?: Array<{ id: number; name: string }>;
}

export interface MarketItem {
    id: number;
    hash_id?: string;
    name: string;
    slug: string;
    description?: string | null;
    is_active: boolean;
    users_count?: number;
    users?: MarketUser[];
}

export type AdminMarketsPageProps = PageProps<{
    markets: MarketItem[];
    assignableUsers: MarketUser[];
}>;

interface MarketAssignedMembersCellProps {
    currentUsers: MarketUser[];
    marketId: number;
    onRevoke: (marketId: number, userId: number) => void;
}

function MarketAssignedMembersCell({
    currentUsers,
    marketId,
    onRevoke,
}: Readonly<MarketAssignedMembersCellProps>) {
    const [expanded, setExpanded] = React.useState(false);
    if (currentUsers.length === 0) {
        return <span className="text-muted-foreground text-[10px]">None</span>;
    }
    const visibleUsers = expanded ? currentUsers : currentUsers.slice(0, 3);
    const remainingCount = currentUsers.length - 3;

    return (
        <div className="flex flex-wrap gap-1 max-w-[260px] items-center">
            {visibleUsers.map((user) => (
                <Badge
                    key={user.id}
                    variant="secondary"
                    className="gap-1 px-1.5 py-0.5 text-[10px] text-foreground flex items-center bg-muted/65 border shrink-0"
                >
                    <span className="truncate max-w-[90px]" title={user.name}>
                        {user.name}
                    </span>
                    <button
                        type="button"
                        onClick={() => onRevoke(marketId, user.id)}
                        className="hover:text-destructive shrink-0 ml-0.5 cursor-pointer"
                        title={`Revoke ${user.name}`}
                        aria-label={`Revoke access for ${user.name}`}
                    >
                        <X className="size-2.5" />
                    </button>
                </Badge>
            ))}
            {!expanded && remainingCount > 0 && (
                <button
                    type="button"
                    onClick={() => setExpanded(true)}
                    className="text-[10px] font-medium text-primary hover:underline px-1 py-0.5 rounded bg-primary/10 border border-primary/20 shrink-0 cursor-pointer"
                    title="Click to view all assigned members"
                >
                    +{remainingCount} more
                </button>
            )}
            {expanded && remainingCount > 0 && (
                <button
                    type="button"
                    onClick={() => setExpanded(false)}
                    className="text-[10px] font-medium text-muted-foreground hover:underline px-1 py-0.5 shrink-0 cursor-pointer"
                >
                    Show less
                </button>
            )}
        </div>
    );
}

export function AdminMarketsIndex() {
    useFlashToasts();

    const { markets, assignableUsers } = usePage<AdminMarketsPageProps>().props;
    const [deleteTarget, setDeleteTarget] = React.useState<MarketItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const handleDelete = () => {
        if (!deleteTarget) return;
        setIsDeleting(true);
        router.delete(route('admin.markets.destroy', deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    const handleQuickAssign = (marketId: number, userId: string) => {
        if (!userId) return;
        router.post(route('admin.markets.users.store', marketId), {
            user_id: Number(userId),
        });
    };

    const [revokeConfirm, setRevokeConfirm] = React.useState<{
        marketId: number;
        userId: number;
    } | null>(null);

    const handleQuickRevoke = (marketId: number, userId: number) => {
        setRevokeConfirm({ marketId, userId });
    };

    const confirmRevokeAction = () => {
        if (!revokeConfirm) return;
        router.delete(
            route('admin.markets.users.destroy', [revokeConfirm.marketId, revokeConfirm.userId]),
            {
                onFinish: () => setRevokeConfirm(null),
            }
        );
    };

    if (!markets) {
        return (
            <AppLayout
                title="Markets"
                breadcrumb={[{ label: 'Markets' }]}
            >
                <Head title="Markets" />
                <EmptyCard
                    title="Markets unavailable"
                    description="The market list could not be loaded. Please try refreshing the page."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Markets"
            breadcrumb={[{ label: 'Markets' }]}
            headerActions={
                <Button asChild className="gap-2 text-xs">
                    <Link href={route('admin.markets.create')}>
                        <Plus className="size-4" /> Create Market
                    </Link>
                </Button>
            }
        >
            <Head title="Markets" />
            <div className="space-y-2">
                {markets.length === 0 ? (
                    <EmptyCard
                        title="No markets configured"
                        description="Create your first market (for example Ohio or California OSP) to start scoping templates and projects."
                        icon={<MapPin className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <Card className="rounded-md border border-border/80 shadow-2xs overflow-hidden">
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-muted/40 border-b border-border text-muted-foreground uppercase text-[11px] tracking-wider">
                                        <tr>
                                            <th className="p-3.5 whitespace-nowrap">Market</th>
                                            <th className="p-3.5 whitespace-nowrap">Description</th>
                                            <th className="p-3.5 whitespace-nowrap">Status</th>
                                            <th className="p-3.5 whitespace-nowrap">Assigned Members</th>
                                            <th className="p-3.5 whitespace-nowrap">Quick Assign</th>
                                            <th className="p-3.5 text-right whitespace-nowrap">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {markets.map((market) => {
                                            const currentUsers = market.users ?? [];
                                            const eligibleToAssign = assignableUsers.filter(
                                                (u) => !currentUsers.some((cu) => cu.id === u.id)
                                            );

                                            return (
                                                <tr
                                                    key={market.id}
                                                    className="hover:bg-muted/20 transition-colors"
                                                >
                                                    <td className="p-3.5 max-w-[220px]">
                                                        <div className="flex items-center gap-2 min-w-0">
                                                            <MapPin className="size-4 text-primary shrink-0" />
                                                            <span className="font-bold text-foreground truncate" title={market.name}>
                                                                {market.name}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground max-w-[250px]">
                                                        <span className="line-clamp-2" title={market.description ?? ''}>
                                                            {market.description || 'No description provided.'}
                                                        </span>
                                                    </td>
                                                    <td className="p-3.5 whitespace-nowrap">
                                                        {market.is_active ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20"
                                                            >
                                                                <CheckCircle2 className="size-3" />{' '}
                                                                Active
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="secondary"
                                                                className="gap-1 text-muted-foreground"
                                                            >
                                                                <XCircle className="size-3" />{' '}
                                                                Inactive
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="p-3.5 min-w-[180px]">
                                                        <MarketAssignedMembersCell
                                                            currentUsers={currentUsers}
                                                            marketId={market.id}
                                                            onRevoke={handleQuickRevoke}
                                                        />
                                                    </td>
                                                    <td className="p-3.5">
                                                        {eligibleToAssign.length > 0 ? (
                                                            <select
                                                                value=""
                                                                onChange={(e) =>
                                                                    handleQuickAssign(
                                                                        market.id,
                                                                        e.target.value
                                                                    )
                                                                }
                                                                className="h-8 rounded border border-input bg-card px-2 py-1 text-[11px] focus-visible:outline-none max-w-[140px] truncate"
                                                            >
                                                                <option value="">
                                                                    Assign user...
                                                                </option>
                                                                {eligibleToAssign.map((user) => (
                                                                    <option
                                                                        key={user.id}
                                                                        value={user.id}
                                                                    >
                                                                        {user.name} ({ROLE_LABELS[user.roles?.[0]?.name ?? ''] ?? user.roles?.[0]?.name ?? 'Member'})
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        ) : (
                                                            <span className="text-muted-foreground text-[10px]">
                                                                Fully Assigned
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="p-3.5">
                                                        <div className="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                                            <Button
                                                                asChild
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-7 text-[11px] gap-1"
                                                            >
                                                                <Link
                                                                    href={route(
                                                                        'admin.markets.edit',
                                                                        market.hash_id || market.id
                                                                    )}
                                                                >
                                                                    <Pencil className="size-3" /> Edit
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => setDeleteTarget(market)}
                                                                className="h-7 text-[11px] gap-1 text-destructive hover:bg-destructive/10"
                                                            >
                                                                <Trash2 className="size-3" /> Delete
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteTarget(null);
                }}
                title="Delete market"
                description={`Delete "${deleteTarget?.name}"? Its templates stay on file but go inactive until they are linked to another market. This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />

            <ConfirmDialog
                open={Boolean(revokeConfirm)}
                onOpenChange={(open) => {
                    if (!open) setRevokeConfirm(null);
                }}
                title="Revoke Market Access"
                description="Are you sure you want to revoke market access for this user?"
                confirmLabel="Revoke Access"
                variant="destructive"
                onConfirm={confirmRevokeAction}
            />
        </AppLayout>
    );
}

export default AdminMarketsIndex;
