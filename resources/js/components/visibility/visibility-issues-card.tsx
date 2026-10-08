import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ISSUE_TYPE_LABELS, IssueTypeValue } from '@/components/projects/issue-list';
import { VisibilityIssuesPage } from '@/components/visibility/visibility-project-types';
import { AlertTriangle, CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-react';

export interface VisibilityIssuesCardProps {
    issuesPage: VisibilityIssuesPage;
    /** Project id or hash id used in the visibility route. */
    projectKey: number | string | undefined;
}

export function VisibilityIssuesCard({ issuesPage, projectKey }: Readonly<VisibilityIssuesCardProps>) {
    const issues = issuesPage.data;
    const currentPage = issuesPage.current_page;
    const lastPage = issuesPage.last_page;
    const showPager = lastPage > 1;

    const pageHref = (page: number) =>
        route('manager.visibility.project', {
            project: projectKey,
            issues_page: page,
        });

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <AlertTriangle className="size-4 text-primary" /> Issues on record
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {issues.length === 0 ? (
                    <p className="text-xs text-muted-foreground">No issues recorded — all clear.</p>
                ) : (
                    issues.map((issue) => (
                        <div key={issue.id} className="rounded-lg bg-muted/20 px-3 py-2 space-y-1">
                            <div className="flex items-center justify-between gap-2">
                                <Badge variant="outline" className="capitalize text-[10px]">
                                    {ISSUE_TYPE_LABELS[issue.type as IssueTypeValue] ?? issue.type}
                                </Badge>
                                {issue.resolved_at ? (
                                    <Badge
                                        variant="outline"
                                        className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px]"
                                    >
                                        <CheckCircle2 className="size-3" /> Resolved
                                    </Badge>
                                ) : (
                                    <Badge variant="warning" className="text-[10px]">
                                        Open
                                    </Badge>
                                )}
                            </div>
                            {/* Same 3,000-char report as the detail view. Clamped
                                without a toggle: these rows are a list, so per-row
                                expand state would need a component per issue. */}
                            <p className="text-[11px] text-foreground leading-snug line-clamp-3">
                                {issue.description}
                            </p>
                            {issue.verizon_guidance && (
                                <p className="text-[10px] text-muted-foreground line-clamp-2">
                                    Verizon guidance: {issue.verizon_guidance}
                                </p>
                            )}
                            <p className="text-[10px] text-muted-foreground">
                                {new Date(issue.created_at).toLocaleString()}
                            </p>
                        </div>
                    ))
                )}

                {showPager && (
                    <div className="flex items-center justify-between border-t border-border/60 pt-2 text-[10px] text-muted-foreground">
                        <span>
                            Page {currentPage} of {lastPage}
                        </span>
                        <div className="flex items-center gap-1">
                            {currentPage > 1 ? (
                                <Link
                                    href={pageHref(currentPage - 1)}
                                    preserveScroll
                                    className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 font-semibold text-foreground hover:bg-muted"
                                >
                                    <ChevronLeft className="size-3" /> Prev
                                </Link>
                            ) : (
                                <span className="inline-flex cursor-not-allowed items-center gap-1 rounded-md border border-border/60 px-2 py-1 opacity-50">
                                    <ChevronLeft className="size-3" /> Prev
                                </span>
                            )}

                            {currentPage < lastPage ? (
                                <Link
                                    href={pageHref(currentPage + 1)}
                                    preserveScroll
                                    className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 font-semibold text-foreground hover:bg-muted"
                                >
                                    Next <ChevronRight className="size-3" />
                                </Link>
                            ) : (
                                <span className="inline-flex cursor-not-allowed items-center gap-1 rounded-md border border-border/60 px-2 py-1 opacity-50">
                                    Next <ChevronRight className="size-3" />
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
