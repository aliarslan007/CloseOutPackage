import { ProjectFilterOption } from '@/components/shared/project-filter-dropdown';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { ArrowRight, Building2, Check, MapPin, Search } from 'lucide-react';
import * as React from 'react';

export interface SelectProjectDialogProps {
    isOpen: boolean;
    onClose: () => void;
    projects: ProjectFilterOption[];
    initialProjectId?: number | 'all';
}

function formatLocation(p: ProjectFilterOption): string {
    const parts = [p.city, p.state].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : p.address ?? '';
}

export function SelectProjectDialog({
    isOpen,
    onClose,
    projects = [],
    initialProjectId,
}: Readonly<SelectProjectDialogProps>) {
    const [search, setSearch] = React.useState('');
    const [selectedId, setSelectedId] = React.useState<number | null>(null);

    React.useEffect(() => {
        if (isOpen) {
            setSearch('');
            if (initialProjectId && initialProjectId !== 'all') {
                setSelectedId(initialProjectId);
            } else if (projects.length === 1 && projects[0]) {
                setSelectedId(projects[0].id);
            } else {
                setSelectedId(null);
            }
        }
    }, [isOpen, initialProjectId, projects]);

    const filteredProjects = React.useMemo(() => {
        if (!search.trim()) return projects;
        const q = search.toLowerCase().trim();
        return projects.filter((p) => {
            const nameMatch = p.name.toLowerCase().includes(q);
            const numMatch = (p.project_number ?? '').toLowerCase().includes(q);
            const psMatch = (p.ps_number ?? '').toLowerCase().includes(q);
            const addrMatch = (p.address ?? '').toLowerCase().includes(q);
            const cityMatch = (p.city ?? '').toLowerCase().includes(q);
            return nameMatch || numMatch || psMatch || addrMatch || cityMatch;
        });
    }, [projects, search]);

    const selectedProject = React.useMemo(
        () => projects.find((p) => p.id === selectedId) ?? null,
        [projects, selectedId]
    );

    const handleProceed = React.useCallback(
        (project: ProjectFilterOption | null) => {
            if (!project) return;
            const target = project.hash_id || project.id;
            onClose();
            router.visit(route('projects.change-orders.create', target));
        },
        [onClose]
    );

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md max-h-[85vh] flex flex-col gap-4 p-6 overflow-hidden">
                <DialogHeader className="text-left space-y-1">
                    <div className="flex items-center gap-2 text-primary">
                        <div className="size-8 rounded-lg bg-primary/10 flex items-center justify-center">
                            <Building2 className="size-4" />
                        </div>
                        <DialogTitle className="text-base font-bold">
                            Select Project for Change Order
                        </DialogTitle>
                    </div>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Choose the project you want to create a change order for. You will be taken directly to the creation page.
                    </DialogDescription>
                </DialogHeader>

                {/* Search Input */}
                <div className="relative flex items-center">
                    <Search className="absolute left-3 size-3.5 text-muted-foreground pointer-events-none" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by project name, number, or location..."
                        className="w-full h-9 pl-9 pr-3 text-xs rounded-lg border border-input bg-muted/40 placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                        autoFocus
                    />
                </div>

                {/* Single-select Project List */}
                <div className="flex-1 overflow-y-auto max-h-64 divide-y divide-border/40 rounded-lg border border-border/80 p-1 space-y-0.5">
                    {filteredProjects.length === 0 ? (
                        <div className="py-8 text-center text-xs text-muted-foreground">
                            No projects match &ldquo;{search}&rdquo;
                        </div>
                    ) : (
                        filteredProjects.map((p) => {
                            const isSelected = selectedId === p.id;
                            const projectNum = p.project_number || p.ps_number;
                            const locationStr = formatLocation(p);

                            return (
                                <div
                                    key={p.id}
                                    role="radio"
                                    aria-checked={isSelected}
                                    tabIndex={0}
                                    onClick={() => setSelectedId(p.id)}
                                    onDoubleClick={() => handleProceed(p)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' || e.key === ' ') {
                                            e.preventDefault();
                                            setSelectedId(p.id);
                                        }
                                    }}
                                    className={cn(
                                        'flex items-center justify-between gap-3 p-2.5 rounded-md cursor-pointer transition-all text-left outline-none',
                                        isSelected
                                            ? 'bg-primary/10 border-primary/40 text-primary'
                                            : 'hover:bg-muted/50 text-foreground'
                                    )}
                                >
                                    <div className="flex flex-col min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'text-xs truncate font-semibold',
                                                    isSelected && 'text-primary font-bold'
                                                )}
                                                title={p.name}
                                            >
                                                {p.name}
                                            </span>
                                            {projectNum && (
                                                <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-muted text-muted-foreground shrink-0">
                                                    {projectNum}
                                                </span>
                                            )}
                                        </div>
                                        {locationStr && (
                                            <span className="flex items-center gap-1 text-[11px] text-muted-foreground mt-0.5 truncate">
                                                <MapPin className="size-3 shrink-0" />
                                                <span className="truncate">{locationStr}</span>
                                            </span>
                                        )}
                                    </div>

                                    <div
                                        className={cn(
                                            'size-5 rounded-full border flex items-center justify-center shrink-0 transition-colors',
                                            isSelected
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-muted-foreground/40 bg-card'
                                        )}
                                    >
                                        {isSelected && <Check className="size-3 stroke-[2.5]" />}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                <DialogFooter className="flex items-center justify-between sm:justify-between gap-2 border-t pt-3">
                    <div className="text-[11px] text-muted-foreground truncate max-w-[200px]">
                        {selectedProject ? (
                            <span>Selected: <strong>{selectedProject.name}</strong></span>
                        ) : (
                            <span>Please select 1 project</span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClose}
                            className="text-xs h-8"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={!selectedProject}
                            onClick={() => handleProceed(selectedProject)}
                            className="text-xs h-8 gap-1.5"
                        >
                            <span>Continue</span>
                            <ArrowRight className="size-3.5" />
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
