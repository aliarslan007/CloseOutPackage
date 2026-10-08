import * as React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Camera } from 'lucide-react';

export interface VisibilitySitePhotoCardProps {
    sitePhotoUrl?: string | null;
}

export function VisibilitySitePhotoCard({ sitePhotoUrl }: Readonly<VisibilitySitePhotoCardProps>) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <Camera className="size-4 text-primary" /> Site Photo
                </CardTitle>
            </CardHeader>
            <CardContent>
                {sitePhotoUrl ? (
                    <img
                        src={sitePhotoUrl}
                        alt="Project site"
                        className="max-h-72 w-full rounded-lg object-cover"
                    />
                ) : (
                    <p className="text-xs text-muted-foreground">
                        No site photo uploaded.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
