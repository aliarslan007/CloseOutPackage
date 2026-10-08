import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import axios from 'axios';
import {
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Edit3,
    FileCheck2,
    FolderSearch,
    Plus,
    Radio,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface SiteDetailEntry {
    readonly source: 'auth_center' | 'manual';
    readonly site_type: 'hub' | 'smallcell';
    readonly name: string;
    readonly auth_site_id?: number;
    readonly region?: string;
    readonly state?: string;
    readonly [key: string]: string | number | boolean | null | undefined;
}

export interface FinalApprovalDialogProps {
    readonly open: boolean;
    readonly loading?: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly onConfirm: (entries: SiteDetailEntry[]) => void;
}

interface SiteItem {
    readonly id: number;
    readonly name: string;
}

interface StateOption {
    readonly code: string;
    readonly name: string;
}

const SITES_PER_PAGE = 25;

/* -------------------------------------------------------------------------- */
/* Subcomponent: Catalog Site List Item & Controls                            */
/* -------------------------------------------------------------------------- */
interface CatalogBrowserProps {
    readonly regions: string[];
    readonly states: StateOption[];
    readonly sites: SiteItem[];
    readonly selectedRegion: string;
    readonly selectedState: string;
    readonly searchQuery: string;
    readonly page: number;
    readonly totalPages: number;
    readonly fetching: boolean;
    readonly isSiteAdded: (id: number) => boolean;
    readonly onRegionChange: (val: string) => void;
    readonly onStateChange: (val: string) => void;
    readonly onSearchChange: (val: string) => void;
    readonly onPageChange: (val: number) => void;
    readonly onAddSite: (site: SiteItem) => void;
}

function CatalogBrowser({
    regions,
    states,
    sites,
    selectedRegion,
    selectedState,
    searchQuery,
    page,
    totalPages,
    fetching,
    isSiteAdded,
    onRegionChange,
    onStateChange,
    onSearchChange,
    onPageChange,
    onAddSite,
}: Readonly<CatalogBrowserProps>) {
    const start = (page - 1) * SITES_PER_PAGE;
    const pageSites = sites.slice(start, start + SITES_PER_PAGE);

    return (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4">
            <div className="flex items-center justify-between border-b border-border/60 pb-3">
                <div>
                    <p className="font-bold text-xs text-foreground">
                        Browse Site Access Info Catalog
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        Filter by region and state, or search across all sites in the catalog.
                    </p>
                </div>
                <span className="text-[11px] font-semibold text-muted-foreground bg-muted rounded-full px-2.5 py-0.5">
                    {fetching ? 'Updating…' : `${sites.length.toLocaleString()} sites available`}
                </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label htmlFor="catalog-region" className="text-[11px] text-muted-foreground">
                        Region
                    </Label>
                    <select
                        id="catalog-region"
                        value={selectedRegion}
                        onChange={(e) => onRegionChange(e.target.value)}
                        className="h-9 w-full rounded-lg border border-input bg-card px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary cursor-pointer"
                    >
                        <option value="">All Regions ({regions.length})</option>
                        {regions.map((reg) => (
                            <option key={reg} value={reg}>
                                {reg}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="space-y-1">
                    <Label htmlFor="catalog-state" className="text-[11px] text-muted-foreground">
                        State
                    </Label>
                    <select
                        id="catalog-state"
                        value={selectedState}
                        onChange={(e) => onStateChange(e.target.value)}
                        className="h-9 w-full rounded-lg border border-input bg-card px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary cursor-pointer"
                    >
                        <option value="">All States ({states.length})</option>
                        {states.map((st) => (
                            <option key={st.code} value={st.code}>
                                {st.name} ({st.code})
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={searchQuery}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder="Search site by full name or code (e.g. AKRON, COLUMBUS, CLEVELAND)…"
                    className="pl-9 text-xs h-9"
                />
            </div>

            <div className="rounded-lg border border-border/80 overflow-hidden bg-background">
                {pageSites.length === 0 ? (
                    <div className="py-8 text-center text-muted-foreground">
                        <Building2 className="mx-auto size-7 text-muted-foreground/40 mb-1" />
                        <p className="text-xs font-semibold text-foreground">
                            {fetching ? 'Loading sites from catalog…' : 'No matching sites found'}
                        </p>
                        <p className="text-[11px] mt-0.5">
                            {fetching
                                ? 'Please wait a moment.'
                                : 'Try changing your region/state filter or search query.'}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border/60 max-h-56 overflow-y-auto">
                        {pageSites.map((site) => {
                            const added = isSiteAdded(site.id);
                            return (
                                <li
                                    key={site.id}
                                    className="flex items-center justify-between gap-3 px-3 py-2 hover:bg-muted/40 transition-colors"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className="font-semibold text-foreground text-xs leading-tight"
                                            title={site.name}
                                        >
                                            {site.name}
                                        </p>
                                        <p className="text-[10px] text-muted-foreground mt-0.5">
                                            ID: #{site.id}
                                            {selectedRegion ? ` · Region: ${selectedRegion}` : ''}
                                            {selectedState ? ` · State: ${selectedState}` : ''}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={added ? 'secondary' : 'outline'}
                                        disabled={added || fetching}
                                        onClick={() => onAddSite(site)}
                                        className="shrink-0 h-7 text-xs px-2.5 gap-1"
                                    >
                                        {added ? (
                                            <>
                                                <Check className="size-3 text-emerald-600 dark:text-emerald-400" />
                                                <span>Added</span>
                                            </>
                                        ) : (
                                            <>
                                                <Plus className="size-3" />
                                                <span>Add Site</span>
                                            </>
                                        )}
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {totalPages > 1 && (
                <div className="flex items-center justify-between pt-1 text-xs">
                    <span className="text-[11px] text-muted-foreground">
                        Page {page} of {totalPages} ({sites.length.toLocaleString()} total)
                    </span>
                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={page <= 1}
                            onClick={() => onPageChange(page - 1)}
                            className="size-7 p-0"
                            aria-label="Previous page"
                        >
                            <ChevronLeft className="size-3.5" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={page >= totalPages}
                            onClick={() => onPageChange(page + 1)}
                            className="size-7 p-0"
                            aria-label="Next page"
                        >
                            <ChevronRight className="size-3.5" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Subcomponent: Manual Entry Form                                            */
/* -------------------------------------------------------------------------- */
interface ManualSiteEntryProps {
    readonly name: string;
    readonly region: string;
    readonly state: string;
    readonly onNameChange: (val: string) => void;
    readonly onRegionChange: (val: string) => void;
    readonly onStateChange: (val: string) => void;
    readonly onAdd: () => void;
}

function ManualSiteEntry({
    name,
    region,
    state,
    onNameChange,
    onRegionChange,
    onStateChange,
    onAdd,
}: Readonly<ManualSiteEntryProps>) {
    return (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4">
            <div>
                <p className="font-bold text-xs text-foreground">Manual Site Entry</p>
                <p className="text-[11px] text-muted-foreground">
                    Specify site details directly if not cataloged in Site Access Info.
                </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="space-y-1 sm:col-span-3">
                    <Label htmlFor="manual-site-name" className="text-xs font-semibold">
                        Full Site Name <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="manual-site-name"
                        value={name}
                        onChange={(e) => onNameChange(e.target.value)}
                        placeholder="e.g. AKRON EAST 04 - TOWER 12345 (OH-COL-128)"
                        className="text-xs"
                    />
                </div>
                <div className="space-y-1">
                    <Label htmlFor="manual-region" className="text-[11px] text-muted-foreground">
                        Region (Optional)
                    </Label>
                    <Input
                        id="manual-region"
                        value={region}
                        onChange={(e) => onRegionChange(e.target.value)}
                        placeholder="e.g. Great Lakes"
                        className="text-xs"
                    />
                </div>
                <div className="space-y-1">
                    <Label htmlFor="manual-state" className="text-[11px] text-muted-foreground">
                        State (Optional)
                    </Label>
                    <Input
                        id="manual-state"
                        value={state}
                        onChange={(e) => onStateChange(e.target.value)}
                        placeholder="e.g. OH"
                        className="text-xs"
                    />
                </div>
                <div className="flex items-end">
                    <Button
                        type="button"
                        onClick={onAdd}
                        disabled={!name.trim()}
                        className="w-full text-xs gap-1.5 h-9"
                    >
                        <Plus className="size-3.5" />
                        <span>Add to Package</span>
                    </Button>
                </div>
            </div>
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Subcomponent: Attached Sites Summary                                       */
/* -------------------------------------------------------------------------- */
interface AttachedSitesProps {
    readonly entries: SiteDetailEntry[];
    readonly onRemove: (index: number) => void;
}

function AttachedSites({ entries, onRemove }: Readonly<AttachedSitesProps>) {
    return (
        <div className="space-y-2 rounded-xl border border-border/80 bg-muted/30 p-4">
            <div className="flex items-center justify-between">
                <p className="font-bold text-xs text-foreground flex items-center gap-1.5">
                    <FileCheck2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                    Attached Site Details ({entries.length})
                </p>
                <span className="text-[11px] text-muted-foreground">
                    {entries.length === 0
                        ? 'Optional — none attached'
                        : 'Included in delivered closeout PDF'}
                </span>
            </div>

            {entries.length === 0 ? (
                <div className="py-4 text-center border border-dashed border-border rounded-lg bg-card/50">
                    <p className="text-xs text-muted-foreground">
                        No sites attached yet. Pick from the catalog or add manually above.
                    </p>
                </div>
            ) : (
                <div className="space-y-2 max-h-48 overflow-y-auto">
                    {entries.map((entry, idx) => (
                        <div
                            key={`${entry.source}-${entry.name}-${idx}`}
                            className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 shadow-2xs"
                        >
                            <div className="min-w-0 flex-1">
                                <p
                                    className="font-bold text-xs text-foreground truncate"
                                    title={entry.name}
                                >
                                    {entry.name}
                                </p>
                                <div className="flex items-center gap-1.5 mt-0.5 text-[10px] text-muted-foreground">
                                    <Badge
                                        variant="outline"
                                        className="text-[9px] px-1.5 py-0 uppercase"
                                    >
                                        {entry.site_type === 'hub' ? 'Hub Site' : 'Cell Site'}
                                    </Badge>
                                    <span>·</span>
                                    <span>
                                        {entry.source === 'auth_center'
                                            ? 'Site Access Info'
                                            : 'Manual'}
                                    </span>
                                    {entry.region && <span>· Region: {entry.region}</span>}
                                    {entry.state && <span>· State: {entry.state}</span>}
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onRemove(idx)}
                                className="size-7 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                aria-label={`Remove ${entry.name}`}
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Main Dialog Component                                                      */
/* -------------------------------------------------------------------------- */
export function FinalApprovalDialog({
    open,
    loading = false,
    onOpenChange,
    onConfirm,
}: Readonly<FinalApprovalDialogProps>) {
    const [entryMode, setEntryMode] = React.useState<'catalog' | 'manual'>('catalog');
    const [siteType, setSiteType] = React.useState<'hub' | 'smallcell'>('smallcell');

    const [regions, setRegions] = React.useState<string[]>([]);
    const [states, setStates] = React.useState<StateOption[]>([]);
    const [sites, setSites] = React.useState<SiteItem[]>([]);
    const [selectedRegion, setSelectedRegion] = React.useState('');
    const [selectedState, setSelectedState] = React.useState('');
    const [searchQuery, setSearchQuery] = React.useState('');
    const [page, setPage] = React.useState(1);
    const [fetching, setFetching] = React.useState(false);

    const [manualName, setManualName] = React.useState('');
    const [manualRegion, setManualRegion] = React.useState('');
    const [manualState, setManualState] = React.useState('');

    const [entries, setEntries] = React.useState<SiteDetailEntry[]>([]);

    const fetchOptions = React.useCallback(async (type: string, regionVal?: string) => {
        setFetching(true);
        try {
            const { data } = await axios.get(route('manager.packages.auth-sites.filter-options'), {
                params: { type, region: regionVal || undefined },
            });
            if (!data.success) {
                throw new Error(data.message || 'Failed to load options.');
            }
            if (data.regions && data.regions.length > 0) {
                setRegions(data.regions);
            }
            setStates(data.states ?? []);
        } catch (error) {
            toast.error(
                error instanceof Error && error.message
                    ? error.message
                    : 'Failed to load Site Access Info Catalog options.'
            );
        } finally {
            setFetching(false);
        }
    }, []);

    const fetchSites = React.useCallback(
        async (type: string, regionVal?: string, stateVal?: string) => {
            setFetching(true);
            try {
                const { data } = await axios.get(route('manager.packages.auth-sites'), {
                    params: {
                        type,
                        region: regionVal || undefined,
                        state: stateVal || undefined,
                    },
                });
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load sites.');
                }
                setSites(data.sites ?? []);
                setPage(1);
            } catch (error) {
                toast.error(
                    error instanceof Error && error.message
                        ? error.message
                        : 'Failed to load Site Access Info Catalog sites.'
                );
            } finally {
                setFetching(false);
            }
        },
        []
    );

    // Initial load and filter cascading:
    // Only re-fetch regions/states options when open, siteType, or selectedRegion changes.
    React.useEffect(() => {
        if (open && entryMode === 'catalog') {
            void fetchOptions(siteType, selectedRegion || undefined);
        }
    }, [open, siteType, selectedRegion, entryMode, fetchOptions]);

    // Fetch site listings whenever type, region, or state changes
    React.useEffect(() => {
        if (open && entryMode === 'catalog') {
            void fetchSites(siteType, selectedRegion || undefined, selectedState || undefined);
        }
    }, [open, siteType, selectedRegion, selectedState, entryMode, fetchSites]);

    const handleTypeChange = (type: 'hub' | 'smallcell') => {
        setSiteType(type);
        setSelectedRegion('');
        setSelectedState('');
        setSearchQuery('');
        setPage(1);
    };

    const filteredSites = React.useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) {
            return sites;
        }
        return sites.filter((site) => site.name.toLowerCase().includes(query));
    }, [sites, searchQuery]);

    const totalPages = Math.max(1, Math.ceil(filteredSites.length / SITES_PER_PAGE));

    const isSiteAdded = React.useCallback(
        (siteId: number) => entries.some((e) => e.auth_site_id === siteId),
        [entries]
    );

    const handleAddCatalogSite = (site: SiteItem) => {
        if (isSiteAdded(site.id)) {
            return;
        }
        setEntries((prev) => [
            ...prev,
            {
                source: 'auth_center',
                site_type: siteType,
                name: site.name,
                auth_site_id: site.id,
                ...(selectedRegion ? { region: selectedRegion } : {}),
                ...(selectedState ? { state: selectedState } : {}),
            },
        ]);
        toast.success(`Added ${site.name}`);
    };

    const handleAddManualSite = () => {
        const name = manualName.trim();
        if (!name) {
            toast.error('Please provide a valid site name.');
            return;
        }
        setEntries((prev) => [
            ...prev,
            {
                source: 'manual',
                site_type: siteType,
                name,
                ...(manualRegion.trim() ? { region: manualRegion.trim() } : {}),
                ...(manualState.trim() ? { state: manualState.trim() } : {}),
            },
        ]);
        setManualName('');
        setManualRegion('');
        setManualState('');
        toast.success(`Added manual site ${name}`);
    };

    return (
        <Dialog open={open} onOpenChange={(val) => (val ? undefined : onOpenChange(false))}>
            <DialogContent className="max-w-3xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
                <DialogHeader className="p-6 pb-4 border-b border-border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <ShieldCheck className="size-5" />
                            </span>
                            <div>
                                <DialogTitle className="text-lg font-bold text-foreground">
                                    Package Final Approval & Site Details
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    Assign cell / hub sites and finalize package approval.
                                </DialogDescription>
                            </div>
                        </div>

                        <div className="flex items-center gap-1.5 rounded-lg border border-border/80 bg-muted/50 px-3 py-1 text-xs">
                            <span className="text-muted-foreground font-medium">Customer:</span>
                            <span className="font-bold text-foreground">Verizon Wireless</span>
                            <Badge
                                variant="outline"
                                className="text-[10px] bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 ml-1"
                            >
                                Verified
                            </Badge>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto p-6 space-y-5 text-xs">
                    <div className="flex items-center justify-between gap-3 p-1.5 rounded-xl bg-muted/60 border border-border">
                        <button
                            type="button"
                            onClick={() => setEntryMode('catalog')}
                            className={cn(
                                'flex-1 inline-flex items-center justify-center gap-2 py-2 px-3 rounded-lg font-semibold text-xs transition-all cursor-pointer',
                                entryMode === 'catalog'
                                    ? 'bg-card text-foreground shadow-xs border border-border/80'
                                    : 'text-muted-foreground hover:text-foreground'
                            )}
                        >
                            <FolderSearch className="size-4 text-primary" />
                            <span>Site Access Info Catalog</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setEntryMode('manual')}
                            className={cn(
                                'flex-1 inline-flex items-center justify-center gap-2 py-2 px-3 rounded-lg font-semibold text-xs transition-all cursor-pointer',
                                entryMode === 'manual'
                                    ? 'bg-card text-foreground shadow-xs border border-border/80'
                                    : 'text-muted-foreground hover:text-foreground'
                            )}
                        >
                            <Edit3 className="size-4 text-primary" />
                            <span>Manual Site Entry</span>
                        </button>
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-foreground">
                            Site Classification
                        </Label>
                        <div className="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                onClick={() => handleTypeChange('smallcell')}
                                className={cn(
                                    'flex items-center gap-3 p-3 rounded-xl border text-left transition-all cursor-pointer',
                                    siteType === 'smallcell'
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary text-foreground'
                                        : 'border-border bg-card hover:border-border/80 text-muted-foreground'
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 items-center justify-center rounded-lg',
                                        siteType === 'smallcell'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    )}
                                >
                                    <Radio className="size-4" />
                                </span>
                                <div>
                                    <p className="font-bold text-xs text-foreground">
                                        Cell Site (Small Cell)
                                    </p>
                                    <p className="text-[11px] text-muted-foreground">
                                        Wireless tower & node installations
                                    </p>
                                </div>
                            </button>

                            <button
                                type="button"
                                onClick={() => handleTypeChange('hub')}
                                className={cn(
                                    'flex items-center gap-3 p-3 rounded-xl border text-left transition-all cursor-pointer',
                                    siteType === 'hub'
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary text-foreground'
                                        : 'border-border bg-card hover:border-border/80 text-muted-foreground'
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 items-center justify-center rounded-lg',
                                        siteType === 'hub'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    )}
                                >
                                    <Building2 className="size-4" />
                                </span>
                                <div>
                                    <p className="font-bold text-xs text-foreground">Hub Site</p>
                                    <p className="text-[11px] text-muted-foreground">
                                        Central fiber/power hub stations
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>

                    {entryMode === 'catalog' ? (
                        <CatalogBrowser
                            regions={regions}
                            states={states}
                            sites={filteredSites}
                            selectedRegion={selectedRegion}
                            selectedState={selectedState}
                            searchQuery={searchQuery}
                            page={page}
                            totalPages={totalPages}
                            fetching={fetching}
                            isSiteAdded={isSiteAdded}
                            onRegionChange={(val) => {
                                setSelectedRegion(val);
                                setSelectedState('');
                                setPage(1);
                            }}
                            onStateChange={(val) => {
                                setSelectedState(val);
                                setPage(1);
                            }}
                            onSearchChange={(val) => {
                                setSearchQuery(val);
                                setPage(1);
                            }}
                            onPageChange={setPage}
                            onAddSite={handleAddCatalogSite}
                        />
                    ) : (
                        <ManualSiteEntry
                            name={manualName}
                            region={manualRegion}
                            state={manualState}
                            onNameChange={setManualName}
                            onRegionChange={setManualRegion}
                            onStateChange={setManualState}
                            onAdd={handleAddManualSite}
                        />
                    )}

                    <AttachedSites
                        entries={entries}
                        onRemove={(idx) => setEntries((prev) => prev.filter((_, i) => i !== idx))}
                    />
                </div>

                <DialogFooter className="p-4 border-t border-border bg-card flex flex-row items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onOpenChange(false)}
                        disabled={loading}
                        className="text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="success"
                        loading={loading}
                        onClick={() => onConfirm(entries)}
                        className="text-xs font-bold gap-1.5 px-4"
                    >
                        <ShieldCheck className="size-4" />
                        <span>Approve Package</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default FinalApprovalDialog;
