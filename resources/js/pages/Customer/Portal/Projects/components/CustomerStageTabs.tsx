import { cn } from '@/lib/utils';
import React from 'react';

export type CustomerTabKey = 'pre_construction' | 'construction' | 'post_completion' | 'checklist';

export interface StageTabItem {
    id: CustomerTabKey;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    count: number;
}

interface CustomerStageTabsProps {
    tabs: StageTabItem[];
    activeTab: CustomerTabKey;
    onSelectTab: (tabId: CustomerTabKey) => void;
}

/**
 * Flat segmented bar: a single bottom rule, no rounded outer box.
 *
 * The previous `rounded-2xl border p-1.5` wrapper around `rounded-xl` pills had
 * a broken radius nesting (18px outer vs 14px inner with only 6px of padding), so
 * the container's left/right corner arcs surfaced through the pill edges and read
 * as stray floating brackets once a tab was active. No outer box, no nested
 * radii, nothing for the arcs to fight with.
 */
export function CustomerStageTabs({
    tabs,
    activeTab,
    onSelectTab,
}: Readonly<CustomerStageTabsProps>) {
    return (
        <nav
            className="flex gap-1 overflow-x-auto no-scrollbar border-b border-border"
            aria-label="Deliverable Stages"
        >
            {tabs.map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.id;

                return (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => onSelectTab(tab.id)}
                        aria-current={isActive ? 'true' : undefined}
                        className={cn(
                            'flex shrink-0 items-center gap-2 rounded-md px-3.5 py-2.5 text-xs font-semibold whitespace-nowrap cursor-pointer transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            isActive
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                        )}
                    >
                        <Icon className={cn('size-3.5', !isActive && 'opacity-70')} />
                        <span>{tab.label}</span>
                        <span
                            className={cn(
                                'min-w-5 rounded-sm px-1 py-0.5 text-center text-[10px] font-bold tabular-nums',
                                isActive
                                    ? 'bg-primary-foreground/20 text-primary-foreground'
                                    : 'bg-muted text-muted-foreground'
                            )}
                        >
                            {tab.count}
                        </span>
                    </button>
                );
            })}
        </nav>
    );
}

export default CustomerStageTabs;