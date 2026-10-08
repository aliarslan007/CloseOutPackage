import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ProjectShowDetail } from '@/components/projects/project-show-types';
import { AlertTriangle } from 'lucide-react';

export interface ProjectIssuesCardProps {
    project: ProjectShowDetail;
}

export function ProjectIssuesCard({ project }: Readonly<ProjectIssuesCardProps>) {
    const recentIssues = [...project.issues]
        .sort((a, b) => b.created_at.localeCompare(a.created_at))
        .slice(0, 3);

    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center justify-between gap-2 text-sm font-bold">
                    <span className="flex items-center gap-2">
                        <AlertTriangle className="size-4 text-primary" /> What needs attention
                    </span>
                    <Button asChild variant="ghost" size="sm" className="h-7 text-[11px] px-2">
                        <Link href={route('projects.issues.index', project.hash_id || project.id)}>View all</Link>
                    </Button>
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-3 pt-2">
                {recentIssues.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-center text-xs text-muted-foreground">
                        No issues recorded — all clear.
                    </p>
                ) : (
                    recentIssues.map((issue) => (
                        <div key={issue.id} className="rounded-lg bg-muted/20 px-3 py-2">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-[11px] font-bold text-foreground capitalize">
                                    {issue.type.replace('_', ' ')}
                                </span>
                                {issue.resolved_at ? (
                                    <Badge
                                        variant="outline"
                                        className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px]"
                                    >
                                        Resolved
                                    </Badge>
                                ) : (
                                    <Badge variant="warning" className="text-[10px]">
                                        Open
                                    </Badge>
                                )}
                            </div>
                            <p className="text-[11px] text-muted-foreground line-clamp-2">
                                {issue.description}
                            </p>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
