import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Check, ChevronDown, Images, Search } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { useAccessibleProjects } from '../hooks/use-accessible-projects';
import type { MediaLibraryProject } from '../types/media-library.types';

export type PickerScope = 'project' | 'all' | 'generic';

const ALL_LABEL = 'All Photos';
const GENERIC_LABEL = 'Generic Photos';

export interface MediaPickerScopeBarProps {
    scope: PickerScope;
    /** `project` also carries the project to narrow to; `all` clears the narrowing. */
    onScopeChange: (scope: PickerScope, projectId?: number) => void;
    /** Project the `project` scope is narrowed to. */
    scopeProjectId: number | null;
    search: string;
    onSearchChange: (search: string) => void;
    /** Context project: the default option, always offered even if the list is empty. */
    projectId?: number;
    projectName?: string;
    /** Extra filter control rendered between the project picker and the search. */
    extraFilters?: ReactNode;
}

/**
 * Long project names are the norm, so the name wraps to a second line inside a
 * fixed-width control instead of stretching the filter bar. A native `select`
 * cannot wrap its own label, hence the popover — the same pattern the uploader
 * filter beside it uses.
 */
const PROJECT_NAME_CLASS = 'min-w-0 flex-1 text-left break-words [overflow-wrap:anywhere]';

function projectOptionClass(isSelected: boolean): string {
    return cn(
        'flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs transition-colors text-muted-foreground hover:bg-muted hover:text-foreground',
        isSelected && 'bg-muted/50 font-semibold text-foreground'
    );
}

export function MediaPickerScopeBar({
    scope,
    onScopeChange,
    scopeProjectId,
    search,
    onSearchChange,
    projectId,
    projectName,
    extraFilters,
}: Readonly<MediaPickerScopeBarProps>) {
    const { projects } = useAccessibleProjects();
    // Narrows the panel's project list only — the filename search has its own box.
    const [projectSearch, setProjectSearch] = useState('');

    // The context project stays selectable before the (cached) fetch resolves:
    // it is the project the change order or package is being built for.
    const options = useMemo<MediaLibraryProject[]>(() => {
        if (projectId === undefined || projects.some((project) => project.id === projectId)) {
            return projects;
        }

        return [{ id: projectId, name: projectName ?? 'This Project' }, ...projects];
    }, [projects, projectId, projectName]);

    const query = projectSearch.trim().toLowerCase();
    // Managers can reach into the hundreds of projects; searching beats scrolling.
    const matchingProjects = useMemo(
        () => (query ? options.filter((option) => option.name.toLowerCase().includes(query)) : options),
        [options, query]
    );

    const isGeneric = scope === 'generic';
    const selectedId = scope === 'project' ? (scopeProjectId ?? projectId ?? null) : null;
    const selectedLabel = isGeneric
        ? GENERIC_LABEL
        : (options.find((option) => option.id === selectedId)?.name ?? ALL_LABEL);

    return (
        <div className="p-3 border-b border-border bg-muted/20 flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center justify-between gap-2.5">
            <Popover className="relative w-full min-w-0 shrink-0 sm:w-[210px]">
                {({ close }) => (
                    <>
                        <PopoverButton
                            className="flex min-h-11 w-full items-center justify-between gap-2 rounded-lg border border-border bg-card px-2.5 py-2 text-left text-sm text-foreground shadow-2xs hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer select-none sm:min-h-9 sm:py-1.5 sm:text-xs"
                            aria-label="Filter photos by project"
                            title={selectedLabel}
                        >
                            <span className={cn(PROJECT_NAME_CLASS, 'line-clamp-3')}>
                                {selectedLabel}
                            </span>
                            <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
                        </PopoverButton>

                        <PopoverPanel
                            transition
                            anchor={{ to: 'bottom start', gap: 6 }}
                            /* Panel matches the trigger width (`--button-width` is
                               set by the anchor): the dropdown can no longer grow
                               wider than the filter it belongs to. */
                            className="z-50 w-[var(--button-width)] rounded-xl border border-border/80 bg-card p-1.5 shadow-xl ring-1 ring-black/5 focus:outline-none transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
                        >
                            <div className="relative mb-1">
                                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={projectSearch}
                                    onChange={(e) => setProjectSearch(e.target.value)}
                                    placeholder="Search projects..."
                                    aria-label="Search projects by name"
                                    className="h-8 pl-8 text-xs"
                                />
                            </div>

                            <div className="max-h-56 overflow-y-auto">
                                {/* All Photos first: the wider net, and the default scope. */}
                                <button
                                    type="button"
                                    onClick={() => {
                                        onScopeChange('all');
                                        setProjectSearch('');
                                        close();
                                    }}
                                    className={projectOptionClass(selectedId === null && !isGeneric)}
                                >
                                    <span className={PROJECT_NAME_CLASS}>{ALL_LABEL}</span>
                                    {selectedId === null && !isGeneric && (
                                        <Check className="size-3.5 shrink-0 text-primary" />
                                    )}
                                </button>

                                {/* Generic photos: uploads that belong to no project. */}
                                <button
                                    type="button"
                                    onClick={() => {
                                        onScopeChange('generic');
                                        setProjectSearch('');
                                        close();
                                    }}
                                    className={projectOptionClass(isGeneric)}
                                >
                                    <span className={cn(PROJECT_NAME_CLASS, 'flex items-center gap-1.5')}>
                                        <Images className="size-3.5 shrink-0 text-muted-foreground" />
                                        {GENERIC_LABEL}
                                    </span>
                                    {isGeneric && <Check className="size-3.5 shrink-0 text-primary" />}
                                </button>

                                {matchingProjects.length === 0 ? (
                                    <p className="px-2.5 py-3 text-center text-[11px] text-muted-foreground">
                                        No projects match &ldquo;{projectSearch.trim()}&rdquo;
                                    </p>
                                ) : (
                                    matchingProjects.map((option) => {
                                        const isSelected = selectedId === option.id;

                                        return (
                                            <button
                                                key={option.id}
                                                type="button"
                                                onClick={() => {
                                                    onScopeChange('project', option.id);
                                                    setProjectSearch('');
                                                    close();
                                                }}
                                                className={projectOptionClass(isSelected)}
                                                title={option.name}
                                            >
                                                <span className={PROJECT_NAME_CLASS}>{option.name}</span>
                                                {isSelected && (
                                                    <Check className="size-3.5 shrink-0 text-primary" />
                                                )}
                                            </button>
                                        );
                                    })
                                )}
                            </div>
                        </PopoverPanel>
                    </>
                )}
            </Popover>

            {extraFilters}

            {/* Grows to fill the free space, capped so it never dominates a wide drawer. */}
            <div className="relative w-full sm:w-auto sm:min-w-[8rem] sm:max-w-[22rem] sm:flex-1">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 sm:size-3.5 text-muted-foreground" />
                <Input
                    type="search"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder="Filter by filename..."
                    className="h-11 sm:h-8 pl-8 text-sm sm:text-xs w-full"
                />
            </div>
        </div>
    );
}
