import { SearchInput } from '@/components/shared/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Building2, Check, FileCode2 } from 'lucide-react';
import * as React from 'react';

export interface MarketOption {
    id: number;
    name: string;
    slug: string;
    isActive: boolean;
}

export interface MarketSelectorProps {
    markets: MarketOption[];
    selectedMarketId?: number | null;
    onSelect: (marketId: number) => void;
    className?: string;
}

export function MarketSelector({
    markets,
    selectedMarketId,
    onSelect,
    className,
}: MarketSelectorProps) {
    return (
        <div className={cn('grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3', className)}>
            {markets.map((market) => {
                const isSelected = selectedMarketId === market.id;
                return (
                    <Card
                        key={market.id}
                        onClick={() => onSelect(market.id)}
                        className={cn(
                            'cursor-pointer transition-all hover:border-primary',
                            isSelected && 'border-primary bg-primary/5 ring-1 ring-primary'
                        )}
                    >
                        <CardContent className="p-4 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <Building2 className="size-5" />
                                </div>
                                <div>
                                    <h4 className="font-semibold text-sm">{market.name}</h4>
                                    <p className="text-xs text-muted-foreground">{market.slug}</p>
                                </div>
                            </div>
                            {isSelected && <Check className="size-5 text-primary" />}
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}

export interface TemplateOption {
    id: number;
    name: string;
    description?: string;
    marketName?: string;
    sectionCount?: number;
    categoryCount?: number;
}

export interface TemplateSelectorProps {
    templates: TemplateOption[];
    onSelectTemplate: (templateId: number) => void;
    className?: string;
}

export function TemplateSelector({
    templates,
    onSelectTemplate,
    className,
}: TemplateSelectorProps) {
    const [search, setSearch] = React.useState('');

    const filtered = templates.filter(
        (t) =>
            t.name.toLowerCase().includes(search.toLowerCase()) ||
            t.description?.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <div className={cn('space-y-4', className)}>
            <SearchInput
                value={search}
                onSearchChange={setSearch}
                placeholder="Search templates..."
                className="max-w-md"
            />

            {filtered.length === 0 ? (
                <div className="p-8 border border-dashed rounded-xl text-center text-xs text-muted-foreground">
                    No templates available for this market.
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {filtered.map((template) => (
                        <Card
                            key={template.id}
                            className="hover:border-primary transition-all flex flex-col justify-between"
                        >
                            <CardContent className="p-5 space-y-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <div className="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <FileCode2 className="size-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-base">{template.name}</h3>
                                            {template.marketName && (
                                                <Badge
                                                    variant="outline"
                                                    className="mt-1 text-[10px]"
                                                >
                                                    {template.marketName}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {template.description && (
                                    <p className="text-xs text-muted-foreground line-clamp-2">
                                        {template.description}
                                    </p>
                                )}

                                <div className="flex items-center gap-4 text-xs text-muted-foreground pt-2 border-t">
                                    <span>{template.sectionCount ?? 3} Sections</span>
                                    <span>•</span>
                                    <span>{template.categoryCount ?? 100}+ Picture Categories</span>
                                </div>

                                <Button
                                    className="w-full mt-2"
                                    onClick={() => onSelectTemplate(template.id)}
                                >
                                    Select Template & Start Package
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
