import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { SearchInput } from '@/components/shared/search-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ChangePasswordDialog } from '@/components/users/change-password-dialog';
import { ROLE_LABELS, UserListItem, UserRow } from '@/components/users/user-row';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Activity, User, X } from 'lucide-react';
import * as React from 'react';

export interface UserFilters {
    search: string;
    role: string;
    is_active: string;
    market_filter: string;
}

export type AdminUsersPageProps = PageProps<{
    users: UserListItem[];
    markets: Array<{ id: number; name: string }>;
    filters: Partial<UserFilters>;
}>;

// The Users module is for internal staff only — customer accounts are portal
// users managed under App Settings → Customer Account Settings. They are not a
// filter option here, and the server never lists them in the table either.
const ROLE_OPTIONS = Object.entries(ROLE_LABELS)
    .filter(([value]) => value !== 'customer')
    .map(([value, label]) => ({ value, label }));

export function AdminUsersIndex() {
    useFlashToasts();

    const { users, auth, markets, filters: serverFilters } = usePage<AdminUsersPageProps>().props;
    const [passwordTarget, setPasswordTarget] = React.useState<UserListItem | null>(null);
    const [toggleTarget, setToggleTarget] = React.useState<UserListItem | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<UserListItem | null>(null);
    const [isToggling, setIsToggling] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const [filters, setFilters] = React.useState<UserFilters>({
        search: serverFilters.search ?? '',
        role: serverFilters.role ?? '',
        is_active: serverFilters.is_active ?? '',
        market_filter: serverFilters.market_filter ?? '',
    });

    const pushFilters = React.useCallback((next: Partial<UserFilters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        if (merged.search) params.search = merged.search;
        if (merged.role) params.role = merged.role;
        if (merged.is_active) params.is_active = merged.is_active;
        if (merged.market_filter) params.market_filter = merged.market_filter;

        router.get(route('admin.users.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, [filters]);

    const handleSearchChange = React.useCallback((value: string) => {
        setFilters((prev) => ({ ...prev, search: value }));
        pushFilters({ search: value });
    }, [pushFilters]);

    const handleFilterChange = (key: keyof UserFilters, value: string) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
        pushFilters({ [key]: value });
    };

    const handleClearFilters = () => {
        const cleared = { search: '', role: '', is_active: '', market_filter: '' };
        setFilters(cleared);
        pushFilters(cleared);
    };

    const hasActiveFilters = filters.search || filters.role || filters.is_active || filters.market_filter;

    const handleToggle = () => {
        if (!toggleTarget) return;
        setIsToggling(true);
        router.post(route('admin.users.toggle-active', toggleTarget.id), undefined, {
            onSuccess: () => {
                setToggleTarget(null);
                setIsToggling(false);
            },
            onError: () => setIsToggling(false),
        });
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        setIsDeleting(true);
        router.delete(route('admin.users.destroy', deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    if (!users) {
        return (
            <AppLayout title="Users" breadcrumb={[{ label: 'Users' }]}>
                <Head title="Users" />
                <EmptyCard
                    title="Users unavailable"
                    description="The user list could not be loaded."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Users"
            breadcrumb={[{ label: 'Users' }]}
            headerActions={
                <Button asChild className="gap-2 text-xs shrink-0 h-9">
                    <Link href={route('admin.users.create')}>
                        <User className="size-4" /> Create User
                    </Link>
                </Button>
            }
        >
            <Head title="Users" />
            <div className="space-y-3">
                {/* Dedicated Filter & Search Toolbar */}
                <div className="rounded-xl border bg-card p-3 shadow-2xs">
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <SearchInput
                            value={filters.search}
                            onSearchChange={handleSearchChange}
                            placeholder="Search by name or email..."
                            className="w-full sm:w-64 shrink-0"
                        />
                        <div className="grid grid-cols-2 sm:flex sm:items-center gap-2 flex-1 min-w-0">
                            <select
                                value={filters.role}
                                onChange={(e) => handleFilterChange('role', e.target.value)}
                                className="h-9 w-full sm:w-auto rounded-md border border-input bg-card px-3 pr-8 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0"
                                aria-label="Filter by role"
                            >
                                <option value="">All Roles</option>
                                {ROLE_OPTIONS.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                            <select
                                value={filters.is_active}
                                onChange={(e) => handleFilterChange('is_active', e.target.value)}
                                className="h-9 w-full sm:w-auto rounded-md border border-input bg-card px-3 pr-8 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0"
                                aria-label="Filter by status"
                            >
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <select
                                value={filters.market_filter}
                                onChange={(e) => handleFilterChange('market_filter', e.target.value)}
                                className="h-9 w-full sm:w-auto rounded-md border border-input bg-card px-3 pr-8 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0 col-span-2 sm:col-span-1"
                                aria-label="Filter by market assignment"
                            >
                                <option value="">All Markets</option>
                                <option value="with_markets">With Markets</option>
                                <option value="without_markets">Without Markets</option>
                            </select>
                        </div>
                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                                className="h-9 gap-1.5 text-xs text-muted-foreground shrink-0 cursor-pointer"
                            >
                                <X className="size-3" /> Clear
                            </Button>
                        )}
                    </div>
                </div>

                {users.length === 0 ? (
                    <EmptyCard
                        title="No users found"
                        description={hasActiveFilters ? 'Try adjusting your filters.' : 'Create the first user account to start assigning roles and market access.'}
                        icon={<User className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <Card className="rounded-md border border-border/80 shadow-2xs overflow-hidden">
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-muted/40 border-b border-border text-muted-foreground uppercase text-[11px] tracking-wider">
                                        <tr>
                                            <th className="p-3.5 whitespace-nowrap">User</th>
                                            <th className="p-3.5 whitespace-nowrap">Roles</th>
                                            <th className="p-3.5 whitespace-nowrap">Status</th>
                                            <th className="p-3.5 whitespace-nowrap">Assigned Markets</th>
                                            <th className="p-3.5 whitespace-nowrap">Quick Assign</th>
                                            <th className="p-3.5 text-right whitespace-nowrap">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {users.map((user) => (
                                            <UserRow
                                                key={user.id}
                                                user={user}
                                                markets={markets}
                                                isSelf={user.id === auth.user.id}
                                                onPassword={setPasswordTarget}
                                                onToggleActive={setToggleTarget}
                                                onDelete={setDeleteTarget}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {passwordTarget && (
                <ChangePasswordDialog
                    key={passwordTarget.id}
                    open={Boolean(passwordTarget)}
                    onOpenChange={(open) => {
                        if (!open) setPasswordTarget(null);
                    }}
                    userId={passwordTarget.id}
                    userName={passwordTarget.name}
                />
            )}

            <ConfirmDialog
                open={Boolean(toggleTarget)}
                onOpenChange={(open) => {
                    if (!open && !isToggling) setToggleTarget(null);
                }}
                title={toggleTarget?.is_active ? 'Deactivate user' : 'Activate user'}
                description={
                    toggleTarget?.is_active
                        ? `Deactivate "${toggleTarget.name}"? They will no longer be able to sign in.`
                        : `Activate "${toggleTarget?.name}"? They will regain sign-in access.`
                }
                confirmLabel={toggleTarget?.is_active ? 'Deactivate' : 'Activate'}
                variant={toggleTarget?.is_active ? 'destructive' : 'default'}
                loading={isToggling}
                onConfirm={handleToggle}
            />

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteTarget(null);
                }}
                title="Delete user"
                description={`Delete "${deleteTarget?.name}"? This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

export default AdminUsersIndex;
