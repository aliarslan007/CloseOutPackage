import { Progress } from '@/components/ui/progress';
import { ShieldCheck } from 'lucide-react';
import { PortalMetrics } from '../portal.types';

interface PortalHeaderProps {
    metrics: PortalMetrics;
    marketCount: number;
}

export function PortfolioBadge() {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground">
            <ShieldCheck className="size-3.5" />
            Verizon Wireless
        </span>
    );
}

function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function be(count: number): string {
    return count === 1 ? 'is' : 'are';
}

/** Used when nothing is waiting on the customer. */
function allClearVerdict(metrics: PortalMetrics, sites: string, inMarkets: string): string {
    const status = metrics.approved === metrics.total ? 'fully approved' : 'moving through approval';

    return `All ${sites} ${be(metrics.total)} ${status} ${inMarkets}. Nothing needs your attention right now.`;
}

/**
 * Plain-language verdict. A customer should be able to read one sentence and
 * know whether anything is waiting on them, without interpreting a chart.
 *
 * Branches are ordered so no sentence can contradict the numbers below it — a
 * portfolio with no submitted packages is never described as "progressing".
 */
function verdictFor(metrics: PortalMetrics, marketCount: number): string {
    if (metrics.total === 0) {
        return 'Once your administrator assigns a site, its photos, checklist, and approval status will appear here so you can follow the work and sign off on each package.';
    }

    const sites = plural(metrics.total, 'site');
    const inMarkets = `in ${plural(marketCount, 'market')}`;

    if (metrics.notStarted === metrics.total) {
        return `No packages have been submitted for your ${sites} ${inMarkets} yet. We will let you know as soon as the first one is ready to review.`;
    }

    if (metrics.needAttention === 0) {
        return allClearVerdict(metrics, sites, inMarkets);
    }

    if (metrics.needAttention === metrics.total) {
        return `All ${sites} ${be(metrics.total)} waiting on your review.`;
    }

    const waiting =
        metrics.needAttention === 1 ? '1 site is' : `${metrics.needAttention} sites are`;
    const rest = metrics.total - metrics.needAttention;

    return `${waiting} waiting on your review. The remaining ${plural(rest, 'site')} ${be(rest)} moving through approval ${inMarkets}.`;
}

export function PortfolioVerdict({ metrics, marketCount }: Readonly<PortalHeaderProps>) {
    return (
        <p className="text-sm leading-relaxed text-muted-foreground">
            {verdictFor(metrics, marketCount)}
        </p>
    );
}

/**
 * Compact health readout that sits beside the page actions. Deliberately a
 * one-line stat, not a card — the site list owns the vertical space here.
 */
export function PortfolioHealth({ metrics }: Readonly<{ metrics: PortalMetrics }>) {
    if (metrics.total === 0) {
        return null;
    }

    return (
        <div className="flex w-full items-center gap-2.5 rounded-md border border-border/80 bg-card px-3 py-1.5 sm:w-auto">
            <span className="text-xs font-medium text-muted-foreground">Portfolio health</span>
            <span className="font-heading text-sm font-bold tabular-nums text-foreground">
                {metrics.avgProgress}%
            </span>
            <Progress value={metrics.avgProgress} className="w-14 shrink-0 bg-border" />
        </div>
    );
}
