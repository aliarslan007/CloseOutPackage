import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmailLink } from '@/components/shared/contact-links';
import { FolderKanban, Settings2, User, XCircle } from 'lucide-react';

export interface CustomerAccessItem {
    id: number;
    project_id: number;
    expires_at?: string | null;
    created_at: string;
    project?: { id: number; name: string } | null;
    granted_by?: { id: number; name: string } | null;
}

export interface CustomerItem {
    id: number;
    name: string;
    email: string;
    is_active?: boolean;
    customer_accesses?: CustomerAccessItem[];
}

export interface CustomerCardProps {
    customer: CustomerItem;
    onManageAccess: (customer: CustomerItem) => void;
    onRevokeAccess: (customer: CustomerItem, access: CustomerAccessItem) => void;
}

export function CustomerCard({
    customer,
    onManageAccess,
    onRevokeAccess,
}: Readonly<CustomerCardProps>) {
    const accesses = customer.customer_accesses ?? [];

    return (
        <div className="bg-card rounded-xl border border-border/80 shadow-2xs overflow-hidden flex flex-col justify-between">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-3.5 sm:px-4 py-3">
                <div className="flex items-center gap-2.5 min-w-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-bold uppercase">
                        {(customer.name || '?').charAt(0)}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap">
                            <p className="font-heading text-xs font-bold text-foreground truncate">
                                {customer.name}
                            </p>
                            <Badge variant="secondary" className="text-[10px] shrink-0">
                                {accesses.length} Project{accesses.length === 1 ? '' : 's'}
                            </Badge>
                        </div>
                        <p className="text-[11px] text-muted-foreground truncate">
                            <EmailLink email={customer.email} className="text-[11px]" />
                        </p>
                    </div>
                </div>

                <div className="flex items-center justify-between sm:justify-end gap-2 shrink-0 border-t border-border/40 sm:border-t-0 pt-2 sm:pt-0">
                    {customer.is_active ? (
                        <Badge
                            variant="outline"
                            className="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px] shrink-0"
                        >
                            <User className="size-3" /> Active
                        </Badge>
                    ) : (
                        <Badge
                            variant="secondary"
                            className="gap-1 text-muted-foreground text-[10px] shrink-0"
                        >
                            <XCircle className="size-3" /> Inactive
                        </Badge>
                    )}
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => onManageAccess(customer)}
                        className="h-8 gap-1.5 text-xs shrink-0"
                    >
                        <Settings2 className="size-3.5" /> Manage Access
                    </Button>
                </div>
            </div>

            <div className="space-y-2 p-4 flex-1">
                {accesses.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-4 text-center text-[11px] text-muted-foreground">
                        No project access granted yet. Click &quot;Manage Access&quot; to assign projects.
                    </p>
                ) : (
                    accesses.map((access) => (
                        <div
                            key={access.id}
                            className="flex items-center justify-between gap-2 rounded-lg bg-muted/20 px-3 py-2"
                        >
                            <div className="min-w-0 space-y-0.5">
                                <p className="text-xs font-medium text-foreground truncate flex items-center gap-1.5">
                                    <FolderKanban className="size-3.5 text-primary shrink-0" />
                                    {access.project?.name ?? 'Project removed'}
                                </p>
                                <p className="text-[10px] text-muted-foreground">
                                    Granted by {access.granted_by?.name ?? 'System'}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => onRevokeAccess(customer, access)}
                                className="h-7 text-[11px] gap-1 text-destructive hover:bg-destructive/10 shrink-0"
                                aria-label={`Revoke access to ${access.project?.name ?? 'project'}`}
                            >
                                <XCircle className="size-3" /> Revoke
                            </Button>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
