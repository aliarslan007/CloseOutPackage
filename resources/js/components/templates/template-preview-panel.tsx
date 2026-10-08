import { Badge } from '@/components/ui/badge';
import {
    STAGE_LABELS,
    TemplateDetail,
} from '@/components/templates/template-types';
import { Camera, ListChecks } from 'lucide-react';

/**
 * Read-only rendering of a template exactly as field users will experience it.
 * Shared by the builder's live side panel and the Index page's preview dialog.
 */
export function TemplatePreviewPanel({
    template,
}: Readonly<{ template: TemplateDetail }>) {
    return (
        <div className="space-y-4">
            {template.sections.map((section) => (
                <div key={section.id} className="rounded-xl border border-border/80 overflow-hidden">
                    <div className="bg-muted/40 px-3 py-2 border-b border-border/60">
                        <div className="flex items-center justify-between">
                            <h4 className="text-xs font-bold text-foreground">
                                {STAGE_LABELS[section.stage]}
                            </h4>
                            <span className="text-[10px] text-muted-foreground">
                                {section.categories.length}{' '}
                                {section.categories.length === 1 ? 'group' : 'groups'}
                            </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-0.5">
                            {section.heading}
                        </p>
                    </div>
                    <div className="space-y-1.5 p-3">
                        {section.categories.length === 0 ? (
                            <p className="text-[11px] text-muted-foreground">
                                No photo groups yet.
                            </p>
                        ) : (
                            section.categories.map((category) => (
                                <div
                                    key={category.id}
                                    className="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                                >
                                    <Camera className="size-3.5 text-primary shrink-0" />
                                    <span className="text-[11px] text-foreground flex-1">
                                        {category.name}
                                    </span>
                                    {category.is_required && (
                                        <Badge variant="warning" className="text-[9px]">
                                            Must capture
                                        </Badge>
                                    )}
                                    <span className="text-[10px] text-muted-foreground">
                                        {category.default_picture_count} pic
                                        {category.default_picture_count === 1 ? '' : 's'}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            ))}

            <div className="rounded-xl border border-border/80 overflow-hidden">
                <div className="flex items-center gap-2 bg-muted/40 px-3 py-2 border-b border-border/60">
                    <ListChecks className="size-4 text-primary" />
                    <h4 className="text-xs font-bold text-foreground">Checklist</h4>
                </div>
                <div className="space-y-1.5 p-3">
                    {template.checklist_items.length === 0 ? (
                        <p className="text-[11px] text-muted-foreground">
                            No checklist questions yet.
                        </p>
                    ) : (
                        template.checklist_items.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                            >
                                <span className="text-[11px] text-foreground flex-1">
                                    {item.label}
                                </span>
                                {item.has_explain_field && (
                                    <Badge variant="outline" className="text-[9px]">
                                        Explain
                                    </Badge>
                                )}
                                {item.allows_na && (
                                    <Badge variant="secondary" className="text-[9px]">
                                        Allows N/A
                                    </Badge>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
