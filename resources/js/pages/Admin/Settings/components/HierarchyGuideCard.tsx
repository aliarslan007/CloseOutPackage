import { Info } from 'lucide-react';

/**
 * Plain-language explainer of the Customer → Region → Default Engineer
 * hierarchy, so a first-time admin knows what the console does before touching it.
 */
export function HierarchyGuideCard() {
    return (
        <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs">
            <div className="flex items-start gap-3">
                <div className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Info className="size-4" />
                </div>
                <div className="flex-1 space-y-1.5">
                    <h4 className="font-semibold text-foreground">Customer &amp; Engineer Hierarchy Overview</h4>
                    <p className="leading-relaxed text-muted-foreground">
                        Organize telecom carriers and field contacts by territory. When creating a project, selecting a{' '}
                        <strong>Customer</strong> and <strong>Region</strong> will automatically assign that
                        territory&apos;s default engineer.
                    </p>
                    <div className="grid grid-cols-1 gap-2 pt-1 text-[11px] sm:grid-cols-3">
                        <div className="space-y-0.5 rounded-md border border-border/60 bg-card p-2.5">
                            <span className="block font-semibold text-foreground">1. Customers</span>
                            <span className="text-muted-foreground">
                                Telecom carriers (Verizon, T-Mobile, AT&amp;T) managed by Admins.
                            </span>
                        </div>
                        <div className="space-y-0.5 rounded-md border border-border/60 bg-card p-2.5">
                            <span className="block font-semibold text-foreground">2. Regions</span>
                            <span className="text-muted-foreground">
                                Operational territories linked directly to your active Markets.
                            </span>
                        </div>
                        <div className="space-y-0.5 rounded-md border border-border/60 bg-card p-2.5">
                            <span className="block font-semibold text-foreground">3. Default Engineers</span>
                            <span className="text-muted-foreground">
                                Marked with <span className="font-semibold text-amber-600 dark:text-amber-400">★ Default</span>.
                                Auto-selected on projects and printed on PDF covers.
                            </span>
                        </div>
                    </div>
                    <p className="pt-1 leading-relaxed text-muted-foreground">
                        <strong className="text-foreground">Note:</strong> an engineer&apos;s{' '}
                        <span className="font-semibold">&ldquo;N assigned&rdquo;</span> counts only the projects given to
                        that engineer, while a customer&apos;s <span className="font-semibold">&ldquo;N projects&rdquo;</span>{' '}
                        counts every project linked to the customer. A customer cannot be deleted until all of its
                        projects are reassigned or removed.
                    </p>
                </div>
            </div>
        </div>
    );
}
