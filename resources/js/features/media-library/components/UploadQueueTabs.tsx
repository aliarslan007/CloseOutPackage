import { cn } from '@/lib/utils';

export type QueueTab = 'all' | 'active' | 'done' | 'failed';

export interface UploadQueueTabsProps {
    activeTab: QueueTab;
    onTabChange: (tab: QueueTab) => void;
    totalCount: number;
    inFlightCount: number;
    doneCount: number;
    failedCount: number;
}

const TAB_BASE_CLS = 'rounded-md px-2 py-0.5 font-medium transition-colors text-[11px]';
const TAB_ACTIVE_CLS = 'bg-primary/10 text-primary font-semibold';
const TAB_INACTIVE_CLS = 'text-muted-foreground hover:text-foreground';

export function UploadQueueTabs({
    activeTab,
    onTabChange,
    totalCount,
    inFlightCount,
    doneCount,
    failedCount,
}: Readonly<UploadQueueTabsProps>) {
    const tabs: Array<{ id: QueueTab; label: string; isFailed?: boolean }> = [
        { id: 'all', label: `All (${totalCount})` },
        { id: 'active', label: `In Progress (${inFlightCount})` },
        { id: 'done', label: `Done (${doneCount})` },
    ];

    if (failedCount > 0) {
        tabs.push({ id: 'failed', label: `Failed (${failedCount})`, isFailed: true });
    }

    return (
        <div className="flex items-center gap-1 border-b border-border/80 bg-muted/20 px-3 py-1.5 text-xs">
            {tabs.map((tab) => {
                const isActive = activeTab === tab.id;
                let stateCls = isActive ? TAB_ACTIVE_CLS : TAB_INACTIVE_CLS;

                if (tab.isFailed) {
                    stateCls = isActive
                        ? 'bg-destructive/10 text-destructive font-semibold'
                        : 'text-destructive/80 hover:text-destructive';
                }

                return (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => onTabChange(tab.id)}
                        className={cn(TAB_BASE_CLS, stateCls)}
                    >
                        {tab.label}
                    </button>
                );
            })}
        </div>
    );
}
