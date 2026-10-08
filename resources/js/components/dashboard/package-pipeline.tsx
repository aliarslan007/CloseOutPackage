import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PackageCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface PackagePipelineProps {
    totalPackages: number;
    packagesDraft: number;
    packagesReadyToSubmit: number;
    pendingApprovals: number;
    packagesNeedingRework: number;
    packagesRejected: number;
    packagesWithFlaggedPhotos: number;
    packagesApproved: number;
}

interface Stage {
    key: string;
    label: string;
    count: number;
    href: string;
    accentClass: string;
    countClass: string;
    sublabel?: string;
}

const packagesLink = (status: string) => route('dashboard.packages', { status });

function getPackageDescription(total: number): string {
    if (total === 1) {
        return 'Track the status of 1 close-out package across your projects.';
    }
    if (total > 1) {
        return `Track the status of ${total} close-out packages across your projects.`;
    }
    return 'Track the status of close-out packages across your projects.';
}

export function PackagePipeline({
    totalPackages,
    packagesDraft,
    packagesReadyToSubmit,
    pendingApprovals,
    packagesNeedingRework,
    packagesRejected,
    packagesWithFlaggedPhotos,
    packagesApproved,
}: Readonly<PackagePipelineProps>) {
    const stages: Stage[] = [
        {
            key: 'draft',
            label: 'Draft',
            count: packagesDraft,
            href: packagesLink('draft'),
            accentClass: 'from-slate-400 to-slate-500',
            countClass: 'text-muted-foreground',
            sublabel: `${packagesReadyToSubmit} ready to submit`,
        },
        {
            key: 'ready_to_submit',
            label: 'In preparation',
            count: packagesReadyToSubmit,
            href: packagesLink('ready_to_submit'),
            accentClass: 'from-sky-500 to-blue-600',
            countClass: 'text-blue-600 dark:text-blue-400',
        },
        {
            key: 'awaiting_review',
            label: 'Under review',
            count: pendingApprovals,
            href: packagesLink('awaiting_review'),
            accentClass: 'from-amber-400 to-amber-600',
            countClass: 'text-amber-600 dark:text-amber-400',
        },
        {
            key: 'needs_rework',
            label: 'Needs attention',
            count: packagesNeedingRework,
            href: packagesLink('needs_rework'),
            accentClass: 'from-rose-500 to-red-600',
            countClass: 'text-destructive',
            sublabel: `${packagesRejected} rejected · ${packagesWithFlaggedPhotos} flagged`,
        },
        {
            key: 'approved',
            label: 'Fully approved',
            count: packagesApproved,
            href: packagesLink('approved'),
            accentClass: 'from-emerald-500 to-green-600',
            countClass: 'text-emerald-600 dark:text-emerald-400',
        },
    ];

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <PackageCheck className="size-4 text-primary" />
                    Project Deliverables
                </CardTitle>
                <CardDescription>
                    {getPackageDescription(totalPackages)}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    {stages.map((stage) => (
                        <Link
                            key={stage.key}
                            href={stage.href}
                            className="group relative flex flex-col gap-2 overflow-hidden rounded-xl border border-border/60 bg-muted/20 p-3 transition-all hover:border-border hover:bg-muted/40 hover:shadow-sm"
                        >
                            <span
                                className={cn(
                                    'absolute inset-x-0 top-0 h-1 bg-gradient-to-r',
                                    stage.accentClass
                                )}
                            />
                            <span
                                className={cn(
                                    'pt-1 text-2xl font-bold tabular-nums tracking-tight',
                                    stage.countClass
                                )}
                            >
                                {stage.count}
                            </span>
                            <span className="text-xs font-semibold leading-tight">
                                {stage.label}
                            </span>
                            {stage.sublabel && (
                                <span className="text-[10px] leading-tight text-muted-foreground">
                                    {stage.sublabel}
                                </span>
                            )}
                        </Link>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default PackagePipeline;
