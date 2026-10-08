import * as React from 'react';

export interface MomentumPoint {
    date: string;
    submissions: number;
    approvals: number;
}

export function MomentumChart({ points }: { points: MomentumPoint[] }) {
    const totalSubmitted = points.reduce((sum, point) => sum + point.submissions, 0);
    const totalApproved = points.reduce((sum, point) => sum + point.approvals, 0);
    const max = Math.max(1, ...points.flatMap((point) => [point.submissions, point.approvals]));

    if (totalSubmitted === 0 && totalApproved === 0) {
        return (
            <p className="text-xs text-muted-foreground">
                No package submissions or approvals in the last 30 days.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex h-32 items-end gap-px">
                {points.map((point) => {
                    const submissionHeight = point.submissions > 0 ? (point.submissions / max) * 100 : 0;
                    const approvalHeight = point.approvals > 0 ? (point.approvals / max) * 100 : 0;

                    return (
                        <div
                            key={point.date}
                            className="flex flex-1 items-end justify-center gap-px"
                            title={`${point.date}: ${point.submissions} submitted · ${point.approvals} approved`}
                        >
                            <div
                                className="w-1.5 rounded-t bg-amber-500/80 transition-colors hover:bg-amber-500"
                                style={{ height: `${submissionHeight}%` }}
                            />
                            <div
                                className="w-1.5 rounded-t bg-emerald-500/80 transition-colors hover:bg-emerald-500"
                                style={{ height: `${approvalHeight}%` }}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-amber-500" />
                    {totalSubmitted} submitted
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-emerald-500" />
                    {totalApproved} approved
                </span>
            </div>
        </div>
    );
}

export default MomentumChart;
