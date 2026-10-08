import { InertiaLinkProps, Link } from '@inertiajs/react';

export interface NavLinkProps extends InertiaLinkProps {
    active?: boolean;
}

export function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: NavLinkProps) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-primary text-foreground focus:border-ring'
                    : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground') +
                ' ' + className
            }
        >
            {children}
        </Link>
    );
}

export default NavLink;
