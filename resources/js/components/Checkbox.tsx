import { InputHTMLAttributes } from 'react';

export interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
    className?: string;
}

export function Checkbox({ className = '', ...props }: Readonly<CheckboxProps>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={'rounded border-input text-primary shadow-sm focus:ring-ring ' + className}
        />
    );
}

export default Checkbox;
