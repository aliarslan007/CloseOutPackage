import { cn } from '@/lib/utils';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Building2, Check, ChevronDown, MapPin, Search, X } from 'lucide-react';
import * as React from 'react';

export interface ProjectFilterOption {
    id: number;
    hash_id?: string;
    name: string;
    customer_id?: number | null;
    project_number?: string | null;
    ps_number?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
}

export interface ProjectFilterDropdownProps {
    projects: ProjectFilterOption[];
    selectedProjectId: number | 'all';
    onSelectProject: (projectId: number | 'all') => void;
    className?: string;
    /**
     * Show the "All Projects" row and the clear button. Pages that are always
     * scoped to a single project (live field tracking) pass false so the user
     * can never land back on a mixed, unscoped view.
     */
    allowAll?: boolean;
}

function formatProjectAddress(p: ProjectFilterOption): string {
    const parts: string[] = [];
    if (p.address) {
        parts.push(p.address);
    }
    const cityStateZip = [p.city, p.state, p.zip].filter(Boolean).join(', ');
    if (cityStateZip) {
        parts.push(cityStateZip);
    }
    return parts.join(' • ');
}

export function ProjectFilterDropdown({
    projects = [],
    selectedProjectId,
    onSelectProject,
    className,
    allowAll = true,
}: Readonly<ProjectFilterDropdownProps>) {
    const [search, setSearch] = React.useState('');

    const selectedProject = React.useMemo(() => {
        if (selectedProjectId === 'all') return null;
        return projects.find((p) => p.id === selectedProjectId) ?? null;
    }, [projects, selectedProjectId]);

    const filteredProjects = React.useMemo(() => {
        if (!search.trim()) return projects;
        const q = search.toLowerCase().trim();
        return projects.filter((p) => {
            const nameMatch = p.name.toLowerCase().includes(q);
            const numMatch = (p.project_number ?? '').toLowerCase().includes(q);
            const psMatch = (p.ps_number ?? '').toLowerCase().includes(q);
            const addrMatch = (p.address ?? '').toLowerCase().includes(q);
            const cityMatch = (p.city ?? '').toLowerCase().includes(q);
            const stateMatch = (p.state ?? '').toLowerCase().includes(q);
            return nameMatch || numMatch || psMatch || addrMatch || cityMatch || stateMatch;
        });
    }, [projects, search]);

    const hasSelectedProject = selectedProjectId !== 'all';

    return (
        <Popover className="relative inline-block text-left shrink-0">
            {({ close }) => (
                <>
                    <PopoverButton
                        className={cn(
                            'h-9 rounded-md border border-input bg-card px-3 text-xs flex items-center justify-between gap-2 shadow-2xs hover:bg-muted/50 transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer max-w-[200px]',
                            hasSelectedProject &&
                                'border-primary/50 bg-primary/5 font-semibold text-primary',
                            className
                        )}
                        aria-label="Filter by project"
                    >
                        <div className="flex items-center gap-2 min-w-0 flex-1">
                            <Building2
                                className={cn(
                                    'size-3.5 shrink-0',
                                    hasSelectedProject
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                )}
                            />
                            <span className="truncate text-left">
                                {selectedProject ? selectedProject.name : 'All Projects'}
                            </span>
                        </div>

                        <div className="flex items-center gap-1 shrink-0">
                            {!hasSelectedProject && (
                                <span className="text-[10px] text-muted-foreground/80 font-normal">
                                    ({projects.length})
                                </span>
                            )}
                            {hasSelectedProject && allowAll && (
                                <span
                                    role="button"
                                    tabIndex={0}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSelectProject('all');
                                        setSearch('');
                                    }}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.stopPropagation();
                                            onSelectProject('all');
                                            setSearch('');
                                        }
                                    }}
                                    aria-label="Clear project filter"
                                    className="rounded p-0.5 hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer"
                                >
                                    <X className="size-3" />
                                </span>
                            )}
                            <ChevronDown className="size-3.5 text-muted-foreground" />
                        </div>
                    </PopoverButton>

                    <PopoverPanel
                        anchor={{ to: 'bottom start', gap: 6 }}
                        className="z-50 w-80 sm:w-96 rounded-xl border border-border/80 bg-card p-2 text-xs shadow-xl ring-1 ring-black/5 focus:outline-none max-h-[380px] flex flex-col gap-1.5"
                    >
                        {/* Search Input */}
                        <div className="relative flex items-center px-1">
                            <Search className="absolute left-3 size-3.5 text-muted-foreground pointer-events-none" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name, number, or location..."
                                className="w-full h-8 pl-8 pr-2 text-xs rounded-md border border-input bg-muted/40 placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            />
                        </div>

                        {/* Projects List */}
                        <div className="overflow-y-auto max-h-64 divide-y divide-border/30 pr-0.5 space-y-0.5">
                            {/* Option: All Projects */}
                            {allowAll && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onSelectProject('all');
                                        setSearch('');
                                        close();
                                    }}
                                    className={cn(
                                        'w-full text-left p-2 rounded-lg flex items-center justify-between gap-2 hover:bg-muted/60 transition-colors cursor-pointer',
                                        selectedProjectId === 'all' &&
                                            'bg-primary/10 text-primary font-semibold'
                                    )}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <Building2 className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="text-xs">All Projects</span>
                                    </div>
                                    <div className="flex items-center gap-1.5 shrink-0">
                                        <span className="text-[10px] text-muted-foreground">
                                            {projects.length}{' '}
                                            {projects.length === 1 ? 'project' : 'projects'}
                                        </span>
                                        {selectedProjectId === 'all' && (
                                            <Check className="size-3.5 text-primary" />
                                        )}
                                    </div>
                                </button>
                            )}
                            {/* Filtered Project Items */}
                            {filteredProjects.length === 0 ? (
                                <div className="p-4 text-center text-muted-foreground text-xs">
                                    No projects match &ldquo;{search}&rdquo;
                                </div>
                            ) : (
                                filteredProjects.map((p) => {
                                    const isSelected = selectedProjectId === p.id;
                                    const projectNum = p.project_number || p.ps_number;
                                    const addressStr = formatProjectAddress(p);

                                    return (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => {
                                                onSelectProject(p.id);
                                                setSearch('');
                                                close();
                                            }}
                                            className={cn(
                                                'w-full text-left p-2.5 rounded-lg flex flex-col gap-1 hover:bg-muted/60 transition-colors cursor-pointer',
                                                isSelected && 'bg-primary/10 text-primary'
                                            )}
                                        >
                                            {/* Line 1: Name & Project Number */}
                                            <div className="flex items-start justify-between gap-2 min-w-0">
                                                <span
                                                    className={cn(
                                                        'text-xs whitespace-normal break-words flex-1 font-semibold text-foreground',
                                                        isSelected && 'text-primary font-bold'
                                                    )}
                                                    title={p.name}
                                                >
                                                    {p.name}
                                                </span>

                                                <div className="flex items-center gap-1.5 shrink-0">
                                                    {projectNum && (
                                                        <span className="text-[10px] font-mono font-medium px-1.5 py-0.5 rounded bg-muted text-muted-foreground shrink-0 max-w-[110px] truncate">
                                                            {projectNum}
                                                        </span>
                                                    )}
                                                    {isSelected && (
                                                        <Check className="size-3.5 text-primary shrink-0" />
                                                    )}
                                                </div>
                                            </div>

                                            {/* Line 2: Address / Location */}
                                            {addressStr ? (
                                                <div
                                                    className="flex items-center gap-1 text-[11px] text-muted-foreground min-w-0"
                                                    title={addressStr}
                                                >
                                                    <MapPin className="size-3 shrink-0 text-muted-foreground/70" />
                                                    <span className="whitespace-normal break-words">{addressStr}</span>
                                                </div>
                                            ) : (
                                                <span className="text-[10px] text-muted-foreground/60 italic">
                                                    No address provided
                                                </span>
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
    );
}
