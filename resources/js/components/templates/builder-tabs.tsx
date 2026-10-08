import * as React from 'react';
import { cn } from '@/lib/utils';

export type BuilderTabKey =
    | 'pre_construction'
    | 'construction'
    | 'post_completion'
    | 'checklist';

export interface BuilderTab {
    key: BuilderTabKey;
    label: string;
    count?: number;
    /** True when this section has unsaved heading/notes edits. */
    dirty?: boolean;
}

export interface BuilderTabsProps {
    tabs: BuilderTab[];
    active: BuilderTabKey;
    onChange: (tab: BuilderTabKey) => void;
}

export function BuilderTabs({ tabs, active, onChange }: Readonly<BuilderTabsProps>) {
    return (
        <div
            role="tablist"
            aria-label="Template builder sections"
            className="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1"
        >
            {tabs.map((tab) => {
                const isActive = tab.key === active;
                return (
                    <button
                        key={tab.key}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onChange(tab.key)}
                        className={cn(
                            'flex items-center gap-1.5 whitespace-nowrap rounded-full px-4 py-1.5 text-xs font-bold transition-all shrink-0',
                            isActive
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'bg-card text-muted-foreground hover:text-foreground border border-border'
                        )}
                        title={tab.dirty ? 'Unsaved changes in this section' : undefined}
                        aria-label={tab.dirty ? `${tab.label} (unsaved changes)` : undefined}
                    >
                        {tab.dirty && (
                            <span
                                className="size-1.5 rounded-full bg-amber-500 animate-pulse shrink-0"
                                aria-hidden="true"
                            />
                        )}
                        {tab.label}
                        {typeof tab.count === 'number' && (
                            <span
                                className={cn(
                                    'rounded-full px-1.5 py-0.5 text-[10px] leading-none',
                                    isActive
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'
                                )}
                            >
                                {tab.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
