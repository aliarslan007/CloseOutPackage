import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Plus, Search, Settings2, XCircle } from 'lucide-react';
import { CustomerAccessItem, CustomerItem } from './customer-card';

export interface ProjectOption {
    id: number;
    name: string;
}

export interface ManageAccessDialogProps {
    customer: CustomerItem | null;
    projects: ProjectOption[];
    isGranting: boolean;
    onClose: () => void;
    onGrantAccess: (selectedProjectIds: number[]) => void;
    onRevokeAccess: (customer: CustomerItem, access: CustomerAccessItem) => void;
}

export function ManageAccessDialog({
    customer,
    projects,
    isGranting,
    onClose,
    onGrantAccess,
    onRevokeAccess,
}: Readonly<ManageAccessDialogProps>) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [selectedProjectIds, setSelectedProjectIds] = React.useState<number[]>([]);

    React.useEffect(() => {
        if (!customer) {
            setSelectedProjectIds([]);
            setSearchQuery('');
        }
    }, [customer]);

    const assignedProjectIds = React.useMemo(() => {
        if (!customer) return new Set<number>();
        return new Set((customer.customer_accesses ?? []).map((a) => a.project_id));
    }, [customer]);

    const availableProjects = React.useMemo(() => {
        return projects.filter((p) => !assignedProjectIds.has(p.id));
    }, [projects, assignedProjectIds]);

    const filteredAvailableProjects = React.useMemo(() => {
        if (!searchQuery.trim()) return availableProjects;
        const q = searchQuery.toLowerCase();
        return availableProjects.filter((p) => p.name.toLowerCase().includes(q));
    }, [availableProjects, searchQuery]);

    const handleToggleProjectSelect = (projectId: number) => {
        setSelectedProjectIds((prev) =>
            prev.includes(projectId) ? prev.filter((id) => id !== projectId) : [...prev, projectId]
        );
    };

    if (!customer) return null;

    return (
        <Dialog open={Boolean(customer)} onOpenChange={(open) => !open && !isGranting && onClose()}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="font-heading text-lg font-bold flex items-center gap-2">
                        <Settings2 className="size-5 text-primary" />
                        Manage Project Access — {customer.name}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 pt-2">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 divide-y md:divide-y-0 md:divide-x divide-border">
                        {/* Left Panel: Available Projects */}
                        <div className="space-y-3 pr-0 md:pr-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold text-foreground uppercase tracking-wide">
                                    Available Projects ({availableProjects.length})
                                </h3>
                                {selectedProjectIds.length > 0 && (
                                    <span className="text-[11px] text-primary font-semibold">
                                        {selectedProjectIds.length} selected
                                    </span>
                                )}
                            </div>

                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 size-3.5 text-muted-foreground" />
                                <Input
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Search projects..."
                                    className="pl-8 h-8 text-xs"
                                />
                            </div>

                            <div className="max-h-60 overflow-y-auto space-y-1.5 pr-1 border rounded-lg p-2 bg-muted/10">
                                {filteredAvailableProjects.length === 0 ? (
                                    <p className="text-center py-6 text-xs text-muted-foreground">
                                        {searchQuery
                                            ? 'No projects match your search.'
                                            : 'All projects have been assigned.'}
                                    </p>
                                ) : (
                                    filteredAvailableProjects.map((project) => {
                                        const isSelected = selectedProjectIds.includes(project.id);
                                        return (
                                            <label
                                                key={project.id}
                                                className={`flex items-center gap-2.5 p-2 rounded-md text-xs cursor-pointer transition-colors border ${
                                                    isSelected
                                                        ? 'bg-primary/10 border-primary/40 text-foreground'
                                                        : 'hover:bg-accent border-transparent text-muted-foreground'
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={isSelected}
                                                    onChange={() => handleToggleProjectSelect(project.id)}
                                                    className="size-4 rounded border-border text-primary focus:ring-primary accent-primary"
                                                />
                                                <span className="truncate font-medium">{project.name}</span>
                                            </label>
                                        );
                                    })
                                )}
                            </div>

                            <Button
                                size="sm"
                                onClick={() => onGrantAccess(selectedProjectIds)}
                                disabled={selectedProjectIds.length === 0 || isGranting}
                                loading={isGranting}
                                className="w-full gap-2 text-xs"
                            >
                                <Plus className="size-3.5" /> Assign Selected ({selectedProjectIds.length})
                            </Button>
                        </div>

                        {/* Right Panel: Assigned Projects */}
                        <div className="space-y-3 pt-4 md:pt-0 pl-0 md:pl-4">
                            <h3 className="text-xs font-bold text-foreground uppercase tracking-wide">
                                Assigned Projects ({(customer.customer_accesses ?? []).length})
                            </h3>

                            <div className="max-h-[300px] overflow-y-auto space-y-2 pr-1 border rounded-lg p-2 bg-muted/10">
                                {(customer.customer_accesses ?? []).length === 0 ? (
                                    <p className="text-center py-8 text-xs text-muted-foreground">
                                        No projects assigned to this customer yet.
                                    </p>
                                ) : (
                                    (customer.customer_accesses ?? []).map((access) => (
                                        <div
                                            key={access.id}
                                            className="flex items-center justify-between gap-2 p-2 rounded-md bg-card border border-border text-xs"
                                        >
                                            <span className="truncate font-medium text-foreground">
                                                {access.project?.name ?? 'Project'}
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => onRevokeAccess(customer, access)}
                                                className="h-6 text-[10px] gap-1 text-destructive hover:bg-destructive/10 shrink-0 px-2"
                                            >
                                                <XCircle className="size-3" /> Remove
                                            </Button>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
