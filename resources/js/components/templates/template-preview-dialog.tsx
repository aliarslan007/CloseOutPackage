import * as React from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { TemplateDetail } from '@/components/templates/template-types';
import { TemplatePreviewPanel } from '@/components/templates/template-preview-panel';

export interface TemplatePreviewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    template: TemplateDetail;
}

export function TemplatePreviewDialog({
    open,
    onOpenChange,
    template,
}: Readonly<TemplatePreviewDialogProps>) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[80vh] overflow-y-auto no-scrollbar sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Template Preview — {template.name}</DialogTitle>
                    <DialogDescription>
                        Read-only preview of how this template appears during package creation.
                    </DialogDescription>
                </DialogHeader>

                <TemplatePreviewPanel template={template} />
            </DialogContent>
        </Dialog>
    );
}
