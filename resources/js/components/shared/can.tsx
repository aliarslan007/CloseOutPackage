import { PropsWithChildren, ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types/inertia';

export interface CanProps {
    permission?: string;
    role?: string;
    fallback?: ReactNode;
    children: ReactNode;
}

export function Can({ permission, role, fallback = null, children }: PropsWithChildren<CanProps>) {
    const { auth } = usePage<PageProps>().props;

    const hasRole = role ? auth.roles?.includes(role) : false;
    const hasPermission = permission ? auth.permissions?.includes(permission) : false;

    // Admin role bypasses all permission checks
    const isAdmin = auth.roles?.includes('admin');

    const isAuthorized = isAdmin || hasRole || hasPermission;

    if (!isAuthorized) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}

export default Can;
