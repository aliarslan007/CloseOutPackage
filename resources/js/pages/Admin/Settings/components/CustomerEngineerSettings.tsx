import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { usePermissions } from '@/lib/permissions';
import { router } from '@inertiajs/react';
import { Building2, FolderKanban, MapPin, Pencil, Plus, Trash2, UserRound, UsersRound } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { CustomerFormDialog } from './CustomerFormDialog';
import { EngineerFormDialog } from './EngineerFormDialog';
import { EngineerRow } from './EngineerRow';
import { EngineerWorkloadDialog } from './EngineerWorkloadDialog';
import { HierarchyGuideCard } from './HierarchyGuideCard';
import type { CustomerHierarchyPayload, CustomerItem, EngineerItem } from './customer-hierarchy-types';

function Metric({ icon, label, value }: Readonly<{ icon: ReactNode; label: string; value: number }>) {
    return (
        <div className="flex items-center gap-3 rounded-xl border border-border/80 bg-card px-4 py-3 shadow-2xs">
            <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                {icon}
            </span>
            <div>
                <p className="text-lg font-bold leading-none text-foreground">{value}</p>
                <p className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{label}</p>
            </div>
        </div>
    );
}

export function CustomerEngineerSettings({
    hierarchy,
}: Readonly<{ hierarchy: CustomerHierarchyPayload }>) {
    const { customers, regions, metrics } = hierarchy;
    const { isAdmin } = usePermissions();

    const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(customers[0]?.id ?? null);
    const [customerDialog, setCustomerDialog] = useState<{ open: boolean; customer: CustomerItem | null }>({
        open: false,
        customer: null,
    });
    const [engineerDialog, setEngineerDialog] = useState<{
        open: boolean;
        engineer: EngineerItem | null;
        marketId: number | null;
    }>({ open: false, engineer: null, marketId: null });
    const [deleteCustomer, setDeleteCustomer] = useState<CustomerItem | null>(null);
    const [deleteEngineer, setDeleteEngineer] = useState<EngineerItem | null>(null);
    const [workloadEngineer, setWorkloadEngineer] = useState<EngineerItem | null>(null);
    const [deleting, setDeleting] = useState(false);

    const selectedCustomer = customers.find((c) => c.id === selectedCustomerId) ?? customers[0] ?? null;

    // Engineers grouped by their region (market), which is mandatory on create.
    const grouped = useMemo(() => {
        const map = new Map<number, EngineerItem[]>();

        for (const engineer of selectedCustomer?.engineers ?? []) {
            const list = map.get(engineer.market_id) ?? [];
            list.push(engineer);
            map.set(engineer.market_id, list);
        }

        return [...map.entries()];
    }, [selectedCustomer]);

    const deleteCustomerDescription = useMemo(() => {
        if (!deleteCustomer) return '';
        const count = deleteCustomer.projects_count ?? 0;
        if (count > 0) {
            const suffix = count === 1 ? '' : 's';
            return `"${deleteCustomer.name}" still has ${count} project${suffix} linked to it, so it cannot be deleted. Reassign or remove those projects in the Projects module first.`;
        }
        return `Delete "${deleteCustomer.name}" and all of its engineers? This removes their Customer Portal logins too.`;
    }, [deleteCustomer]);

    const setDefault = (engineer: EngineerItem) => {
        router.put(
            route('admin.settings.customer-engineers.update', engineer.hash_id),
            { is_default: true },
            { preserveScroll: true }
        );
    };

    const confirmDelete = (url: string, clear: () => void) => {
        setDeleting(true);
        router.delete(url, {
            preserveScroll: true,
            onSuccess: clear,
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <div className="space-y-5">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <Metric icon={<Building2 className="size-4" />} label="Customers" value={metrics.customers} />
                <Metric icon={<MapPin className="size-4" />} label="Regions" value={metrics.regions} />
                <Metric
                    icon={<UserRound className="size-4" />}
                    label="Active Engineers"
                    value={metrics.active_engineers}
                />
            </div>

            {/* Guide Card for Layman Users */}
            <HierarchyGuideCard />

            {/* Customer selector */}
            <div className="flex flex-wrap items-center gap-2">
                {customers.map((customer) => (
                    <button
                        key={customer.id}
                        type="button"
                        onClick={() => setSelectedCustomerId(customer.id)}
                        className={cn(
                            'cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors',
                            selectedCustomer?.id === customer.id
                                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                : 'border-border bg-card text-muted-foreground hover:text-foreground'
                        )}
                    >
                        {customer.name} <span className="opacity-70">({customer.code})</span>
                    </button>
                ))}
                {/* Company records are admin-only (CustomerPolicy); a manager
                    adds engineers under an existing company instead. */}
                {isAdmin && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8 cursor-pointer gap-1 text-xs"
                        onClick={() => setCustomerDialog({ open: true, customer: null })}
                    >
                        <Plus className="size-3.5" /> New Customer
                    </Button>
                )}
            </div>

            {selectedCustomer === null ? (
                <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    No customers yet. Create one to start adding engineers.
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="space-y-2.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="text-base font-bold tracking-tight text-foreground">
                                {selectedCustomer.name}
                            </h3>
                            <Badge variant="outline" className="px-1.5 font-mono text-[10px]">
                                {selectedCustomer.code}
                            </Badge>
                            <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                <UsersRound className="size-3" />
                                {selectedCustomer.engineers.length} engineer
                                {selectedCustomer.engineers.length === 1 ? '' : 's'}
                            </span>
                            {!selectedCustomer.is_active && (
                                <Badge variant="secondary" className="text-[10px]">
                                    Inactive
                                </Badge>
                            )}
                        </div>

                        {/* Project count starts the rule; it runs to the customer actions. */}
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                                    selectedCustomer.projects_count > 0
                                        ? 'bg-amber-500/10 text-amber-700 ring-1 ring-amber-500/20 dark:text-amber-300'
                                        : 'bg-muted/60 text-muted-foreground ring-1 ring-border'
                                )}
                                title="Projects linked to this customer (across all engineers). A customer with projects cannot be deleted."
                            >
                                <FolderKanban className="size-3.5" />
                                {selectedCustomer.projects_count} project
                                {selectedCustomer.projects_count === 1 ? '' : 's'}
                            </span>
                            <div
                                className="h-px min-w-8 flex-1 bg-border"
                                role="presentation"
                                aria-hidden
                            />
                            {isAdmin && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-8 cursor-pointer gap-1.5 text-xs"
                                        onClick={() =>
                                            setCustomerDialog({ open: true, customer: selectedCustomer })
                                        }
                                    >
                                        <Pencil className="size-3.5" /> Edit Customer
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-8 cursor-pointer gap-1.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        onClick={() => setDeleteCustomer(selectedCustomer)}
                                    >
                                        <Trash2 className="size-3.5" /> Delete Customer
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>

                    {grouped.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border/80 bg-card/50 px-6 py-10 text-center">
                            <UsersRound className="size-8 text-muted-foreground/50" aria-hidden />
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-foreground">
                                    No engineers for {selectedCustomer.name} yet
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Add engineers by region so projects can auto-assign a default contact.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-1.5"
                                onClick={() => setEngineerDialog({ open: true, engineer: null, marketId: null })}
                            >
                                <Plus className="size-3.5" /> Add Engineer
                            </Button>
                        </div>
                    ) : (
                        grouped.map(([marketId, engineers]) => (
                            <div
                                key={marketId}
                                className="overflow-hidden rounded-md border border-border/80 bg-card shadow-2xs"
                            >
                                <div className="flex items-center justify-between gap-2 border-b border-border/60 bg-muted/25 px-4 py-2.5">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <MapPin className="size-3.5 shrink-0 text-primary" />
                                        <span className="truncate text-sm font-semibold text-foreground">
                                            {engineers[0]?.market_name ?? 'Region'}
                                        </span>
                                        <span className="text-[11px] text-muted-foreground">
                                            {engineers.length}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 cursor-pointer gap-1 text-xs"
                                        onClick={() => setEngineerDialog({ open: true, engineer: null, marketId })}
                                    >
                                        <Plus className="size-3.5" /> Add Engineer
                                    </Button>
                                </div>
                                <ul className="divide-y divide-border/50">
                                    {engineers.map((engineer) => (
                                        <EngineerRow
                                            key={engineer.id}
                                            engineer={engineer}
                                            onSetDefault={setDefault}
                                            onWorkload={setWorkloadEngineer}
                                            onEdit={(target) =>
                                                setEngineerDialog({
                                                    open: true,
                                                    engineer: target,
                                                    marketId: target.market_id,
                                                })
                                            }
                                            onRemove={setDeleteEngineer}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                </div>
            )}

            <CustomerFormDialog
                open={customerDialog.open}
                onOpenChange={(open) => setCustomerDialog((state) => ({ ...state, open }))}
                customer={customerDialog.customer}
            />

            <EngineerFormDialog
                open={engineerDialog.open}
                onOpenChange={(open) => setEngineerDialog((state) => ({ ...state, open }))}
                customer={selectedCustomer}
                regions={regions}
                engineer={engineerDialog.engineer}
                defaultMarketId={engineerDialog.marketId}
            />

            <EngineerWorkloadDialog
                engineer={workloadEngineer}
                onOpenChange={(open) => {
                    if (!open) {
                        setWorkloadEngineer(null);
                    }
                }}
            />

            <ConfirmDialog
                open={deleteCustomer !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setDeleteCustomer(null);
                    }
                }}
                title="Delete Customer"
                description={deleteCustomerDescription}
                confirmLabel="Delete Customer"
                variant="destructive"
                loading={deleting}
                onConfirm={() => {
                    if (deleteCustomer) {
                        confirmDelete(
                            route('admin.settings.customers.destroy', deleteCustomer.hash_id),
                            () => setDeleteCustomer(null)
                        );
                    }
                }}
            />

            <ConfirmDialog
                open={deleteEngineer !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setDeleteEngineer(null);
                    }
                }}
                title="Remove engineer"
                description={`Remove "${deleteEngineer?.name}" from ${deleteEngineer?.market_name ?? 'this region'}? Their Customer Portal login is removed too. Existing PDFs keep their saved contact details.`}
                confirmLabel="Remove"
                variant="destructive"
                loading={deleting}
                onConfirm={() => {
                    if (deleteEngineer) {
                        confirmDelete(
                            route('admin.settings.customer-engineers.destroy', deleteEngineer.hash_id),
                            () => setDeleteEngineer(null)
                        );
                    }
                }}
            />
        </div>
    );
}

export default CustomerEngineerSettings;
