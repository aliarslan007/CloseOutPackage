import * as React from 'react';
import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export interface SearchInputProps
    extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
    value: string;
    onSearchChange: (value: string) => void;
    debounceMs?: number;
    placeholder?: string;
}

export function SearchInput({
    value,
    onSearchChange,
    debounceMs = 300,
    placeholder = 'Search...',
    className,
    ...props
}: SearchInputProps) {
    const [localValue, setLocalValue] = React.useState(value);

    React.useEffect(() => {
        setLocalValue(value);
    }, [value]);

    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (localValue !== value) {
                onSearchChange(localValue);
            }
        }, debounceMs);

        return () => clearTimeout(timer);
    }, [localValue, debounceMs, onSearchChange, value]);

    const handleClear = () => {
        setLocalValue('');
        onSearchChange('');
    };

    return (
        <div className={cn('relative flex items-center w-full', className)}>
            <Search className="absolute left-3 size-4 text-muted-foreground pointer-events-none" />
            <Input
                value={localValue}
                onChange={(e) => setLocalValue(e.target.value)}
                placeholder={placeholder}
                className="pl-9 pr-9"
                {...props}
            />
            {localValue && (
                <button
                    type="button"
                    onClick={handleClear}
                    className="absolute right-3 text-muted-foreground hover:text-foreground focus:outline-none"
                    aria-label="Clear search"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

export default SearchInput;
