import * as React from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { FolderOpen } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface EmptyCardProps {
    title: string;
    description: string;
    icon?: React.ReactNode;
    actionLabel?: string;
    onAction?: () => void;
    className?: string;
}

export function EmptyCard({
    title,
    description,
    icon = <FolderOpen className="size-10 text-muted-foreground" />,
    actionLabel,
    onAction,
    className,
}: EmptyCardProps) {
    return (
        <Card className={cn('border-dashed shadow-none', className)}>
            <CardContent className="flex flex-col items-center justify-center p-8 text-center space-y-4">
                <div className="flex size-16 items-center justify-center rounded-full bg-muted/50">
                    {icon}
                </div>
                <div className="space-y-1 max-w-sm">
                    <h3 className="text-lg font-semibold tracking-tight">{title}</h3>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </div>
                {actionLabel && onAction && (
                    <Button onClick={onAction} size="sm">
                        {actionLabel}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

export default EmptyCard;
