import * as React from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyCard } from '@/components/shared/empty-card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import { PageProps } from '@/types/inertia';
import { Activity, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { CustomerCard, CustomerAccessItem, CustomerItem } from '@/components/admin/customers/customer-card';
import { ManageAccessDialog, ProjectOption } from '@/components/admin/customers/manage-access-dialog';

export type AdminCustomersPageProps = PageProps<{
    customers: CustomerItem[];
    projects: ProjectOption[];
}>;

export function AdminCustomersIndex() {
    useFlashToasts();

    const { customers = [], projects = [] } = usePage<AdminCustomersPageProps>().props;

    const [selectedCustomer, setSelectedCustomer] = React.useState<CustomerItem | null>(null);
    const [isGranting, setIsGranting] = React.useState(false);

    const [revokeTarget, setRevokeTarget] = React.useState<{
        customer: CustomerItem;
        access: CustomerAccessItem;
    } | null>(null);
    const [isRevoking, setIsRevoking] = React.useState(false);

    const handleGrantAccess = (selectedProjectIds: number[]) => {
        if (!selectedCustomer || selectedProjectIds.length === 0) return;

        setIsGranting(true);

        router.post(
            route('admin.customers.access.store', selectedCustomer.id),
            {
                user_id: selectedCustomer.id,
                project_ids: selectedProjectIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsGranting(false);
                    setSelectedCustomer(null);
                },
                onError: () => {
                    toast.error('Could not grant access. Please try again.');
                    setIsGranting(false);
                },
            }
        );
    };

    const revokeProjectFromCustomer = (
        customer: CustomerItem | null,
        projectId: number
    ): CustomerItem | null => {
        if (!customer) {
            return null;
        }

        return {
            ...customer,
            customer_accesses: (customer.customer_accesses ?? []).filter(
                (access) => access.project_id !== projectId
            ),
        };
    };

    const handleRevoke = () => {
        if (!revokeTarget) return;
        setIsRevoking(true);
        router.delete(
            route('admin.customers.access.destroy', {
                user: revokeTarget.customer.id,
                project: revokeTarget.access.project_id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    const projectId = revokeTarget.access.project_id;
                    setRevokeTarget(null);
                    setIsRevoking(false);
                    if (selectedCustomer?.id === revokeTarget.customer.id) {
                        setSelectedCustomer((prev) => revokeProjectFromCustomer(prev, projectId));
                    }
                },
                onError: () => setIsRevoking(false),
            }
        );
    };

    if (!customers) {
        return (
            <AppLayout
                title="Customer Access"
                breadcrumb={[{ label: 'Customer Access' }]}
            >
                <Head title="Customer Access" />
                <EmptyCard
                    title="Customers unavailable"
                    description="The customer list could not be loaded."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Customer Access"
            breadcrumb={[{ label: 'Customer Access' }]}
        >
            <Head title="Customer Access" />
            <div className="space-y-2">
                {customers.length === 0 ? (
                    <EmptyCard
                        title="No customer accounts"
                        description="Users with the customer role will appear here to manage their project permissions."
                        icon={<ShieldCheck className="size-10 text-muted-foreground" />}
                    />
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {customers.map((customer) => (
                            <CustomerCard
                                key={customer.id}
                                customer={customer}
                                onManageAccess={(c) => setSelectedCustomer(c)}
                                onRevokeAccess={(c, a) => setRevokeTarget({ customer: c, access: a })}
                            />
                        ))}
                    </div>
                )}
            </div>

            <ManageAccessDialog
                customer={selectedCustomer}
                projects={projects}
                isGranting={isGranting}
                onClose={() => setSelectedCustomer(null)}
                onGrantAccess={handleGrantAccess}
                onRevokeAccess={(c, a) => setRevokeTarget({ customer: c, access: a })}
            />

            <ConfirmDialog
                open={Boolean(revokeTarget)}
                onOpenChange={(open) => {
                    if (!open && !isRevoking) setRevokeTarget(null);
                }}
                title="Revoke customer access"
                description={`Remove "${revokeTarget?.customer.name}"'s access to "${revokeTarget?.access.project?.name ?? 'this project'}"?`}
                confirmLabel="Revoke"
                variant="destructive"
                loading={isRevoking}
                onConfirm={handleRevoke}
            />
        </AppLayout>
    );
}

export default AdminCustomersIndex;
