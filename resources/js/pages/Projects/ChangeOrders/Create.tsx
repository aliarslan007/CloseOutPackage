import { ChangeOrderItemsEditor } from '@/components/change-orders/change-order-items-editor';
import { ChangeOrderPhotoAttachment, type AttachedPhoto } from '@/components/change-orders/change-order-photo-attachment';
import { ChangeOrderReviewCategorizedItems } from '@/components/change-orders/change-order-review-categorized-items';
import { ChangeOrderStepper } from '@/components/change-orders/change-order-stepper';
import {
    ChangeOrderItem,
    CustomerOption,
    DEFAULT_CHANGE_ORDER_DESCRIPTION_LIMIT,
    DEFAULT_CHANGE_ORDER_ITEM_VENDOR_LIMIT,
    DEFAULT_CHANGE_ORDER_ITEM_DESCRIPTION_LIMIT,
    DEFAULT_CHANGE_ORDER_TITLE_LIMIT,
} from '@/components/change-orders/change-order-types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, getFieldDateBadgeInfo } from '@/components/change-orders/change-order-format';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calendar, Send, Users } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ChangeOrdersCreateProps extends PageProps {
    project: {
        id: number;
        hash_id?: string;
        name: string;
    };
    customers: CustomerOption[];
    /** Backend-enforced title length cap. */
    titleLimit?: number;
    /** Backend-enforced photo cap for a single change order. */
    photoLimit?: number;
    /** Backend-enforced cover note length cap. */
    descriptionLimit?: number;
    /** Backend-enforced vendor count cap per itemized cost line. */
    itemVendorLimit?: number;
    /** Backend-enforced description cap per itemized cost line. */
    itemDescriptionLimit?: number;
}

function getInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

