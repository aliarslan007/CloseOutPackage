import { ButtonHTMLAttributes } from 'react';

export interface SecondaryButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    className?: string;
    disabled?: boolean;
}

export function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: SecondaryButtonProps) {
    return (
        <button
            {...props}
            type={type}
            disabled={disabled}
            className={
                `inline-flex items-center rounded-full border border-input bg-background px-4 py-2 text-xs font-semibold uppercase tracking-widest text-foreground shadow-sm transition duration-150 ease-in-out hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-25 ${
                    disabled ? 'opacity-25' : ''
                } ` + className
            }
        >
            {children}
        </button>
    );
}

export default SecondaryButton;
