import { ButtonHTMLAttributes } from 'react';

export interface PrimaryButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    className?: string;
    disabled?: boolean;
}

export function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: PrimaryButtonProps) {
    return (
        <button
            {...props}
            disabled={disabled}
            className={
                `inline-flex items-center rounded-full border border-transparent bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-primary-foreground transition duration-150 ease-in-out hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 active:bg-primary ${
                    disabled ? 'opacity-25' : ''
                } ` + className
            }
        >
            {children}
        </button>
    );
}

export default PrimaryButton;
