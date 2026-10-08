import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import * as React from 'react';

export interface SowImageGroupCreateFormProps {
    busy: boolean;
    onCreate: (name: string) => void;
}

/** Small inline form for naming a new photo group. */
export function SowImageGroupCreateForm({ busy, onCreate }: Readonly<SowImageGroupCreateFormProps>) {
    const [name, setName] = React.useState('');
    const trimmed = name.trim();

    return (
        <form
            className="flex flex-wrap items-end gap-2 rounded-lg border border-border bg-muted/30 p-3"
            onSubmit={(e) => {
                e.preventDefault();
                if (trimmed === '') return;
                onCreate(trimmed);
                setName('');
            }}
        >
            <div className="min-w-0 flex-1">
                <label
                    htmlFor="new-sow-group"
                    className="mb-1 block text-xs font-medium text-foreground"
                >
                    Group name
                </label>
                <Input
                    id="new-sow-group"
                    autoFocus
                    value={name}
                    maxLength={120}
                    disabled={busy}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g. Site Plan, Fiber Pathway"
                    className="h-8 text-xs"
                />
            </div>
            <Button type="submit" size="sm" className="h-8 text-xs" disabled={busy || trimmed === ''}>
                Create group
            </Button>
        </form>
    );
}
