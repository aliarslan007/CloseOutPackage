import { buildProjectColumns, ProjectListItem } from '@/components/projects/project-table-columns';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { DataTable } from '@/components/shared/data-table';
import { EmptyCard } from '@/components/shared/empty-card';
import { ManagerMarketAlert } from '@/components/shared/manager-market-alert';
import { PrimaryTechnicianStar } from '@/components/projects/primary-technician-star';
import { SearchInput } from '@/components/shared/search-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/permissions';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Activity, Plus, X } from 'lucide-react';
import * as React from 'react';

export interface ProjectFilters {
    search: string;
    status: string;
    market_id: string;
    package_status: string;
    assignment: string;
    sort: string;
}

/** Shape of the filters prop as sent by the backend (ProjectController::index). */
export interface ProjectFilterParams {
    search?: string | null;
    status?: string | null;
    market_id?: number | null;
    package_status?: string | null;
    assignment?: string | null;
    sort?: string | null;
    direction?: string | null;
}

export type ProjectsIndexPageProps = PageProps<{
    projects: {
        data: ProjectListItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        [key: string]: unknown;
    };
    markets: Array<{ id: number; name: string }>;
    managers: Array<{ id: number; name: string; email: string }>;
    supervisors: Array<{ id: number; name: string; email: string }>;
    filters?: Partial<ProjectFilterParams>;
    /** Backend-computed via ProjectPolicy::create — managers need a market assigned. */
    can_create_project?: boolean;
}>;

const STATUS_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'completed', label: 'Completed' },
];

const SORT_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name_asc', label: 'Name (A to Z)' },
    { value: 'name_desc', label: 'Name (Z to A)' },
];

