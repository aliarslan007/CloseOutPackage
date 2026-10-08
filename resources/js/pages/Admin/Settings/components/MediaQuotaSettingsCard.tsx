import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/shared/form-field';
import { Input } from '@/components/ui/input';
import { HardDrive, Shield, Briefcase, Camera, Server } from 'lucide-react';

export interface MediaQuotaSettingsCardProps {
    mediaQuotas?: Record<string, number | null>;
    mediaConfig?: {
        max_file_mb?: number;
        chunk_size_mb?: number;
        allowed_mimes?: string[];
    };
    activeTab: string;
}

interface MediaQuotaForm {
    admin_quota_gb: string;
    manager_quota_gb: string;
    supervisor_quota_gb: string;
}

const bytesToGb = (bytes: number | null | undefined): string => {
    if (!bytes || bytes <= 0) return '';
    return String(Math.round((bytes / 1073741824) * 10) / 10);
};

export function MediaQuotaSettingsCard({
    mediaQuotas,
    mediaConfig,
    activeTab,
}: Readonly<MediaQuotaSettingsCardProps>) {
    const form = useForm<MediaQuotaForm>({
        admin_quota_gb: bytesToGb(mediaQuotas?.['media.quota.admin_bytes']),
        manager_quota_gb: bytesToGb(mediaQuotas?.['media.quota.manager_bytes']),
        supervisor_quota_gb: bytesToGb(mediaQuotas?.['media.quota.supervisor_bytes']),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('admin.settings.media-quotas.update') + `?tab=${activeTab}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-6">
            <Card className="border-border shadow-xs">
                <CardHeader className="pb-4">
                    <div className="flex items-center gap-2.5 text-primary mb-1">
                        <HardDrive className="size-5 text-indigo-600 dark:text-indigo-400" />
                        <CardTitle className="text-sm font-semibold">Role-Based Storage Quotas</CardTitle>
                    </div>
                    <CardDescription className="text-xs">
                        Configure storage capacity limits enforced across each role. Once exceeded, further uploads are paused until storage is cleared or limits are increased. Leave blank for unlimited.
                    </CardDescription>
                </CardHeader>
                <form onSubmit={handleSubmit} noValidate>
                    <CardContent className="space-y-6">
                        {/* 3 KPI Quota Cards */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {/* Administrator */}
                            <div className="rounded-xl border border-border/80 bg-muted/20 p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                                        <Shield className="size-4 text-primary shrink-0" />
                                        <span>Administrator</span>
                                    </div>
                                    <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                                        Admin
                                    </span>
                                </div>
                                <FormField
                                    label="Capacity Limit (GB)"
                                    htmlFor="quota-admin"
                                    error={form.errors.admin_quota_gb}
                                >
                                    <Input
                                        id="quota-admin"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100000"
                                        value={form.data.admin_quota_gb}
                                        onChange={(e) => form.setData('admin_quota_gb', e.target.value)}
                                        placeholder="Unlimited"
                                        className="h-8 text-xs font-medium"
                                        error={Boolean(form.errors.admin_quota_gb)}
                                    />
                                    <span className="text-[11px] text-muted-foreground pt-1 block">
                                        {form.data.admin_quota_gb ? `${form.data.admin_quota_gb} GB assigned` : 'Unlimited storage (recommended)'}
                                    </span>
                                </FormField>
                            </div>

                            {/* Project Manager */}
                            <div className="rounded-xl border border-border/80 bg-muted/20 p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                                        <Briefcase className="size-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                                        <span>Project Manager</span>
                                    </div>
                                    <span className="rounded bg-indigo-500/10 px-1.5 py-0.5 text-[10px] font-medium text-indigo-600 dark:text-indigo-400">
                                        Manager
                                    </span>
                                </div>
                                <FormField
                                    label="Capacity Limit (GB)"
                                    htmlFor="quota-manager"
                                    error={form.errors.manager_quota_gb}
                                >
                                    <Input
                                        id="quota-manager"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100000"
                                        value={form.data.manager_quota_gb}
                                        onChange={(e) => form.setData('manager_quota_gb', e.target.value)}
                                        placeholder="e.g. 10"
                                        className="h-8 text-xs font-medium"
                                        error={Boolean(form.errors.manager_quota_gb)}
                                    />
                                    <span className="text-[11px] text-muted-foreground pt-1 block">
                                        {form.data.manager_quota_gb ? `${form.data.manager_quota_gb} GB assigned` : 'Default: 10 GB'}
                                    </span>
                                </FormField>
                            </div>

                            {/* Field Supervisor */}
                            <div className="rounded-xl border border-border/80 bg-muted/20 p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                                        <Camera className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                        <span>Field Supervisor</span>
                                    </div>
                                    <span className="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                        Supervisor
                                    </span>
                                </div>
                                <FormField
                                    label="Capacity Limit (GB)"
                                    htmlFor="quota-supervisor"
                                    error={form.errors.supervisor_quota_gb}
                                >
                                    <Input
                                        id="quota-supervisor"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100000"
                                        value={form.data.supervisor_quota_gb}
                                        onChange={(e) => form.setData('supervisor_quota_gb', e.target.value)}
                                        placeholder="e.g. 5"
                                        className="h-8 text-xs font-medium"
                                        error={Boolean(form.errors.supervisor_quota_gb)}
                                    />
                                    <span className="text-[11px] text-muted-foreground pt-1 block">
                                        {form.data.supervisor_quota_gb ? `${form.data.supervisor_quota_gb} GB assigned` : 'Default: 5 GB'}
                                    </span>
                                </FormField>
                            </div>
                        </div>

                        {/* System Specifications Bar */}
                        <div className="rounded-xl border border-border/70 bg-card p-4 space-y-2.5">
                            <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                                <Server className="size-4 text-primary shrink-0" />
                                <span>Platform Upload Infrastructure</span>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs pt-1">
                                <div className="rounded-lg bg-muted/30 p-2.5">
                                    <p className="text-[11px] text-muted-foreground">Max File Size</p>
                                    <p className="font-semibold text-foreground text-xs pt-0.5">{mediaConfig?.max_file_mb ?? 50} MB per photo</p>
                                </div>
                                <div className="rounded-lg bg-muted/30 p-2.5">
                                    <p className="text-[11px] text-muted-foreground">Chunk Stream Rate</p>
                                    <p className="font-semibold text-foreground text-xs pt-0.5">{mediaConfig?.chunk_size_mb ?? 5} MB streaming parts</p>
                                </div>
                                <div className="rounded-lg bg-muted/30 p-2.5">
                                    <p className="text-[11px] text-muted-foreground">Supported Formats</p>
                                    <p className="font-semibold text-foreground text-xs pt-0.5">JPEG, PNG, WebP, HEIC</p>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end pt-1">
                            <Button type="submit" loading={form.processing} className="px-5">
                                Save Storage Quotas
                            </Button>
                        </div>
                    </CardContent>
                </form>
            </Card>
        </div>
    );
}
