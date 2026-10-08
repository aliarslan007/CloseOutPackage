import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { MediaAsset } from '@/features/media-library/types/media-library.types';
import { SowMediaLibraryModal } from '@/features/sow/components/dialogs/sow-media-library-modal';
import { SowAutofillBadge } from '@/features/sow/components/report/sow-autofill-badge';
import { SOW_FIELD_LIMITS } from '@/features/sow/components/report/sow-field-limits';
import type { SowProjectOption } from '@/features/sow/types/sow.types';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Camera,
    Check,
    ImagePlus,
    Info,
    X,
} from 'lucide-react';
import * as React from 'react';

export interface SowCreateProps extends PageProps {
    markets: Array<{ id: number; name: string; slug: string }>;
    projects: SowProjectOption[];
}

const SELECT_CLASS =
    'h-9 w-full rounded-lg border border-border bg-background px-2.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20';
const REPORTS_INDEX_ROUTE = 'sow.reports.index';

const EMPTY_FORM = {
    market_id: '',
    project_id: '',
    title: '',
    site_name: '',
    report_date: '',
    fiber_provider: 'VZB',
    site_address: '',
    site_city: '',
    site_county: '',
    latitude: '',
    longitude: '',
    project_description: '',
    field_survey_date: '',
    provider_contact_name: '',
    provider_contact_phone: '',
    provider_contact_email: '',
    demarcation: '',
    ps_number: '',
    survey_contractor_name: '',
    survey_contact_name: '',
    survey_contact_phone: '',
    survey_contact_email: '',
    scope_of_work_description: '',
    site_access_notes: '',
    cover_media_asset_id: '',
    include_project_site_photo: false as boolean,
};

type FormKey = keyof typeof EMPTY_FORM;

type TextKey = Exclude<FormKey, 'include_project_site_photo'>;

/** Field → project value. Only applied (and badged) when the field is empty. */
const AUTOFILL_MAP: Array<{ field: FormKey; from: (project: SowProjectOption) => string | null }> =
    [
        { field: 'site_name', from: (project) => project.name },
        { field: 'site_address', from: (project) => project.address },
        { field: 'site_city', from: (project) => project.city },
        { field: 'site_county', from: (project) => project.county },
        { field: 'latitude', from: (project) => project.latitude },
        { field: 'longitude', from: (project) => project.longitude },
        { field: 'ps_number', from: (project) => project.ps_number },
        { field: 'project_description', from: (project) => project.description },
    ];

const STEPS = [
    {
        id: 'report',
        label: '1. Report',
        intro: 'Start with the basics. Picking a project fills in the rest.',
    },
    {
        id: 'site',
        label: '2. Site Details',
        intro: 'Confirm the site details. Anything pulled from the project is marked.',
    },
    {
        id: 'survey',
        label: '3. Survey Data',
        intro: 'Field survey findings and contacts. All fields in this step are optional.',
    },
] as const;

const STEP_FIELDS: FormKey[][] = [
    ['market_id', 'project_id', 'title', 'site_name', 'report_date'],
    ['site_address', 'site_city', 'site_county', 'latitude', 'longitude', 'project_description'],
    [
        'field_survey_date',
        'fiber_provider',
        'demarcation',
        'ps_number',
        'scope_of_work_description',
        'site_access_notes',
        'provider_contact_name',
        'provider_contact_phone',
        'provider_contact_email',
        'survey_contractor_name',
        'survey_contact_name',
        'survey_contact_phone',
        'survey_contact_email',
    ],
];
function stepCircleClass(done: boolean, current: boolean): string {
    if (done) return 'bg-primary text-primary-foreground';
    if (current) return 'border-2 border-primary text-primary';
    return 'border border-border text-muted-foreground';
}

