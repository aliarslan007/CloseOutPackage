import { HTMLAttributes } from 'react';

export interface InputErrorProps extends HTMLAttributes<HTMLParagraphElement> {
    message?: string;
    className?: string;
}

export function InputError({ message, className = '', ...props }: InputErrorProps) {
    return message ? (
        <p {...props} className={'text-sm text-destructive ' + className}>
            {message}
        </p>
    ) : null;
}

export default InputError;
