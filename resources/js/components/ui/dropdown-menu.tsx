import { cn } from '@/lib/utils';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import * as React from 'react';

export interface DropdownMenuProps {
    children: React.ReactNode;
}

export function DropdownMenu({ children }: Readonly<DropdownMenuProps>) {
    return (
        <Menu as="div" className="relative inline-block text-left">
            {children}
        </Menu>
    );
}

export interface DropdownMenuTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
    className?: string;
    children: React.ReactNode;
}

export function DropdownMenuTrigger({
    asChild = false,
    children,
    className,
    ...props
}: Readonly<DropdownMenuTriggerProps>) {
    if (asChild && React.isValidElement(children)) {
        return <MenuButton as={React.Fragment}>{children}</MenuButton>;
    }

    return (
        <MenuButton className={cn('inline-flex items-center justify-center', className)} {...props}>
            {children}
        </MenuButton>
    );
}

export interface DropdownMenuContentProps extends React.HTMLAttributes<HTMLDivElement> {
    align?: 'start' | 'end' | 'center';
    className?: string;
    children: React.ReactNode;
}

export function DropdownMenuContent({
    align = 'end',
    className,
    children,
}: Readonly<DropdownMenuContentProps>) {
    const anchorPosition = align === 'start' ? 'bottom start' : 'bottom end';

    return (
        <MenuItems
            transition
            anchor={{ to: anchorPosition, gap: 6 }}
            className={cn(
                'z-50 min-w-[9rem] overflow-hidden rounded-xl border border-border/80 bg-card p-1 shadow-xl ring-1 ring-black/5 focus:outline-none transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0',
                className
            )}
        >
            {children}
        </MenuItems>
    );
}

export interface DropdownMenuItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
    className?: string;
    children: React.ReactNode;
}

export function DropdownMenuItem({
    asChild = false,
    className,
    children,
    onClick,
    ...props
}: Readonly<DropdownMenuItemProps>) {
    return (
        <MenuItem>
            {({ focus }) => {
                if (asChild && React.isValidElement(children)) {
                    return React.cloneElement(
                        children as React.ReactElement<{ className?: string }>,
                        {
                            className: cn(
                                'relative flex cursor-pointer select-none items-center rounded-lg px-2.5 py-1.5 text-xs font-medium outline-none transition-colors w-full',
                                focus
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                                children.props.className,
                                className
                            ),
                        }
                    );
                }

                return (
                    <button
                        type="button"
                        onClick={onClick}
                        className={cn(
                            'relative flex cursor-pointer select-none items-center rounded-lg px-2.5 py-1.5 text-xs font-medium outline-none transition-colors w-full text-left',
                            focus
                                ? 'bg-muted text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                            className
                        )}
                        {...props}
                    >
                        {children}
                    </button>
                );
            }}
        </MenuItem>
    );
}

export default DropdownMenu;
