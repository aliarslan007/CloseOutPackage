import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    EMAIL_TEMPLATE_STATUS_LABELS,
    statusBadgeVariant,
    type EmailTemplateItem,
    type EmailTypeOption,
} from '@/pages/Admin/EmailTemplates/components/email-template-types';
import { Link } from '@inertiajs/react';
import { Copy, Pencil, Star, Trash2 } from 'lucide-react';

export interface EmailTemplateRowProps {
    readonly template: EmailTemplateItem;
    readonly emailTypes: ReadonlyArray<EmailTypeOption>;
    readonly onSetDefault: (template: EmailTemplateItem) => void;
    readonly onDuplicate: (template: EmailTemplateItem) => void;
    readonly onDelete: (template: EmailTemplateItem) => void;
}

function typeLabel(emailTypes: ReadonlyArray<EmailTypeOption>, value: string): string {
    return emailTypes.find((type) => type.value === value)?.label ?? value;
}

/** One library row: type chip, name, status, updated date, row actions. */
export function EmailTemplateRow({
    template,
    emailTypes,
    onSetDefault,
    onDuplicate,
    onDelete,
}: Readonly<EmailTemplateRowProps>) {
    return (
        <tr className="border-t border-border">
            <td className="px-3 py-2">
                {/* Mobile-only caps so long type names wrap into a few lines
                    instead of blowing out the auto table width. */}
                <Badge variant="outline" className="max-w-[7.5rem] break-words sm:max-w-none">
                    {typeLabel(emailTypes, template.email_type)}
                </Badge>
            </td>
            <td className="px-3 py-2">
                <span className="flex items-center gap-1.5 font-medium max-w-[10rem] break-words sm:max-w-none">
                    {template.name}
                    {template.is_default && (
                        <Badge variant="secondary" className="shrink-0 gap-1">
                            <Star className="size-2.5" /> Default
                        </Badge>
                    )}
                </span>
            </td>
            <td className="px-3 py-2">
                <Badge variant={statusBadgeVariant(template.status)}>
                    {EMAIL_TEMPLATE_STATUS_LABELS[template.status]}
                </Badge>
            </td>
            <td className="px-3 py-2 text-xs text-muted-foreground">
                {new Date(template.updated_at).toLocaleDateString()}
            </td>
            <td className="px-3 py-2">
                <div className="flex items-center justify-end gap-1">
                    {!template.is_default && template.status === 'active' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 gap-1 px-2 text-xs"
                            onClick={() => onSetDefault(template)}
                            title="Set as default for this email type"
                        >
                            <Star className="size-3" /> Default
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 gap-1 px-2 text-xs"
                        onClick={() => onDuplicate(template)}
                        title="Duplicate this template"
                    >
                        <Copy className="size-3" />
                    </Button>
                    <Button asChild variant="ghost" size="sm" className="h-7 px-2" title="Edit">
                        <Link href={route('admin.email-templates.show', template.id)}>
                            <Pencil className="size-3" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 px-2 text-destructive hover:text-destructive"
                        onClick={() => onDelete(template)}
                        title="Delete"
                    >
                        <Trash2 className="size-3" />
                    </Button>
                </div>
            </td>
        </tr>
    );
}