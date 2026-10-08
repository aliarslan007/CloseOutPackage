import {
    CustomerOptionItem,
    EngineerOptionItem,
    EngineersByCustomerAndMarket,
    PROJECT_STATUSES,
    ProjectFormValues,
    ProjectStatusValue,
} from '@/components/projects/project-form-types';
import { ProjectLocationFields } from '@/components/projects/project-location-fields';
import { ProjectTimelineFields } from '@/components/projects/project-timeline-fields';
import { FormField } from '@/components/shared/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    preparePhotoForUpload,
    rejectedPhotoMessage,
    SUPPORTED_PHOTO_TYPES,
} from '@/services/compression.service';
import { useForm } from '@inertiajs/react';
import { Camera, Upload, X } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ProjectFormProps {
    form: ReturnType<typeof useForm<ProjectFormValues>>;
    markets: Array<{ id: number; name: string }>;
    customers?: CustomerOptionItem[];
    engineers?: EngineersByCustomerAndMarket;
    managers?: Array<{ id: number; name: string; email: string }>;
    supervisors?: Array<{ id: number; name: string; email: string }>;
    includeStatus?: boolean;
    sitePhotoUrl?: string | null;
}

const STATUS_LABELS: Record<ProjectStatusValue, string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    submitted: 'Submitted',
    approved: 'Approved',
    rejected: 'Rejected',
    completed: 'Completed',
    archived: 'Archived',
};

const SELECT_CLASS =
    'h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

function SectionTitle({ children }: Readonly<{ children: React.ReactNode }>) {
    return (
        <h3 className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {children}
        </h3>
    );
}

function SitePhotoField({
    form,
    existingUrl,
}: Readonly<{ form: ReturnType<typeof useForm<ProjectFormValues>>; existingUrl?: string | null }>) {
    const inputRef = React.useRef<HTMLInputElement>(null);
    const [preview, setPreview] = React.useState<string | null>(null);

    React.useEffect(() => {
        if (preview === null) {
            return;
        }

        return () => URL.revokeObjectURL(preview);
    }, [preview]);

    const picked = form.data.site_photo;
    const shownUrl = preview ?? existingUrl ?? null;

    const handleChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';

        if (!file) {
            return;
        }

        const prepared = await preparePhotoForUpload(file);

        if (!prepared.ok) {
            toast.error(rejectedPhotoMessage(file, prepared.reason));
            return;
        }

        setPreview(URL.createObjectURL(prepared.file));
        form.setData('site_photo', prepared.file);
    };

    const handleRemove = () => {
        setPreview(null);
        form.setData('site_photo', null);
    };

    return (
        <FormField
            label="Site Photo"
            htmlFor="site_photo"
            error={form.errors.site_photo}
            helper={`Optional. ${existingUrl || preview ? 'Saving replaces the current photo.' : 'JPG, PNG, WEBP or HEIC.'}`}
        >
            <div className="space-y-2">
                <div className="relative aspect-[4/3] w-full overflow-hidden rounded-lg border border-border/70 bg-muted/40">
                    {shownUrl ? (
                        <img src={shownUrl} alt="Site preview" className="h-full w-full object-cover" />
                    ) : (
                        <div className="flex h-full flex-col items-center justify-center gap-1.5 text-muted-foreground">
                            <Camera className="size-6" />
                            <span className="text-[11px]">No site photo yet</span>
                        </div>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs gap-1"
                        onClick={() => inputRef.current?.click()}
                    >
                        <Upload className="size-3.5" /> {shownUrl ? 'Replace Photo' : 'Upload Photo'}
                    </Button>
                    {picked !== null && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 text-xs gap-1 text-destructive hover:bg-destructive/10"
                            onClick={handleRemove}
                        >
                            <X className="size-3.5" /> Remove
                        </Button>
                    )}
                </div>
                <input
                    ref={inputRef}
                    id="site_photo"
                    name="site_photo"
                    type="file"
                    accept={SUPPORTED_PHOTO_TYPES.join(',')}
                    onChange={handleChange}
                    className="hidden"
                    aria-label="Upload site photo"
                />
            </div>
        </FormField>
    );
}

