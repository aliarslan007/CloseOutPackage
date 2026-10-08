import { User, PageProps } from '@/types/inertia';
import { usePage } from '@inertiajs/react';

export const ROLES = {
    ADMIN: 'admin',
    MANAGER: 'manager',
    FIELD_SUPERVISOR: 'field_supervisor',
    CUSTOMER: 'customer',
} as const;

export function hasRole(user?: User | null, ...roles: string[]): boolean {
    if (!user?.roles) return false;
    return user.roles.some((r) => roles.includes(r));
}

export function isAdmin(user?: User | null): boolean {
    return hasRole(user, ROLES.ADMIN);
}

export function isManager(user?: User | null): boolean {
    return hasRole(user, ROLES.MANAGER);
}

export function isFieldSupervisor(user?: User | null): boolean {
    return hasRole(user, ROLES.FIELD_SUPERVISOR);
}

export function isCustomer(user?: User | null): boolean {
    return hasRole(user, ROLES.CUSTOMER);
}

export function canManageChangeOrders(user?: User | null): boolean {
    return hasRole(user, ROLES.ADMIN, ROLES.MANAGER);
}

export function canCreateChangeOrder(user?: User | null): boolean {
    return hasRole(user, ROLES.ADMIN, ROLES.MANAGER);
}

export function canViewChangeOrders(user?: User | null): boolean {
    return hasRole(user, ROLES.ADMIN, ROLES.MANAGER, ROLES.CUSTOMER);
}

/**
 * A customer is their own scope: they have no peer contacts to narrow the list
 * by, and offering one would surface another account's contact details. Add
 * role-specific filter visibility here rather than branching at each call site.
 */
export function canFilterChangeOrdersByCustomer(user?: User | null): boolean {
    return !isCustomer(user);
}

export function canEditProject(user?: User | null, projectManagerId?: number | null): boolean {
    if (!user) return false;
    if (isAdmin(user)) return true;
    if (isManager(user) && projectManagerId !== undefined && projectManagerId !== null) {
        return user.id === projectManagerId;
    }
    return isManager(user);
}

export function canDeleteProject(user?: User | null, projectManagerId?: number | null): boolean {
    if (!user) return false;
    if (isAdmin(user)) return true;
    if (isManager(user) && projectManagerId !== undefined && projectManagerId !== null) {
        return user.id === projectManagerId;
    }
    return false;
}

export function canManageUsers(user?: User | null): boolean {
    return isAdmin(user);
}

export function canManageTemplates(user?: User | null): boolean {
    return isAdmin(user);
}

export function canManageMarkets(user?: User | null): boolean {
    return hasRole(user, ROLES.ADMIN);
}

/**
 * Mirrors MediaAssetPolicy::delete — technicians only their own uploads;
 * managers/admins pass canAccess on the backend.
 */
export function canDeleteMediaAsset(
    user: User | null | undefined,
    asset: { user_id: number } | null | undefined
): boolean {
    if (!user || !asset) return false;
    if (isAdmin(user) || isManager(user)) return true;
    if (isFieldSupervisor(user)) return user.id === asset.user_id;
    return false;
}

export function canTrackFieldTechs(user?: User | null, projectManagerId?: number | null): boolean {
    return canEditProject(user, projectManagerId);
}

/**
 * Determines whether a user has operational permission to submit stages,
 * save drafts, or submit closeout packages for review.
 * Only Field Supervisors and Managers who oversee project execution can execute workflow transitions.
 * Admins retain administrative oversight without performing stage submissions.
 */
export function canSubmitPackageWorkflow(user?: User | null): boolean {
    return hasRole(user, ROLES.FIELD_SUPERVISOR, ROLES.MANAGER);
}

export function usePermissions() {
    const { auth } = usePage<PageProps>().props;
    const user = auth?.user;

    return {
        user,
        isAdmin: isAdmin(user),
        isManager: isManager(user),
        isFieldSupervisor: isFieldSupervisor(user),
        isCustomer: isCustomer(user),
        canManageChangeOrders: canManageChangeOrders(user),
        canCreateChangeOrder: canCreateChangeOrder(user),
        canViewChangeOrders: canViewChangeOrders(user),
        canFilterChangeOrdersByCustomer: canFilterChangeOrdersByCustomer(user),
        canEditProject: (projectManagerId?: number | null) => canEditProject(user, projectManagerId),
        canDeleteProject: (projectManagerId?: number | null) => canDeleteProject(user, projectManagerId),
        canTrackFieldTechs: (projectManagerId?: number | null) => canTrackFieldTechs(user, projectManagerId),
        canManageUsers: canManageUsers(user),
        canManageTemplates: canManageTemplates(user),
        canManageMarkets: canManageMarkets(user),
        canSubmitPackageWorkflow: canSubmitPackageWorkflow(user),
    };
}