export function SowCreate({ markets, projects }: Readonly<SowCreateProps>) {
    useFlashToasts();
    const form = useForm(EMPTY_FORM);
    const [step, setStep] = React.useState(0);
    const [autoFilled, setAutoFilled] = React.useState<Set<string>>(new Set());
    const [coverAsset, setCoverAsset] = React.useState<MediaAsset | null>(null);
    const [includePhoto, setIncludePhoto] = React.useState(false);
    const [photoBroken, setPhotoBroken] = React.useState(false);
    const [pickerOpen, setPickerOpen] = React.useState(false);
    const headingRef = React.useRef<HTMLHeadingElement>(null);

    const marketProjects = React.useMemo(
        () => projects.filter((project) => project.market_id === Number(form.data.market_id)),
        [projects, form.data.market_id]
    );

    const project = React.useMemo(
        () => projects.find((item) => item.id === Number(form.data.project_id)) ?? null,
        [projects, form.data.project_id]
    );

    React.useEffect(() => {
        headingRef.current?.focus({ preventScroll: true });
    }, [step]);

    // A failed submit jumps to the first step holding an error.
    const errorSignature = Object.keys(form.errors)
        .sort((a, b) => a.localeCompare(b))
        .join(',');
    React.useEffect(() => {
        if (errorSignature === '') return;
        const keys = errorSignature.split(',');
        const index = STEP_FIELDS.findIndex((fields) =>
            fields.some((field) => keys.includes(field))
        );
        if (index !== -1) setStep(index);
    }, [errorSignature]);

    const setField = (key: TextKey, value: string) => {
        form.setData(key, value);
        setAutoFilled((prev) => {
            if (!prev.has(key)) return prev;
            const next = new Set(prev);
            next.delete(key);
            return next;
        });
    };

    const resetProjectState = () => {
        setAutoFilled(new Set());
        setCoverAsset(null);
        setIncludePhoto(false);
        setPhotoBroken(false);
        form.setData((prev) => ({
            ...prev,
            cover_media_asset_id: '',
            include_project_site_photo: false,
        }));
    };

    const onProjectChange = (projectId: string) => {
        resetProjectState();
        const next = projects.find((item) => item.id === Number(projectId)) ?? null;
        const patch: Partial<typeof EMPTY_FORM> = { project_id: projectId };
        const filled = new Set<string>();
        if (next) {
            for (const { field, from } of AUTOFILL_MAP) {
                const value = from(next);
                if ((form.data[field] ?? '') === '' && value) {
                    (patch as Record<string, unknown>)[field] = value;
                    filled.add(field);
                }
            }
        }
        form.setData((prev) => ({ ...prev, ...patch }));
        setAutoFilled(filled);
    };

    const onMarketChange = (marketId: string) => {
        form.setData('market_id', marketId);
        const currentValid =
            form.data.project_id !== '' &&
            projects.some(
                (item) =>
                    item.id === Number(form.data.project_id) && item.market_id === Number(marketId)
            );
        if (!currentValid && form.data.project_id !== '') onProjectChange('');
    };

    const badge = (key: FormKey) =>
        autoFilled.has(key) && project ? (
            <SowAutofillBadge projectName={project.name} />
        ) : undefined;

    const stepValid =
        form.data.market_id !== '' &&
        form.data.title.trim() !== '' &&
        (form.data.project_id !== '' || form.data.site_name.trim() !== '');

    const autoFilledCount = STEP_FIELDS[1].filter((field) => autoFilled.has(field)).length;

    const sitePhotoUrl = !coverAsset && !photoBroken ? (project?.site_photo_url ?? null) : null;

    const handleFinalSubmit = () => {
        form.post(route('sow.reports.store'));
    };

    return (
        <AppLayout
            title="New SOW Report"
            breadcrumb={[
                { label: 'Scope of Work', href: route(REPORTS_INDEX_ROUTE) },
                { label: 'New Report' },
            ]}
            headerActions={
                <Button asChild variant="ghost" size="sm" className="gap-1.5 text-xs">
                    <Link href={route(REPORTS_INDEX_ROUTE)}>
                        <ArrowLeft className="size-4" /> Back to Scope of Work
                    </Link>
                </Button>
            }
        >
            <Head title="New SOW Report" />
            <div className="mx-auto w-full max-w-2xl space-y-6">
                <nav aria-label="Progress">
                    <ol className="flex items-center gap-2">
                        {STEPS.map((item, index) => {
                            const done = index < step;
                            const current = index === step;
                            return (
                                <li
                                    key={item.id}
                                    className={cn(
                                        'flex items-center gap-2',
                                        index < STEPS.length - 1 && 'flex-1'
                                    )}
                                >
                                    <button
                                        type="button"
                                        disabled={!done}
                                        onClick={() => setStep(index)}
                                        aria-current={current ? 'step' : undefined}
                                        className={cn(
                                            'flex items-center gap-2',
                                            !done && !current && 'cursor-default'
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-6 items-center justify-center rounded-full text-xs font-semibold',
                                                stepCircleClass(done, current)
                                            )}
                                        >
                                            {done ? <Check className="size-3.5" /> : index + 1}
                                        </span>
                                        <span
                                            className={cn(
                                                'hidden text-xs font-medium sm:inline',
                                                current
                                                    ? 'text-foreground'
                                                    : 'text-muted-foreground'
                                            )}
                                        >
                                            {item.label}
                                        </span>
                                    </button>
                                    {index < STEPS.length - 1 && (
                                        <span
                                            aria-hidden="true"
                                            className="h-px flex-1 bg-border"
                                        />
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </nav>

                <div>
                    <h1
                        ref={headingRef}
                        tabIndex={-1}
                        className="text-xl font-semibold outline-none"
                    >
                        {STEPS[step].label}
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            Step {step + 1} of {STEPS.length}
                        </span>
                    </h1>
                    <p className="mt-0.5 text-sm text-muted-foreground">{STEPS[step].intro}</p>
                </div>

                <div
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && (e.target as HTMLElement).tagName === 'INPUT') {
                            e.preventDefault();
                            if (step === 0 && stepValid) {
                                setStep(1);
                            } else if (step === 1) {
                                setStep(2);
                            }
                        }
                    }}
                    className="space-y-6"
                >
                    {step === 0 && (
                        <Card>
                            <CardContent className="space-y-4 pt-6">
                                <FormField label="Market" required error={form.errors.market_id}>
                                    <select
                                        value={form.data.market_id}
                                        onChange={(e) => onMarketChange(e.target.value)}
                                        className={SELECT_CLASS}
                                    >
                                        <option value="">Select a market…</option>
                                        {markets.map((market) => (
                                            <option key={market.id} value={market.id}>
                                                {market.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>

                                <FormField
                                    label="Project (optional)"
                                    error={form.errors.project_id}
                                    helper="Pulls the site details and site photo from the project."
                                >
                                    <select
                                        value={form.data.project_id}
                                        onChange={(e) => onProjectChange(e.target.value)}
                                        className={SELECT_CLASS}
                                        disabled={form.data.market_id === ''}
                                    >
                                        <option value="">Standalone SOW (no project)</option>
                                        {marketProjects.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.name}
                                                {item.ps_number ? ` · PS# ${item.ps_number}` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>

                                {project && (
                                    <div className="overflow-hidden rounded-lg border border-border">
                                        {coverAsset && (
                                            <div className="flex gap-3 p-3">
                                                <img
                                                    src={
                                                        coverAsset.thumb_url || coverAsset.url || ''
                                                    }
                                                    alt={coverAsset.original_filename}
                                                    className="h-20 w-28 shrink-0 rounded-md object-cover"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">
                                                        {coverAsset.original_filename}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        Replacement from the library. It becomes the
                                                        cover photo of the PDF, instead of the
                                                        project photo.
                                                    </p>
                                                    <div className="mt-2 flex gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="gap-1.5"
                                                            onClick={() => setPickerOpen(true)}
                                                        >
                                                            <ImagePlus className="size-3.5" />
                                                            Replace
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="gap-1.5"
                                                            onClick={() => {
                                                                setCoverAsset(null);
                                                                form.setData(
                                                                    'cover_media_asset_id',
                                                                    ''
                                                                );
                                                                setIncludePhoto(true);
                                                                form.setData(
                                                                    'include_project_site_photo',
                                                                    true
                                                                );
                                                            }}
                                                        >
                                                            <X className="size-3.5" />
                                                            Use project photo
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                        {!coverAsset && sitePhotoUrl && (
                                            <div className="flex gap-3 p-3">
                                                <img
                                                    src={sitePhotoUrl}
                                                    alt={`${project.name} site`}
                                                    onError={() => setPhotoBroken(true)}
                                                    className="h-20 w-28 shrink-0 rounded-md object-cover"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-medium">
                                                        Site photo
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        Pulled from {project.name}. It becomes the
                                                        cover photo of the PDF.
                                                    </p>
                                                    <label className="mt-2 flex cursor-pointer items-center gap-2 text-xs font-medium">
                                                        <input
                                                            type="checkbox"
                                                            checked={includePhoto}
                                                            onChange={(e) => {
                                                                setIncludePhoto(e.target.checked);
                                                                form.setData(
                                                                    'include_project_site_photo',
                                                                    e.target.checked
                                                                );
                                                            }}
                                                            className="size-4 accent-primary"
                                                        />
                                                        {'Attach as cover photo'}
                                                    </label>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="mt-2 gap-1.5"
                                                        onClick={() => setPickerOpen(true)}
                                                    >
                                                        <ImagePlus className="size-3.5" />
                                                        Replace
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                        {!coverAsset && !sitePhotoUrl && (
                                            <div className="flex items-center gap-3 p-3">
                                                <div className="flex size-11 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                                    <Camera className="size-5" />
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {project.name} has no site photo yet. You can
                                                    add photos after creating the report.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}
                                {project && !coverAsset && (
                                    <p className="text-xs text-muted-foreground">
                                        Taken with your phone? Upload it to the Media Library first,
                                        then pick it with Replace.
                                    </p>
                                )}

                                <FormField
                                    label="Title"
                                    required
                                    error={form.errors.title}
                                    maxLength={SOW_FIELD_LIMITS.title}
                                    valueLength={form.data.title.length}
                                >
                                    <Input
                                        value={form.data.title}
                                        onChange={(e) => setField('title', e.target.value)}
                                        placeholder="e.g. LOMAS PEAK SOW"
                                        maxLength={SOW_FIELD_LIMITS.title}
                                    />
                                </FormField>
                                <FormField
                                    label="Site Name"
                                    required={form.data.project_id === ''}
                                    error={form.errors.site_name}
                                    badge={badge('site_name')}
                                    maxLength={SOW_FIELD_LIMITS.site_name}
                                    valueLength={form.data.site_name.length}
                                    helper={
                                        form.data.project_id === ''
                                            ? 'Required for standalone SOWs.'
                                            : undefined
                                    }
                                >
                                    <Input
                                        value={form.data.site_name}
                                        onChange={(e) => setField('site_name', e.target.value)}
                                        placeholder="e.g. LOMAS PEAK"
                                        maxLength={SOW_FIELD_LIMITS.site_name}
                                    />
                                </FormField>
                                <FormField label="Report Date" error={form.errors.report_date}>
                                    <Input
                                        type="date"
                                        value={form.data.report_date ? form.data.report_date.slice(0, 10) : ''}
                                        onChange={(e) => setField('report_date', e.target.value)}
                                    />
                                </FormField>
                            </CardContent>
                        </Card>
                    )}

                    {step === 1 && (
                        <Card>
                            <CardContent className="space-y-4 pt-6">
                                {project && autoFilledCount > 0 && (
                                    <p className="flex items-center gap-1.5 rounded-lg border border-border bg-muted px-3 py-2 text-xs font-medium text-muted-foreground">
                                        <Info className="size-3.5 shrink-0" />
                                        {autoFilledCount} field{autoFilledCount === 1 ? '' : 's'}{' '}
                                        filled from {project.name} — edit anything.
                                    </p>
                                )}
                                <FormField
                                    label="Site Address"
                                    error={form.errors.site_address}
                                    badge={badge('site_address')}
                                    maxLength={SOW_FIELD_LIMITS.site_address}
                                    valueLength={form.data.site_address.length}
                                >
                                    <Input
                                        value={form.data.site_address}
                                        onChange={(e) => setField('site_address', e.target.value)}
                                        maxLength={SOW_FIELD_LIMITS.site_address}
                                    />
                                </FormField>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Site City"
                                        error={form.errors.site_city}
                                        badge={badge('site_city')}
                                        maxLength={SOW_FIELD_LIMITS.site_city}
                                        valueLength={form.data.site_city.length}
                                    >
                                        <Input
                                            value={form.data.site_city}
                                            onChange={(e) => setField('site_city', e.target.value)}
                                            maxLength={SOW_FIELD_LIMITS.site_city}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Site County"
                                        error={form.errors.site_county}
                                        badge={badge('site_county')}
                                        maxLength={SOW_FIELD_LIMITS.site_county}
                                        valueLength={form.data.site_county.length}
                                    >
                                        <Input
                                            value={form.data.site_county}
                                            onChange={(e) =>
                                                setField('site_county', e.target.value)
                                            }
                                            maxLength={SOW_FIELD_LIMITS.site_county}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Latitude"
                                        error={form.errors.latitude}
                                        badge={badge('latitude')}
                                        helper="Decimal degrees, e.g. 37.1234567"
                                    >
                                        <Input
                                            value={form.data.latitude}
                                            onChange={(e) => setField('latitude', e.target.value)}
                                            inputMode="decimal"
                                        />
                                    </FormField>
                                    <FormField
                                        label="Longitude"
                                        error={form.errors.longitude}
                                        badge={badge('longitude')}
                                        helper="Decimal degrees, e.g. -122.1234567"
                                    >
                                        <Input
                                            value={form.data.longitude}
                                            onChange={(e) => setField('longitude', e.target.value)}
                                            inputMode="decimal"
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    label="Project Description"
                                    error={form.errors.project_description}
                                    badge={badge('project_description')}
                                    maxLength={SOW_FIELD_LIMITS.project_description}
                                    valueLength={form.data.project_description.length}
                                >
                                    <Textarea
                                        value={form.data.project_description}
                                        onChange={(e) =>
                                            setField('project_description', e.target.value)
                                        }
                                        rows={3}
                                        maxLength={SOW_FIELD_LIMITS.project_description}

                                    />
                                </FormField>
                            </CardContent>
                        </Card>
                    )}

                    {step === 2 && (
                        <Card>
                            <CardContent className="space-y-5 pt-6">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Field Survey Date"
                                        error={form.errors.field_survey_date}
                                    >
                                        <Input
                                            type="date"
                                            value={
                                                form.data.field_survey_date
                                                    ? form.data.field_survey_date.slice(0, 10)
                                                    : ''
                                            }
                                            onChange={(e) =>
                                                setField('field_survey_date', e.target.value)
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="Fiber Provider"
                                        error={form.errors.fiber_provider}
                                        maxLength={SOW_FIELD_LIMITS.fiber_provider}
                                        valueLength={form.data.fiber_provider.length}
                                    >
                                        <Input
                                            value={form.data.fiber_provider}
                                            onChange={(e) =>
                                                setField('fiber_provider', e.target.value)
                                            }
                                            placeholder="e.g. VZB"
                                            maxLength={SOW_FIELD_LIMITS.fiber_provider}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Demarcation"
                                        error={form.errors.demarcation}
                                        maxLength={SOW_FIELD_LIMITS.demarcation}
                                        valueLength={form.data.demarcation.length}
                                    >
                                        <Input
                                            value={form.data.demarcation}
                                            onChange={(e) =>
                                                setField('demarcation', e.target.value)
                                            }
                                            placeholder="e.g. Demarc location / Telco room"
                                            maxLength={SOW_FIELD_LIMITS.demarcation}
                                        />
                                    </FormField>
                                    <FormField
                                        label="PS # (Hashtag)"
                                        error={form.errors.ps_number}
                                        badge={badge('ps_number')}
                                        maxLength={SOW_FIELD_LIMITS.ps_number}
                                        valueLength={form.data.ps_number.length}
                                    >
                                        <Input
                                            value={form.data.ps_number}
                                            onChange={(e) => setField('ps_number', e.target.value)}
                                            maxLength={SOW_FIELD_LIMITS.ps_number}
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    label="Scope of Work Description"
                                    error={form.errors.scope_of_work_description}
                                    maxLength={SOW_FIELD_LIMITS.scope_of_work_description}
                                    valueLength={form.data.scope_of_work_description.length}
                                >
                                    <Textarea
                                        value={form.data.scope_of_work_description}
                                        onChange={(e) =>
                                            setField('scope_of_work_description', e.target.value)
                                        }
                                        rows={3}
                                        placeholder="Detailed description of the scope of work..."
                                        maxLength={SOW_FIELD_LIMITS.scope_of_work_description}
                                    />
                                </FormField>
                                <FormField
                                    label="Site Access Notes"
                                    error={form.errors.site_access_notes}
                                    maxLength={SOW_FIELD_LIMITS.site_access_notes}
                                    valueLength={form.data.site_access_notes.length}
                                >
                                    <Textarea
                                        value={form.data.site_access_notes}
                                        onChange={(e) =>
                                            setField('site_access_notes', e.target.value)
                                        }
                                        rows={2}
                                        placeholder="Gate codes, key locations, or access protocols..."
                                        maxLength={SOW_FIELD_LIMITS.site_access_notes}
                                    />
                                </FormField>

                                <div className="space-y-3 pt-3 border-t border-border">
                                    <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Fiber Provider Contacts (Optional)
                                    </h3>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <FormField
                                            label="Contact Name"
                                            error={form.errors.provider_contact_name}
                                            maxLength={SOW_FIELD_LIMITS.provider_contact_name}
                                            valueLength={form.data.provider_contact_name.length}
                                        >
                                            <Input
                                                value={form.data.provider_contact_name}
                                                onChange={(e) =>
                                                    setField(
                                                        'provider_contact_name',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Name"
                                                maxLength={SOW_FIELD_LIMITS.provider_contact_name}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Contact Phone"
                                            error={form.errors.provider_contact_phone}
                                            maxLength={SOW_FIELD_LIMITS.provider_contact_phone}
                                            valueLength={form.data.provider_contact_phone.length}
                                        >
                                            <Input
                                                value={form.data.provider_contact_phone}
                                                onChange={(e) =>
                                                    setField(
                                                        'provider_contact_phone',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Phone"
                                                maxLength={SOW_FIELD_LIMITS.provider_contact_phone}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Contact Email"
                                            error={form.errors.provider_contact_email}
                                            maxLength={SOW_FIELD_LIMITS.provider_contact_email}
                                            valueLength={form.data.provider_contact_email.length}
                                        >
                                            <Input
                                                type="email"
                                                value={form.data.provider_contact_email}
                                                onChange={(e) =>
                                                    setField(
                                                        'provider_contact_email',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Email"
                                                maxLength={SOW_FIELD_LIMITS.provider_contact_email}
                                            />
                                        </FormField>
                                    </div>
                                </div>

                                <div className="space-y-3 pt-3 border-t border-border">
                                    <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Survey Contractor & Contacts (Optional)
                                    </h3>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <FormField
                                            label="Contractor"
                                            error={form.errors.survey_contractor_name}
                                            maxLength={SOW_FIELD_LIMITS.survey_contractor_name}
                                            valueLength={form.data.survey_contractor_name.length}
                                        >
                                            <Input
                                                value={form.data.survey_contractor_name}
                                                onChange={(e) =>
                                                    setField(
                                                        'survey_contractor_name',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Company name"
                                                maxLength={SOW_FIELD_LIMITS.survey_contractor_name}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Contact Name"
                                            error={form.errors.survey_contact_name}
                                            maxLength={SOW_FIELD_LIMITS.survey_contact_name}
                                            valueLength={form.data.survey_contact_name.length}
                                        >
                                            <Input
                                                value={form.data.survey_contact_name}
                                                onChange={(e) =>
                                                    setField(
                                                        'survey_contact_name',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Contact person"
                                                maxLength={SOW_FIELD_LIMITS.survey_contact_name}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Contact Phone"
                                            error={form.errors.survey_contact_phone}
                                            maxLength={SOW_FIELD_LIMITS.survey_contact_phone}
                                            valueLength={form.data.survey_contact_phone.length}
                                        >
                                            <Input
                                                value={form.data.survey_contact_phone}
                                                onChange={(e) =>
                                                    setField(
                                                        'survey_contact_phone',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Phone"
                                                maxLength={SOW_FIELD_LIMITS.survey_contact_phone}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Contact Email"
                                            error={form.errors.survey_contact_email}
                                            maxLength={SOW_FIELD_LIMITS.survey_contact_email}
                                            valueLength={form.data.survey_contact_email.length}
                                        >
                                            <Input
                                                type="email"
                                                value={form.data.survey_contact_email}
                                                onChange={(e) =>
                                                    setField(
                                                        'survey_contact_email',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Email"
                                                maxLength={SOW_FIELD_LIMITS.survey_contact_email}
                                            />
                                        </FormField>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => router.visit(route(REPORTS_INDEX_ROUTE))}
                        >
                            Cancel
                        </Button>
                        <div className="flex gap-2">
                            {step > 0 && (
                                <Button
                                    key="wizard-back-button"
                                    type="button"
                                    variant="outline"
                                    onClick={() => setStep((prev) => Math.max(0, prev - 1))}
                                >
                                    Back
                                </Button>
                            )}
                            {step === 0 && (
                                <Button
                                    key="step-0-continue-button"
                                    type="button"
                                    onClick={() => {
                                        if (stepValid) setStep(1);
                                    }}
                                    disabled={!stepValid}
                                    className="gap-1.5"
                                >
                                    Continue to Site Details
                                    <ArrowRight className="size-4" />
                                </Button>
                            )}
                            {step === 1 && (
                                <Button
                                    key="step-1-continue-button"
                                    type="button"
                                    onClick={() => {
                                        setStep(2);
                                    }}
                                    className="gap-1.5"
                                >
                                    Continue to Survey Data
                                    <ArrowRight className="size-4" />
                                </Button>
                            )}
                            {step === 2 && (
                                <Button
                                    key="step-2-submit-button"
                                    type="button"
                                    disabled={form.processing}
                                    onClick={handleFinalSubmit}
                                    className="gap-1.5"
                                >
                                    {form.processing ? (
                                        'Creating SOW…'
                                    ) : (
                                        <>
                                            Continue to Photos & Annotations
                                            <ArrowRight className="size-4" />
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {project && (
                <SowMediaLibraryModal
                    isOpen={pickerOpen}
                    onClose={() => setPickerOpen(false)}
                    projectId={project.id}
                    alreadyAttachedIds={[]}
                    singleSelect
                    title="Replace cover photo"
                    ctaLabel="Use this photo"
                    onPick={(asset) => {
                        setCoverAsset(asset);
                        form.setData('cover_media_asset_id', String(asset.id));
                        setIncludePhoto(false);
                        form.setData('include_project_site_photo', false);
                    }}
                />
            )}
        </AppLayout>
    );
}

export default SowCreate;