export function ProjectFormFields({
    form,
    markets,
    customers = [],
    engineers = {},
    managers: _managers = [],
    supervisors: _supervisors = [],
    includeStatus = false,
    sitePhotoUrl,
}: Readonly<ProjectFormProps>) {
    const setString =
        (key: keyof ProjectFormValues) =>
            (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
                form.setData(key, e.target.value);

    const customerId = form.data.customer_id === '' ? null : Number(form.data.customer_id);
    const regionId = form.data.market_id === '' ? null : Number(form.data.market_id);

    const availableEngineers: EngineerOptionItem[] =
        customerId !== null && regionId !== null ? (engineers[customerId]?.[regionId] ?? []) : [];

    const selectedEngineer =
        availableEngineers.find((engineer) => engineer.id === form.data.customer_engineer_id) ?? null;

    /**
     * Keeps the cascade consistent: picking a customer or region re-resolves
     * the engineer list and pre-selects that region's default contact.
     */
    const applySelection = (nextCustomerId: number | '', nextRegionId: number | '') => {
        const list =
            nextCustomerId !== '' && nextRegionId !== ''
                ? (engineers[Number(nextCustomerId)]?.[Number(nextRegionId)] ?? [])
                : [];
        const defaultEngineer = list.find((engineer) => engineer.is_default) ?? (list.length > 0 ? list[0] : undefined);

        form.setData((data) => ({
            ...data,
            customer_id: nextCustomerId,
            market_id: nextRegionId,
            customer_engineer_id: defaultEngineer?.id ?? '',
        }));
    };

    return (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-5">
                {includeStatus && (
                    <FormField label="Status" htmlFor="status" error={form.errors.status}>
                        <select
                            id="status"
                            name="status"
                            value={form.data.status}
                            onChange={(e) => form.setData('status', e.target.value as ProjectStatusValue)}
                            className={`${SELECT_CLASS} sm:max-w-xs`}
                        >
                            {PROJECT_STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {STATUS_LABELS[status]}
                                </option>
                            ))}
                        </select>
                    </FormField>
                )}

                {/* 1. Customer & Territory (First Row) */}
                <div className="space-y-3">
                    <SectionTitle>Customer &amp; Engineering Territory</SectionTitle>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Customer" htmlFor="customer_id" error={form.errors.customer_id}>
                            <select
                                id="customer_id"
                                name="customer_id"
                                value={form.data.customer_id}
                                onChange={(e) =>
                                    applySelection(
                                        e.target.value === '' ? '' : Number(e.target.value),
                                        form.data.market_id
                                    )
                                }
                                className={SELECT_CLASS}
                            >
                                <option value="">Select a customer…</option>
                                {customers.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name} ({customer.code})
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Region / Market"
                            htmlFor="market_id"
                            required
                            error={form.errors.market_id}
                        >
                            <select
                                id="market_id"
                                name="market_id"
                                value={form.data.market_id}
                                onChange={(e) =>
                                    applySelection(
                                        form.data.customer_id,
                                        e.target.value === '' ? '' : Number(e.target.value)
                                    )
                                }
                                className={SELECT_CLASS}
                                required
                            >
                                <option value="">Select a region…</option>
                                {markets.map((market) => (
                                    <option key={market.id} value={market.id}>
                                        {market.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                    </div>

                    <FormField
                        label="Customer Engineer / PM"
                        htmlFor="customer_engineer_id"
                        error={form.errors.customer_engineer_id}
                        helper={
                            availableEngineers.length === 0
                                ? 'No engineers configured for this customer and region yet.'
                                : 'The region default is pre-selected — switch to another engineer anytime.'
                        }
                    >
                        <select
                            id="customer_engineer_id"
                            name="customer_engineer_id"
                            value={form.data.customer_engineer_id}
                            onChange={(e) =>
                                form.setData(
                                    'customer_engineer_id',
                                    e.target.value === '' ? '' : Number(e.target.value)
                                )
                            }
                            className={SELECT_CLASS}
                            disabled={availableEngineers.length === 0}
                        >
                            <option value="">No engineer selected</option>
                            {availableEngineers.map((engineer) => (
                                <option key={engineer.id} value={engineer.id}>
                                    {engineer.name}
                                    {engineer.is_default ? ' (Default)' : ''} — {engineer.email}
                                </option>
                            ))}
                        </select>
                    </FormField>

                    {selectedEngineer && (
                        <div className="rounded-lg border border-border bg-muted/30 px-3 py-2.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-foreground">
                                    {selectedEngineer.name}
                                </span>
                                {selectedEngineer.title && (
                                    <span className="text-xs text-muted-foreground">
                                        — {selectedEngineer.title}
                                    </span>
                                )}
                                {selectedEngineer.is_default && (
                                    <Badge className="gap-1 border-amber-500/30 bg-amber-500/10 text-[10px] text-amber-700 dark:text-amber-300">
                                        Default for this region
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>{selectedEngineer.email}</span>
                                {selectedEngineer.phone && <span>{selectedEngineer.phone}</span>}
                            </div>
                        </div>
                    )}
                </div>

                {/* 2. Project Information */}
                <div className="space-y-3">
                    <SectionTitle>Project Information</SectionTitle>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormField label="Project Name" htmlFor="name" required error={form.errors.name}>
                            <Input
                                id="name"
                                name="name"
                                value={form.data.name}
                                onChange={setString('name')}
                                placeholder="e.g. Tower 1234 — Main Street"
                                maxLength={255}
                                required
                                error={Boolean(form.errors.name)}
                            />
                        </FormField>

                        <FormField label="PS #" htmlFor="ps_number" error={form.errors.ps_number}>
                            <Input
                                id="ps_number"
                                name="ps_number"
                                value={form.data.ps_number}
                                onChange={setString('ps_number')}
                                placeholder="e.g. PS-2026-0001"
                                maxLength={20}
                                pattern="[A-Za-z0-9\-]+"
                                title="PS # should contain only letters, digits, and hyphens"
                                error={Boolean(form.errors.ps_number)}
                            />
                        </FormField>

                        <FormField
                            label="Project Number"
                            htmlFor="project_number"
                            error={form.errors.project_number}
                        >
                            <Input
                                id="project_number"
                                name="project_number"
                                value={form.data.project_number}
                                onChange={setString('project_number')}
                                maxLength={80}
                                pattern="[A-Za-z0-9\-]+"
                                title="Project Number should contain only letters, digits, and hyphens"
                                error={Boolean(form.errors.project_number)}
                            />
                        </FormField>
                    </div>
                </div>

                {/* 3. Site Location */}
                <div className="space-y-3">
                    <SectionTitle>Site Location</SectionTitle>
                    <ProjectLocationFields form={form} />
                </div>

                {/* 4. Project Timeline */}
                <div className="space-y-3">
                    <SectionTitle>Project Timeline</SectionTitle>
                    <ProjectTimelineFields form={form} />
                </div>

                {/* 5. Notes */}
                <FormField label="Project Notes" htmlFor="notes" error={form.errors.notes}>
                    <div className="space-y-1">
                        <Textarea
                            id="notes"
                            name="Project Notes"
                            value={form.data.notes}
                            onChange={setString('notes')}
                            placeholder="Internal notes about this project..."
                            maxLength={2000}
                            error={Boolean(form.errors.notes)}
                        />
                        <p className="text-right text-[10px] text-muted-foreground">
                            {form.data.notes.length} / 2000
                        </p>
                    </div>
                </FormField>
            </div>

            <div className="lg:col-span-1">
                <SitePhotoField form={form} existingUrl={sitePhotoUrl} />
            </div>
        </div>
    );
}