export function ChangeOrdersCreate({
    project,
    customers = [],
    titleLimit = DEFAULT_CHANGE_ORDER_TITLE_LIMIT,
    photoLimit,
    descriptionLimit = DEFAULT_CHANGE_ORDER_DESCRIPTION_LIMIT,
    itemVendorLimit = DEFAULT_CHANGE_ORDER_ITEM_VENDOR_LIMIT,
    itemDescriptionLimit = DEFAULT_CHANGE_ORDER_ITEM_DESCRIPTION_LIMIT,
}: Readonly<ChangeOrdersCreateProps>) {
    const projectIdentifier = project.hash_id || project.id;
    const [currentStep, setCurrentStep] = React.useState<number>(1);
    const [stagedPhotos, setStagedPhotos] = React.useState<AttachedPhoto[]>([]);

    const maxAllowedDate = React.useMemo(() => {
        const d = new Date();
        d.setFullYear(d.getFullYear() + 2);
        return d.toISOString().split('T')[0];
    }, []);

    const form = useForm({
        title: '',
        description: '',
        change_order_date: new Date().toISOString().split('T')[0],
        media_asset_ids: [] as number[],
        items: [
            {
                description: '',
                vendor_name: [],
                category: 'Third-party Service',
                amount: '' as unknown as number,
                is_included: true,
                cost_absorbed_by_solucomp: false,
                sort_order: 0,
            },
        ] as ChangeOrderItem[],
        recipient_ids: [] as number[],
    });

    const handlePhotosChange = (photos: AttachedPhoto[]) => {
        setStagedPhotos(photos);
        const mediaAssetIds = photos
            .map((p) => p.media_asset_id ?? (typeof p.id === 'number' ? p.id : null))
            .filter((id): id is number => typeof id === 'number' && id > 0);
        form.setData('media_asset_ids', mediaAssetIds);
    };

    // Reactive completed steps based on actual filled valid data
    const completedSteps = React.useMemo(() => {
        const done: number[] = [];
        if (form.data.title.trim().length > 0) {
            done.push(1);
        }
        const hasValidItems = form.data.items.some(
            (item) =>
                item.description.trim().length > 0 &&
                Number(item.amount) >= 0 &&
                !Number.isNaN(Number(item.amount))
        );
        if (hasValidItems) {
            done.push(2);
        }
        if (form.data.recipient_ids.length > 0) {
            done.push(3);
        }
        return done;
    }, [form.data.title, form.data.items, form.data.recipient_ids]);

    // Validation per step
    const validateStep = (step: number): boolean => {
        if (step === 1) {
            if (!form.data.title.trim()) {
                toast.error('Please enter a change order title.');
                return false;
            }
            // maxLength covers typing; IME composition and paste can overshoot it.
            if (form.data.title.length > titleLimit) {
                toast.error(`Title must be ${titleLimit} characters or fewer.`);
                return false;
            }
            return true;
        }

        if (step === 2) {
            if (form.data.items.length === 0) {
                toast.error('Please add at least one line item.');
                return false;
            }
            const invalidItem = form.data.items.find(
                (item) =>
                    !item.description.trim() ||
                    Number(item.amount) < 0 ||
                    Number.isNaN(Number(item.amount))
            );
            if (invalidItem) {
                toast.error('Please ensure all items have a description and valid amount.');
                return false;
            }
            // maxLength covers typing; IME composition and paste can overshoot it.
            if (form.data.items.some((item) => item.description.length > itemDescriptionLimit)) {
                toast.error(
                    `Item descriptions must be ${itemDescriptionLimit} characters or fewer.`
                );
                return false;
            }
            return true;
        }

        return true;
    };

    const handleNext = () => {
        if (!validateStep(currentStep)) return;

        if (currentStep < 4) {
            setCurrentStep((prev) => prev + 1);
        } else {
            handleSubmit(false);
        }
    };

    const handleBack = () => {
        if (currentStep > 1) {
            setCurrentStep((prev) => prev - 1);
        }
    };

    const handleSubmit = (isDraftOnly = false) => {
        if (!validateStep(1) || !validateStep(2)) {
            return;
        }

        // Clean items payload
        const cleanedItems = form.data.items.map((item, idx) => ({
            ...item,
            amount: Number.parseFloat(String(item.amount)) || 0,
            is_included: true,
            cost_absorbed_by_solucomp: Boolean(item.cost_absorbed_by_solucomp),
            sort_order: idx,
        }));

        const mediaAssetIds = stagedPhotos
            .map((p) => p.media_asset_id ?? (typeof p.id === 'number' ? p.id : null))
            .filter((id): id is number => typeof id === 'number' && id > 0);

        form.transform((data) => ({
            ...data,
            items: cleanedItems,
            media_asset_ids: mediaAssetIds,
        }));

        form.post(route('projects.change-orders.store', projectIdentifier), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isDraftOnly
                        ? 'Change order saved as draft.'
                        : 'Change order created successfully.'
                );
            },
        });
    };

    // Item management handlers
    const addItem = () => {
        form.setData('items', [
            ...form.data.items,
            {
                description: '',
                vendor_name: [],
                category: 'Materials',
                amount: '' as unknown as number,
                is_included: true,
                cost_absorbed_by_solucomp: false,
                sort_order: form.data.items.length,
            },
        ]);
    };

    const removeItem = (index: number) => {
        if (form.data.items.length <= 1) {
            toast.error('A change order must have at least one line item.');
            return;
        }
        const updated = form.data.items.filter((_, i) => i !== index);
        form.setData('items', updated);
    };

    const updateItem = (index: number, field: keyof ChangeOrderItem, value: unknown) => {
        const updated = [...form.data.items];
        updated[index] = { ...updated[index], [field]: value } as ChangeOrderItem;
        form.setData('items', updated);
    };

    // Recipient selection handlers
    const toggleRecipient = (userId: number) => {
        const current = form.data.recipient_ids;
        if (current.includes(userId)) {
            form.setData(
                'recipient_ids',
                current.filter((id) => id !== userId)
            );
        } else {
            form.setData('recipient_ids', [...current, userId]);
        }
    };

    const toggleAllRecipients = () => {
        if (form.data.recipient_ids.length === customers.length) {
            form.setData('recipient_ids', []);
        } else {
            form.setData(
                'recipient_ids',
                customers.map((c) => c.id)
            );
        }
    };

    return (
        <AppLayout
            title="Create Change Order"
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', projectIdentifier) },
                { label: 'Change Orders', href: route('projects.change-orders.index', projectIdentifier) },
                { label: 'Create' },
            ]}
        >
            <Head title={`Create Change Order - ${project.name}`} />

            <div className="space-y-4">
                {/* 2-Column Stepper & Form Layout */}
                <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                    {/* Left Column: Stepper */}
                    <div className="md:col-span-4 lg:col-span-3">
                        <div className="sticky top-20 rounded-xl border border-border/80 bg-card p-5 shadow-xs">
                            <ChangeOrderStepper
                                currentStep={currentStep}
                                onStepClick={(s) => {
                                    if (s <= currentStep || completedSteps.includes(s)) {
                                        setCurrentStep(s);
                                    }
                                }}
                                completedSteps={completedSteps}
                            />
                        </div>
                    </div>

                    {/* Right Column: Step Content Card */}
                    <div className="md:col-span-8 lg:col-span-9 space-y-6">
                        <div className="rounded-2xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs">
                            {/* STEP 1: Details */}
                            {currentStep === 1 && (
                                <div className="space-y-6">
                                    <div className="border-b border-border/70 pb-4">
                                        <h2 className="text-lg font-bold text-foreground">
                                            Change Order Details
                                        </h2>
                                        <p className="text-xs text-muted-foreground mt-0.5">
                                            Provide the primary title and background context for
                                            this request.
                                        </p>
                                    </div>

                                    {/* Title & Date of Occurrence Row */}
                                    <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
                                        {/* Title Field */}
                                        <div className="md:col-span-8 space-y-2">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor="co-title"
                                                    className="text-xs font-bold text-foreground"
                                                >
                                                    Title <span className="text-destructive">*</span>
                                                </Label>
                                                <span className="text-[11px] text-muted-foreground tabular-nums">
                                                    {form.data.title.length}/{titleLimit}
                                                </span>
                                            </div>
                                            <Input
                                                id="co-title"
                                                type="text"
                                                placeholder="Enter change order title (e.g. Site Obstruction – Tree Removal)"
                                                value={form.data.title}
                                                onChange={(e) => form.setData('title', e.target.value)}
                                                maxLength={titleLimit}
                                                className="h-11 text-sm rounded-xl border-border bg-background"
                                                autoFocus
                                            />
                                            {form.errors.title && (
                                                <p className="text-xs text-destructive">
                                                    {form.errors.title}
                                                </p>
                                            )}
                                        </div>

                                        {/* Change Order Date of Occurrence */}
                                        <div className="md:col-span-4 space-y-2">
                                            <div className="flex items-center justify-between gap-1">
                                                <Label
                                                    htmlFor="co-date"
                                                    className="text-xs font-bold text-foreground flex items-center gap-1.5"
                                                >
                                                    <Calendar className="size-3.5 text-primary" />
                                                    <span>Date of Occurrence</span>
                                                </Label>
                                                {(() => {
                                                    const badge = getFieldDateBadgeInfo(form.data.change_order_date);
                                                    if (badge.variant === 'future') {
                                                        return (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                                                {badge.label}
                                                            </span>
                                                        );
                                                    }
                                                    if (badge.variant === 'today') {
                                                        return (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                                {badge.label}
                                                            </span>
                                                        );
                                                    }
                                                    if (badge.variant === 'past') {
                                                        return (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-muted text-muted-foreground border border-border">
                                                                {badge.label}
                                                            </span>
                                                        );
                                                    }
                                                    return null;
                                                })()}
                                            </div>
                                            <Input
                                                id="co-date"
                                                type="date"
                                                min="2000-01-01"
                                                max={maxAllowedDate}
                                                value={form.data.change_order_date ?? ''}
                                                onChange={(e) => form.setData('change_order_date', e.target.value)}
                                                className="h-11 text-sm rounded-xl border-border bg-background"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Date this change order occurred or is scheduled on-site.
                                            </p>
                                            {form.errors.change_order_date && (
                                                <p className="text-xs text-destructive">
                                                    {form.errors.change_order_date}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Description / Cover Note Field */}
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label
                                                htmlFor="co-desc"
                                                className="text-xs font-bold text-foreground"
                                            >
                                                Description / Cover Note
                                            </Label>
                                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                                {form.data.description.length}/{descriptionLimit}
                                            </span>
                                        </div>
                                        <Textarea
                                            id="co-desc"
                                            rows={6}
                                            maxLength={descriptionLimit}
                                            placeholder="Provide overall context or additional notes for the customer..."
                                            value={form.data.description}
                                            onChange={(e) =>
                                                form.setData('description', e.target.value)
                                            }
                                            className="text-sm rounded-xl border-border bg-background resize-none"
                                        />
                                        {form.errors.description && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.description}
                                            </p>
                                        )}
                                    </div>

                                    {/* Supporting Photos & Documentation */}
                                    <div className="border-t border-border/60 pt-5">
                                        <ChangeOrderPhotoAttachment
                                            projectId={project.id}
                                            projectName={project.name}
                                            photos={stagedPhotos}
                                            onChange={handlePhotosChange}
                                            maxPhotos={photoLimit}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* STEP 2: Items */}
                            {currentStep === 2 && (
                                <ChangeOrderItemsEditor
                                    items={form.data.items}
                                    onAddItem={addItem}
                                    onRemoveItem={removeItem}
                                    onUpdateItem={updateItem}
                                    itemVendorLimit={itemVendorLimit}
                                    itemDescriptionLimit={itemDescriptionLimit}
                                />
                            )}                            {/* STEP 3: Verizon Customers */}
                            {currentStep === 3 && (
                                <div className="space-y-6">
                                    <div className="flex items-center justify-between border-b border-border/70 pb-4">
                                        <div>
                                            <h2 className="text-lg font-bold text-foreground">
                                                Select Verizon Customers
                                            </h2>
                                            <p className="text-xs text-muted-foreground mt-0.5">
                                                Select Verizon customer contacts who will receive the approval request and email notifications.
                                            </p>
                                        </div>

                                        {customers.length > 0 && (
                                            <button
                                                type="button"
                                                onClick={toggleAllRecipients}
                                                className="text-xs font-semibold text-primary hover:underline cursor-pointer"
                                            >
                                                {form.data.recipient_ids.length === customers.length
                                                    ? 'Deselect All'
                                                    : 'Select All'}
                                            </button>
                                        )}
                                    </div>

                                    {customers.length === 0 ? (
                                        <div className="py-10 text-center text-muted-foreground text-xs">
                                            <Users className="size-8 mx-auto mb-2 text-muted-foreground/60" />
                                            <p className="font-semibold text-foreground">
                                                No customer contacts assigned yet
                                            </p>
                                            <p className="max-w-xs mx-auto mt-1">
                                                You can still save this change order as a draft and
                                                assign customers later before sending.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            {customers.map((customer) => {
                                                const isSelected = form.data.recipient_ids.includes(
                                                    customer.id
                                                );

                                                return (
                                                    <button
                                                        key={customer.id}
                                                        type="button"
                                                        onClick={() => toggleRecipient(customer.id)}
                                                        className={cn(
                                                            'flex items-center gap-3 p-3.5 rounded-xl border transition-all cursor-pointer text-left w-full',
                                                            isSelected
                                                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                                                : 'border-border bg-card hover:bg-muted/30'
                                                        )}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isSelected}
                                                            onChange={() => {}}
                                                            className="size-4 rounded border-border text-primary focus:ring-primary/20 cursor-pointer shrink-0"
                                                        />

                                                        {customer.profile_photo_url ? (
                                                            <img
                                                                src={customer.profile_photo_url}
                                                                alt={customer.name}
                                                                className="size-8 rounded-full object-cover border border-border shrink-0 aspect-square"
                                                            />
                                                        ) : (
                                                            <div className="size-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0">
                                                                {getInitials(customer.name)}
                                                            </div>
                                                        )}

                                                        <div className="flex flex-col min-w-0 flex-1">
                                                            <span className="text-xs font-bold text-foreground truncate">
                                                                {customer.name}
                                                            </span>
                                                            <span className="text-[11px] text-muted-foreground truncate">
                                                                {customer.email}
                                                            </span>
                                                        </div>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* STEP 4: Review & Save */}
                            {currentStep === 4 && (
                                <div className="space-y-6">
                                    <div className="border-b border-border/70 pb-4">
                                        <h2 className="text-lg font-bold text-foreground">
                                            Review & Save
                                        </h2>
                                        <p className="text-xs text-muted-foreground mt-0.5">
                                            Double check your change order details and assigned customers before saving.
                                        </p>
                                    </div>

                                    {/* Summary Card */}
                                    <div className="rounded-xl bg-muted/20 border border-border p-5 space-y-4 text-xs">
                                        <div>
                                            <span className="text-[11px] uppercase tracking-wider font-bold text-muted-foreground">
                                                Change Order Title
                                            </span>
                                            <h3 className="text-base font-bold text-foreground mt-0.5">
                                                {form.data.title}
                                            </h3>
                                            {form.data.change_order_date && (
                                                <div className="mt-1.5 flex items-center gap-2">
                                                    <span className="text-[11px] font-semibold text-muted-foreground flex items-center gap-1">
                                                        <Calendar className="size-3.5 text-primary" />
                                                        <span>Date of Occurrence:</span>
                                                    </span>
                                                    <span className="font-bold text-foreground text-xs">
                                                        {formatDate(form.data.change_order_date)}
                                                    </span>
                                                    {(() => {
                                                        const badge = getFieldDateBadgeInfo(form.data.change_order_date);
                                                        if (badge.variant === 'future') {
                                                            return (
                                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                                                    {badge.label}
                                                                </span>
                                                            );
                                                        }
                                                        if (badge.variant === 'today') {
                                                            return (
                                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                                    {badge.label}
                                                                </span>
                                                            );
                                                        }
                                                        if (badge.variant === 'past') {
                                                            return (
                                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-muted text-muted-foreground border border-border">
                                                                    {badge.label}
                                                                </span>
                                                            );
                                                        }
                                                        return null;
                                                    })()}
                                                </div>
                                            )}
                                            {form.data.description && (
                                                <p className="mt-2 max-h-72 overflow-y-auto overscroll-contain whitespace-pre-wrap break-words text-muted-foreground [overflow-wrap:anywhere]">
                                                    {form.data.description}
                                                </p>
                                            )}
                                        </div>

                                        <div className="border-t border-border/60 pt-4">
                                            <ChangeOrderReviewCategorizedItems items={form.data.items} />
                                        </div>

                                        <div className="border-t border-border/60 pt-4">
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-[11px] uppercase tracking-wider font-bold text-muted-foreground">
                                                    Assigned Verizon Customers ({form.data.recipient_ids.length})
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => setCurrentStep(3)}
                                                    className="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                                                >
                                                    Change Selection
                                                </button>
                                            </div>
                                            <div>
                                                {form.data.recipient_ids.length === 0 ? (
                                                    <div className="p-3 rounded-lg border border-dashed border-border bg-card text-muted-foreground text-xs italic">
                                                        No customers selected. You can save as draft and select customers before sending.
                                                    </div>
                                                ) : (
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                        {customers
                                                            .filter((c) =>
                                                                form.data.recipient_ids.includes(c.id)
                                                            )
                                                            .map((c) => (
                                                                <div
                                                                    key={c.id}
                                                                    className="flex items-center gap-2.5 p-2.5 rounded-lg border border-border/80 bg-card text-xs"
                                                                >
                                                                    {c.profile_photo_url ? (
                                                                        <img
                                                                            src={c.profile_photo_url}
                                                                            alt={c.name}
                                                                            className="size-7 rounded-full object-cover border border-border shrink-0 aspect-square"
                                                                        />
                                                                    ) : (
                                                                        <div className="size-7 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center shrink-0">
                                                                            {getInitials(c.name)}
                                                                        </div>
                                                                    )}
                                                                    <div className="flex flex-col min-w-0 flex-1">
                                                                        <span className="font-semibold text-foreground truncate">
                                                                            {c.name}
                                                                        </span>
                                                                        <span className="text-[11px] text-muted-foreground truncate">
                                                                            {c.email}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {stagedPhotos.length > 0 && (
                                            <div className="border-t border-border/60 pt-4">
                                                <div className="flex items-center justify-between mb-2">
                                                    <span className="text-[11px] uppercase tracking-wider font-bold text-muted-foreground">
                                                        Supporting Photos ({stagedPhotos.length})
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => setCurrentStep(1)}
                                                        className="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                                                    >
                                                        Edit Photos
                                                    </button>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                    {stagedPhotos.map((photo) => (
                                                        <div
                                                            key={photo.id}
                                                            className="flex items-center gap-2 rounded-lg border border-border/80 bg-card p-1.5 overflow-hidden"
                                                        >
                                                            <img
                                                                src={photo.url}
                                                                alt={photo.original_name}
                                                                className="size-8 rounded object-cover shrink-0"
                                                            />
                                                            <span className="truncate text-[11px] font-medium text-foreground">
                                                                {photo.original_name}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Bottom Step Actions */}
                            <div className="mt-8 flex items-center justify-between border-t border-border/70 pt-6">
                                <div>
                                    {currentStep === 1 ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                            className="rounded-xl text-xs"
                                        >
                                            <Link
                                                href={route(
                                                    'projects.change-orders.index',
                                                    projectIdentifier
                                                )}
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleBack}
                                            className="rounded-xl text-xs gap-1.5"
                                        >
                                            <ArrowLeft className="size-3.5" />
                                            <span>Back</span>
                                        </Button>
                                    )}
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => handleSubmit(true)}
                                        disabled={form.processing}
                                        className="rounded-xl text-xs font-semibold"
                                    >
                                        Save as Draft
                                    </Button>

                                    <Button
                                        type="button"
                                        onClick={handleNext}
                                        disabled={form.processing}
                                        className="rounded-xl text-xs font-bold gap-2 px-6 bg-foreground text-background hover:bg-foreground/90"
                                    >
                                        {currentStep === 4 ? (
                                            <>
                                                <Send className="size-3.5" />
                                                <span>Save & Continue</span>
                                            </>
                                        ) : (
                                            <span>Next</span>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default ChangeOrdersCreate;
