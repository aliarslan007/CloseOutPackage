import { forwardRef, InputHTMLAttributes, useEffect, useImperativeHandle, useRef } from 'react';

export interface TextInputProps extends InputHTMLAttributes<HTMLInputElement> {
    isFocused?: boolean;
    className?: string;
}

export interface TextInputRef {
    focus: () => void;
}

export const TextInput = forwardRef<TextInputRef, TextInputProps>(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'rounded-md border border-input bg-card text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:ring-ring focus:outline-none ' +
                className
            }
            ref={localRef}
        />
    );
});

export default TextInput;