const PACKAGE_STATUS_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted for review' },
    { value: 'under_review', label: 'In review' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

/** Maps the friendly sort value to the backend (column, direction) pair. */
const SORT_PARAMS: Record<string, { sort: string; direction: string }> = {
    newest: { sort: 'created_at', direction: 'desc' },
    oldest: { sort: 'created_at', direction: 'asc' },
    name_asc: { sort: 'name', direction: 'asc' },
    name_desc: { sort: 'name', direction: 'desc' },
};

function sortValueFromParams(sort?: string | null, direction?: string | null): string {
    if (!sort) {
        return 'newest';
    }
    const match = Object.entries(SORT_PARAMS).find(
        ([, value]) => value.sort === sort && value.direction === direction
    );
    return match?.[0] ?? 'newest';
}

export function ProjectsIndex() {
    useFlashToasts();

    const {
        projects,
        auth,
        managers,
        supervisors,
        markets,
        errors,
        filters: serverFilters,
        can_create_project: canCreateProjectFromServer,
    } = usePage<ProjectsIndexPageProps>().props;

    const [filters, setFilters] = React.useState<ProjectFilters>({
        search: serverFilters?.search ?? '',
        status: serverFilters?.status ?? '',
        market_id: serverFilters?.market_id ? String(serverFilters.market_id) : '',
        package_status: serverFilters?.package_status ?? '',
        assignment: serverFilters?.assignment ?? '',
        sort: sortValueFromParams(serverFilters?.sort ?? null, serverFilters?.direction ?? null),
    });

    const pushFilters = React.useCallback(
        (next: Partial<ProjectFilters>) => {
            const merged = { ...filters, ...next };
            const params: Record<string, string> = {};
            if (merged.search) params.search = merged.search;
            if (merged.status) params.status = merged.status;
            if (merged.market_id) params.market_id = merged.market_id;
            if (merged.package_status) params.package_status = merged.package_status;
            if (merged.assignment) params.assignment = merged.assignment;
            const sort = SORT_PARAMS[merged.sort] ?? SORT_PARAMS.newest;
            if (sort.sort !== 'created_at' || sort.direction !== 'desc') {
                params.sort = sort.sort;
                params.direction = sort.direction;
            }

            router.get(route('projects.index'), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [filters]
    );

    const handleSearchChange = React.useCallback(
        (value: string) => {
            setFilters((prev) => ({ ...prev, search: value }));
            pushFilters({ search: value });
        },
        [pushFilters]
    );

    const handleFilterChange = (key: keyof ProjectFilters, value: string) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
        pushFilters({ [key]: value });
    };

    const handleClearFilters = () => {
        const cleared: ProjectFilters = {
            search: '',
            status: '',
            market_id: '',
            package_status: '',
            assignment: '',
            sort: 'newest',
        };
        setFilters(cleared);
        pushFilters(cleared);
    };

    const hasActiveFilters =
        filters.search ||
        filters.status ||
        filters.market_id ||
        filters.package_status ||
        filters.assignment ||
        filters.sort !== 'newest';
    const [deleteTarget, setDeleteTarget] = React.useState<ProjectListItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const [assignTarget, setAssignTarget] = React.useState<ProjectListItem | null>(null);
    const [isSavingAssign, setIsSavingAssign] = React.useState(false);

    const permissions = usePermissions();
    const isAdmin = permissions.isAdmin;
    const isManager = permissions.isManager;
    // Mirrors ProjectPolicy::create — admins can always create; managers need at
    // least one market assigned before the backend will accept a new project.
    const canCreateProject =
        canCreateProjectFromServer ?? (isAdmin || (isManager && markets.length > 0));

    // Initial state for Assign Modal form
    const [selectedManagerId, setSelectedManagerId] = React.useState<number | ''>('');
    const [selectedSupervisorIds, setSelectedSupervisorIds] = React.useState<number[]>([]);
    const [selectedPrimarySupervisorId, setSelectedPrimarySupervisorId] = React.useState<
        number | null
    >(null);

    React.useEffect(() => {
        if (assignTarget) {
            setSelectedManagerId(assignTarget.manager_id ?? assignTarget.manager?.id ?? '');
            setSelectedSupervisorIds(
                (assignTarget.field_supervisors as Array<{ id: number }>)?.map((s) => s.id) ?? []
            );
            setSelectedPrimarySupervisorId(assignTarget.primary_supervisor_id ?? null);
        } else {
            setSelectedManagerId('');
            setSelectedSupervisorIds([]);
            setSelectedPrimarySupervisorId(null);
        }
    }, [assignTarget]);

    const canEditProject = (project: ProjectListItem) =>
        isAdmin || (isManager && project.manager?.id === auth.user.id);

    const handleDelete = () => {
        if (!deleteTarget) return;
        setIsDeleting(true);
        router.delete(route('projects.destroy', deleteTarget.hash_id || deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    const [assignError, setAssignError] = React.useState<string | null>(null);

    const handleSaveAssignment = () => {
        if (!assignTarget) return;

        if (isAdmin && !selectedManagerId) {
            setAssignError('Project Manager is a required field.');
            return;
        }

        if (isManager && selectedSupervisorIds.length === 0) {
            setAssignError('You must assign at least one Supervisor.');
            return;
        }

        setAssignError(null);

        setIsSavingAssign(true);
        router.put(
            route('projects.update', assignTarget.hash_id || assignTarget.id),
            {
                // Only admins may reassign the manager; a manager's project
                // manager is fixed to themselves.
                ...(isAdmin && selectedManagerId ? { manager_id: selectedManagerId } : {}),
                supervisor_ids: selectedSupervisorIds,
                primary_supervisor_id: selectedPrimarySupervisorId,
            },
            {
                onSuccess: () => {
                    setAssignTarget(null);
                    setIsSavingAssign(false);
                },
                onError: () => setIsSavingAssign(false),
            }
        );
    };

    if (!projects) {
        return (
            <AppLayout
                title="Projects"
                breadcrumb={[{ label: 'Projects' }]}
            >
                <Head title="Projects" />
                <EmptyCard
                    title="Projects unavailable"
                    description="The project list could not be loaded. Please try refreshing the page."
                    icon={<Activity className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Projects"
            breadcrumb={[{ label: 'Projects' }]}
            headerActions={
                canCreateProject ? (
                    <Button asChild className="gap-2 text-xs shrink-0">
                        <Link href={route('projects.create')}>
                            <Plus className="size-4" /> Create Project
                        </Link>
                    </Button>
                ) : null
            }
        >
            <Head title="Projects" />
            <div className="space-y-2.5">
                {/* Responsive Filter Bar */}
                <div className="rounded-xl border bg-card p-2.5 shadow-2xs">
                    <div className="flex flex-col md:flex-row items-stretch md:items-center gap-2">
                        <SearchInput
                            value={filters.search}
                            onSearchChange={handleSearchChange}
                            placeholder="Search by project name..."
                            className="w-full md:w-64 shrink-0"
                        />
                        <div className="grid grid-cols-2 sm:grid-cols-4 md:flex md:items-center gap-2 flex-1 min-w-0">
                            <select
                                value={filters.status}
                                onChange={(e) => handleFilterChange('status', e.target.value)}
                                className="h-8.5 w-full rounded-md border border-input bg-card px-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                aria-label="Filter by status"
                            >
                                <option value="">All Status</option>
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={filters.market_id}
                                onChange={(e) => handleFilterChange('market_id', e.target.value)}
                                className="h-8.5 w-full rounded-md border border-input bg-card px-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring truncate"
                                aria-label="Filter by market"
                            >
                                <option value="">All Markets</option>
                                {markets.map((market) => (
                                    <option key={market.id} value={market.id}>
                                        {market.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={filters.package_status}
                                onChange={(e) => handleFilterChange('package_status', e.target.value)}
                                className="h-8.5 w-full rounded-md border border-input bg-card px-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                aria-label="Filter by package status"
                            >
                                <option value="">All Packages</option>
                                {PACKAGE_STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={filters.sort}
                                onChange={(e) => handleFilterChange('sort', e.target.value)}
                                className="h-8.5 w-full rounded-md border border-input bg-card px-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                aria-label="Sort projects"
                            >
                                {SORT_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                                className="h-8.5 gap-1.5 text-xs text-muted-foreground shrink-0 self-start md:self-auto"
                            >
                                <X className="size-3" /> Clear
                            </Button>
                        )}
                    </div>
                </div>

                <DataTable
                    className="[&_td]:py-1.5 [&_td]:px-2.5 [&_th]:py-1.5 [&_th]:px-2.5 [&_th]:h-8 text-xs"
                    columns={buildProjectColumns({
                        canEditProject,
                        canDeleteProject: (project) =>
                            permissions.canDeleteProject(project.manager_id ?? project.manager?.id),
                        canManageChangeOrders: permissions.canManageChangeOrders,
                        isAdmin: permissions.isAdmin,
                        isManager: permissions.isManager,
                        onDelete: setDeleteTarget,
                        onAssign: setAssignTarget,
                    })}
                    data={projects.data}
                    meta={{
                        current_page: projects.current_page,
                        last_page: projects.last_page,
                        per_page: projects.per_page,
                        total: projects.total,
                        from: projects.from,
                        to: projects.to,
                    }}
                    emptyTitle="No projects found"
                    emptyDescription={
                        hasActiveFilters
                            ? 'No projects match your search or filters. Try adjusting them.'
                            : 'Projects scoped to your access will appear here.'
                    }
                    onRowClick={(row) =>
                        router.visit(route('projects.show', row.hash_id || row.id))
                    }
                />
            </div>

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) setDeleteTarget(null);
                }}
                title="Delete project"
                description={`Delete "${deleteTarget?.name}"? This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={handleDelete}
            />

            {/* Quick Members Assignment Dialog Modal */}
            <ConfirmDialog
                open={Boolean(assignTarget)}
                onOpenChange={(open) => {
                    if (!open && !isSavingAssign) setAssignTarget(null);
                }}
                title={`Assign Members: ${assignTarget?.name}`}
                description={
                    isAdmin
                        ? 'Update the manager or supervisor scope assignments for this project site.'
                        : 'Assign field technicians to this project. You are the project manager.'
                }
                confirmLabel="Save Assignments"
                loading={isSavingAssign}
                onConfirm={handleSaveAssignment}
            >
                <div className="space-y-4 py-2 text-left">
                    {assignError && (
                        <div className="rounded-lg border border-destructive/25 bg-destructive/10 p-3 text-xs text-destructive">
                            <p className="font-medium">{assignError}</p>
                        </div>
                    )}
                    <ManagerMarketAlert message={errors.manager_id ?? ''} />
                    {/* Admins pick the manager; for a manager the project owner is fixed. */}
                    {isAdmin ? (
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="project_manager_assign"
                                className="text-xs font-semibold text-foreground flex items-center gap-1"
                            >
                                Project Manager <span className="text-destructive">*</span>
                            </Label>
                            {managers.length > 0 ? (
                                <select
                                    id="project_manager_assign"
                                    value={selectedManagerId}
                                    onChange={(e) =>
                                        setSelectedManagerId(
                                            e.target.value === '' ? '' : Number(e.target.value)
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs text-foreground shadow-2xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">Select a Manager...</option>
                                    {managers.map((manager) => (
                                        <option key={manager.id} value={manager.id}>
                                            {manager.name} ({manager.email})
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <div className="rounded-lg border border-destructive/25 bg-destructive/10 p-3 text-xs text-destructive">
                                    <p className="font-medium">
                                        No active managers configured. Please create a user with "Manager" role first.
                                    </p>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold text-foreground">
                                Project Manager
                            </Label>
                            <div className="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-xs font-medium text-foreground">
                                {assignTarget?.manager?.name ?? auth.user.name}
                            </div>
                            <p className="text-[11px] text-muted-foreground">
                                You manage this project. Only an administrator can reassign the project manager.
                            </p>
                        </div>
                    )}

                    {/* Supervisors Checklist - Required for Managers, Optional for Admin */}
                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-foreground flex items-center gap-1">
                            Field Technicians{' '}
                            {isManager && <span className="text-destructive">*</span>}
                        </Label>
                        <p className="text-[11px] text-muted-foreground">
                            Star one technician as the primary field technician for this site.
                        </p>
                        {supervisors.length > 0 ? (
                            <div className="max-h-[160px] space-y-1 overflow-y-auto rounded-lg border border-border/80 bg-background p-2">
                                {supervisors.map((sup) => {
                                    const isChecked = selectedSupervisorIds.includes(sup.id);
                                    return (
                                        <div
                                            key={sup.id}
                                            className="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-xs text-foreground transition-colors hover:bg-muted/50"
                                        >
                                            <label className="flex flex-1 min-w-0 items-center gap-2.5 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={isChecked}
                                                    onChange={(e) => {
                                                        const newIds = e.target.checked
                                                            ? [...selectedSupervisorIds, sup.id]
                                                            : selectedSupervisorIds.filter(
                                                                  (id) => id !== sup.id
                                                              );
                                                        setSelectedSupervisorIds(newIds);

                                                        // An unassigned technician cannot stay
                                                        // the project's primary technician.
                                                        if (
                                                            !e.target.checked &&
                                                            selectedPrimarySupervisorId === sup.id
                                                        ) {
                                                            setSelectedPrimarySupervisorId(null);
                                                        }
                                                    }}
                                                    className="size-4 rounded border-input text-primary focus:ring-primary"
                                                />
                                                <span className="truncate font-medium">
                                                    {sup.name}{' '}
                                                    <span className="text-muted-foreground font-normal">
                                                        ({sup.email})
                                                    </span>
                                                </span>
                                            </label>
                                            {/* Primary technician — only assigned technicians
                                                can hold it. */}
                                            {isChecked ? (
                                                <PrimaryTechnicianStar
                                                    name={sup.name}
                                                    isPrimary={
                                                        selectedPrimarySupervisorId === sup.id
                                                    }
                                                    canEdit
                                                    onClick={() =>
                                                        setSelectedPrimarySupervisorId(
                                                            selectedPrimarySupervisorId === sup.id
                                                                ? null
                                                                : sup.id
                                                        )
                                                    }
                                                />
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground">
                                <p className="font-medium">No active field technicians configured.</p>
                            </div>
                        )}
                    </div>
                </div>
            </ConfirmDialog>
        </AppLayout>
    );
}

export default ProjectsIndex;
