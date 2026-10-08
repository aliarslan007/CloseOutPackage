import { InertiaLinkProps, Link } from '@inertiajs/react';

export interface ResponsiveNavLinkProps extends InertiaLinkProps {
    active?: boolean;
}

export function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: ResponsiveNavLinkProps) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-primary bg-accent text-foreground'
                    : 'border-transparent text-muted-foreground hover:border-border hover:bg-accent hover:text-foreground'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}

export default ResponsiveNavLink;
