import { EmptyCard } from '@/components/shared/empty-card';
import { GpsBadge } from '@/components/shared/gps-badge';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Camera,
    CheckCircle2,
    Download,
    FileText,
    MapPin,
    ShieldCheck,
    User,
} from 'lucide-react';
import * as React from 'react';

const PORTAL_LABEL = 'Customer Portal';
const PORTAL_INDEX_ROUTE = 'portal.index';

export interface CustomerPhotoData {
    id: number;
    category: string;
    photo_url: string;
    url?: string;
    notes?: string | null;
    created_at?: string;
    gps_latitude?: number | null;
    gps_longitude?: number | null;
    uploader?: { id: number; name: string } | null;
    category_selection?: {
        template_picture_category?: { name?: string } | null;
    } | null;
}

export interface CustomerPackageDetails {
    id: number;
    hash_id?: string;
    status: string;
    revision_major?: number;
    revision_minor?: number;
    approved_at?: string | null;
    template?: { id: number; name: string } | null;
    creator?: { id: number; name: string } | null;
    project?: {
        id: number;
        hash_id?: string;
        name: string;
        ps_number?: string | null;
        address?: string | null;
        status: string;
        market?: { id: number; name: string } | null;
        manager?: { id: number; name: string } | null;
    } | null;
    photos?: CustomerPhotoData[];
}

export type CustomerPackageShowProps = PageProps<{
    package: CustomerPackageDetails;
}>;

