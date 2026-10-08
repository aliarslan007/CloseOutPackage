import { LabelHTMLAttributes } from 'react';

export interface InputLabelProps extends LabelHTMLAttributes<HTMLLabelElement> {
    value?: string;
    className?: string;
}

export function InputLabel({
    value,
    className = '',
    children,
    ...props
}: InputLabelProps) {
    return (
        <label
            {...props}
            className={`block text-sm font-medium text-foreground ` + className}
        >
            {value ? value : children}
        </label>
    );
}

export default InputLabel;