function formatDate(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export function CustomerPackageShow() {
    useFlashToasts();

    const { package: pkg } = usePage<CustomerPackageShowProps>().props;

    const photos = React.useMemo(() => pkg?.photos ?? [], [pkg?.photos]);

    // Group photos by category so the customer sees a tidy, labelled archive.
    const photosByCategory = React.useMemo(() => {
        const map = new Map<string, CustomerPhotoData[]>();
        photos.forEach((p) => {
            const cat =
                p.category || p.category_selection?.template_picture_category?.name || 'General';
            if (!map.has(cat)) map.set(cat, []);
            map.get(cat)?.push(p);
        });
        return map;
    }, [photos]);

    if (!pkg) {
        return (
            <AppLayout
                title="Package Details"
                breadcrumb={[
                    { label: PORTAL_LABEL, href: route(PORTAL_INDEX_ROUTE) },
                    { label: 'Package' },
                ]}
            >
                <Head title="Package Details" />
                <EmptyCard
                    title="Package unavailable"
                    description="This package is either not approved yet or you do not have permission to view it."
                    icon={<FileText className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    const project = pkg.project;
    const revisionStr = `R${pkg.revision_major ?? 1}.${pkg.revision_minor ?? 0}`;
    const packageTitle = `Package #${pkg.hash_id || pkg.id}`;
    const approvedDate = formatDate(pkg.approved_at);
    const projectHref = project
        ? route('portal.projects.show', project.hash_id || project.id)
        : route(PORTAL_INDEX_ROUTE);

    return (
        <AppLayout
            title={`${packageTitle} - ${project?.name ?? PORTAL_LABEL}`}
            breadcrumb={[
                { label: PORTAL_LABEL, href: route(PORTAL_INDEX_ROUTE) },
                { label: project?.name ?? 'Project', href: projectHref },
                { label: packageTitle },
            ]}
        >
            <Head title={`${packageTitle} - ${project?.name ?? PORTAL_LABEL}`} />

            <div className="space-y-2">
                <Link
                    href={projectHref}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project?.name ?? 'Customer Portal Projects'}
                </Link>

                {/* ── Project Overview ─────────────────────────────────────── */}
                <div className="rounded-xl border border-border/80 bg-card p-4 shadow-2xs space-y-3">
                    <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-border/60 pb-3">
                        <div className="space-y-1.5 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                    <Building2 className="size-5 text-primary shrink-0" />
                                    {project?.name ?? 'Project'}
                                </h1>
                                {project?.ps_number && (
                                    <Badge variant="outline" className="font-mono text-[10px]">
                                        {project.ps_number}
                                    </Badge>
                                )}
                                {project?.status && <StatusBadge status={project.status} />}
                            </div>
                            {project?.address && (
                                <p className="text-xs text-muted-foreground flex items-center gap-1.5">
                                    <MapPin className="size-3 text-primary shrink-0" />
                                    {project.address}
                                </p>
                            )}
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                            {project?.market?.name && (
                                <Badge variant="outline" className="gap-1 text-xs">
                                    <MapPin className="size-3 text-primary" />
                                    {project.market.name}
                                </Badge>
                            )}
                            <span className="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                                <ShieldCheck className="size-3.5" /> Read-Only Customer View
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        {project?.manager?.name && (
                            <span>
                                Project Manager:{' '}
                                <span className="font-semibold text-foreground">
                                    {project.manager.name}
                                </span>
                            </span>
                        )}
                        <span className="inline-flex items-center gap-1">
                            <Calendar className="size-3 text-primary" />
                            Approved {approvedDate}
                        </span>
                    </div>
                </div>

                {/* ── Package Overview ─────────────────────────────────────── */}
                <div className="rounded-xl border border-border/80 bg-card p-4 shadow-2xs space-y-3">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/60 pb-3">
                        <div className="space-y-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h2 className="font-heading text-base font-bold text-foreground">
                                    {packageTitle}
                                </h2>
                                <Badge
                                    variant="outline"
                                    className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[11px] gap-1"
                                >
                                    <CheckCircle2 className="size-3" /> Approved
                                </Badge>
                            </div>
                            <p className="text-[11px] text-muted-foreground">
                                Delivered close-out documentation for this cell site.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 flex-wrap shrink-0">
                            <Badge variant="secondary" className="font-mono text-xs font-bold">
                                Revision {revisionStr}
                            </Badge>
                            <Button asChild size="sm" className="gap-2 text-xs font-mono">
                                <a
                                    href={route('portal.packages.pdf', pkg.hash_id || pkg.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download className="size-3.5" /> Download Final PDF
                                </a>
                            </Button>
                        </div>
                    </div>

                    {/* How this package got here — plain language for a layman */}
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-2 text-[11px]">
                        <span className="inline-flex items-center gap-1.5 font-bold uppercase tracking-wide text-muted-foreground mr-1 shrink-0">
                            <FileText className="size-3.5 text-primary" /> Package Status
                        </span>
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-600 dark:text-emerald-400 font-semibold whitespace-nowrap">
                            <Camera className="size-3.5" /> Photos Captured
                        </span>
                        <span className="hidden sm:block h-px w-4 bg-border" />
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-600 dark:text-emerald-400 font-semibold whitespace-nowrap">
                            <ShieldCheck className="size-3.5" /> Manager Approved
                        </span>
                        <span className="hidden sm:block h-px w-4 bg-border" />
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-600 dark:text-emerald-400 font-semibold whitespace-nowrap">
                            <CheckCircle2 className="size-3.5" /> Published
                        </span>
                        {pkg.creator?.name && (
                            <span className="inline-flex items-center gap-1 text-muted-foreground ml-auto">
                                <User className="size-3" /> Prepared by{' '}
                                <span className="font-semibold text-foreground">
                                    {pkg.creator.name}
                                </span>
                            </span>
                        )}
                    </div>
                </div>

                {/* ── Approved Photos ──────────────────────────────────────── */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                        <h2 className="font-bold text-sm text-foreground uppercase tracking-wide flex items-center gap-2">
                            <Camera className="size-4 text-primary" />
                            Approved Site Photos ({photos.length})
                        </h2>
                        <p className="text-[11px] text-muted-foreground">
                            Every photo below has been reviewed and signed off by the project
                            manager.
                        </p>
                    </div>

                    {photosByCategory.size === 0 ? (
                        <EmptyCard
                            title="No approved photos yet"
                            description="This package has been approved, but no site photos have been published yet. Please check back shortly."
                            icon={<Camera className="size-10 text-muted-foreground" />}
                        />
                    ) : (
                        Array.from(photosByCategory.entries()).map(([category, catPhotos]) => (
                            <div
                                key={category}
                                className="rounded-xl border border-border/80 bg-card p-4 shadow-2xs space-y-3"
                            >
                                <div className="flex items-center justify-between border-b border-border/60 pb-2">
                                    <h3 className="font-heading text-xs font-bold text-foreground uppercase tracking-wide">
                                        {category}
                                    </h3>
                                    <Badge variant="outline" className="text-[10px] font-mono">
                                        {catPhotos.length} photo{catPhotos.length === 1 ? '' : 's'}
                                    </Badge>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    {catPhotos.map((photo) => (
                                        <div
                                            key={photo.id}
                                            className="group flex flex-col justify-between h-full rounded-lg border border-border/80 bg-muted/20 overflow-hidden p-2 transition-shadow hover:shadow-md"
                                        >
                                            <div className="w-full space-y-2">
                                                <div className="relative aspect-[4/3] w-full h-44 sm:h-48 rounded-md overflow-hidden bg-black/5 dark:bg-white/5 shrink-0">
                                                    <img
                                                        src={photo.url ?? photo.photo_url}
                                                        alt={category}
                                                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                                        loading="lazy"
                                                    />
                                                {photo.gps_latitude != null &&
                                                    photo.gps_longitude != null && (
                                                        <GpsBadge
                                                            latitude={photo.gps_latitude}
                                                            longitude={photo.gps_longitude}
                                                            className="absolute bottom-2 left-2"
                                                        />
                                                    )}
                                            </div>
                                            {photo.uploader?.name && (
                                                <p className="text-[10px] text-muted-foreground flex items-center gap-1 truncate">
                                                    <User className="size-3 shrink-0" />
                                                    Captured by {photo.uploader.name}
                                                </p>
                                            )}
                                            {photo.notes && (
                                                <p
                                                    className={cn(
                                                        'text-[11px] text-muted-foreground font-mono truncate',
                                                        !photo.uploader?.name && 'pt-1'
                                                    )}
                                                    title={photo.notes}
                                                >
                                                    {photo.notes}
                                                </p>
                                            )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

export default CustomerPackageShow;
