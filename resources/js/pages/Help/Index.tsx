import { AppLayout } from '@/layouts/app-layout';
import { SearchInput } from '@/components/shared/search-input';
import { cn } from '@/lib/utils';
import {
    getCategoryById,
    getRelatedArticles,
    HELP_ARTICLES,
    HELP_CATEGORIES,
    resolveHelpLink,
} from '@/help/content';
import type { HelpArticle, HelpCategory, HelpCategoryId, HelpLink, HelpRole, HelpStep } from '@/help/types';
import { PageProps } from '@/types/inertia';
import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    Building2,
    Camera,
    CheckCircle2,
    ChevronRight,
    CircleHelp,
    ClipboardCheck,
    Clock,
    Download,
    Eye,
    FileText,
    FolderOpen,
    Home,
    LayoutDashboard,
    Lightbulb,
    ListChecks,
    Mail,
    Map,
    MessageSquare,
    Rocket,
    Search,
    Send,
    Settings2,
    ShieldCheck,
    Sparkles,
    ThumbsDown,
    ThumbsUp,
    Trash2,
    Users,
    type LucideIcon,
} from 'lucide-react';
import * as React from 'react';

/* ---------------------------------------------------------------------- */
/* Small shared pieces                                                    */
/* ---------------------------------------------------------------------- */

const ICON_MAP: Record<string, LucideIcon> = {
    rocket: Rocket,
    building: Building2,
    folder: FolderOpen,
    camera: Camera,
    clipboard: ClipboardCheck,
    file: FileText,
    settings: Settings2,
    chat: MessageSquare,
    home: Home,
    sparkles: Sparkles,
    book: BookOpen,
    eye: Eye,
    users: Users,
    list: ListChecks,
    check: CheckCircle2,
    send: Send,
    download: Download,
    dashboard: LayoutDashboard,
    map: Map,
    shield: ShieldCheck,
    trash: Trash2,
    clock: Clock,
    mail: Mail,
};

const ROLE_META: Record<HelpRole, { label: string; plural: string; className: string }> = {
    admin: {
        label: 'Admin',
        plural: 'Admins',
        className: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30',
    },
    manager: {
        label: 'Manager',
        plural: 'Managers',
        className: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
    },
    field_supervisor: {
        label: 'Supervisor',
        plural: 'Supervisors',
        className: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
    },
    customer: {
        label: 'Customer',
        plural: 'Customers',
        className: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/30',
    },
};

const FEEDBACK_STORAGE_KEY = 'cop.help.feedback';

/** Shared horizontal padding for every card panel on this page. */
const PANEL_PADDING_X = 'px-5 sm:px-7';

function Icon({ name, className }: Readonly<{ name: string; className?: string }>) {
    const Component = ICON_MAP[name] ?? CircleHelp;
    return <Component className={className ?? 'size-4'} strokeWidth={1.5} />;
}

/** Themed square tile that holds an icon — used on cards, headers, and lists. */
function IconTile({
    name,
    className,
    iconClassName,
}: Readonly<{ name: string; className?: string; iconClassName?: string }>) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary',
                className,
            )}
        >
            <Icon name={name} className={iconClassName ?? 'size-4'} />
        </div>
    );
}

/** Friendly "Still stuck?" footer card, reused at the bottom of both views. */
function StillStuckCard({ children }: Readonly<{ children: React.ReactNode }>) {
    return (
        <div className="flex items-start gap-2.5 rounded-xl border border-border bg-card px-4 py-3.5">
            <CircleHelp className="mt-0.5 size-4 shrink-0 text-primary" />
            <p className="text-[13px] leading-relaxed text-muted-foreground">{children}</p>
        </div>
    );
}

function RoleChips({ roles }: Readonly<{ roles: HelpRole[] }>) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {roles.map((role) => (
                <span
                    key={role}
                    className={cn(
                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                        ROLE_META[role].className,
                    )}
                >
                    <ShieldCheck className="size-2.5" />
                    {ROLE_META[role].label}
                </span>
            ))}
        </div>
    );
}

function CategoryChip({ category }: Readonly<{ category: HelpCategoryId }>) {
    const meta = getCategoryById(category);
    if (!meta) return null;
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2.5 py-0.5 text-[11px] font-semibold text-muted-foreground">
            <Icon name={meta.icon} className="size-3" />
            {meta.label}
        </span>
    );
}

/** A "Take me there" button that opens the real screen in the app. */
function TakeMeThere({ link }: Readonly<{ link: HelpLink }>) {
    return (
        <Link
            href={resolveHelpLink(link)}
            className="inline-flex items-center gap-1.5 rounded-lg border border-primary/25 bg-primary/5 px-3 py-1.5 text-[13px] font-semibold text-primary transition-colors hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
            <ChevronRight className="size-3.5" />
            {link.label}
        </Link>
    );
}

function StepView({ step, index }: Readonly<{ step: HelpStep; index: number }>) {
    return (
        <li className="flex gap-3.5">
            <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-[13px] font-bold text-primary-foreground shadow-sm">
                {index + 1}
            </div>
            <div className="min-w-0 flex-1 space-y-2 pb-1">
                <div>
                    <h4 className="font-heading text-[15px] font-semibold text-foreground">{step.title}</h4>
                    <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{step.body}</p>
                </div>

                {step.links && step.links.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 pt-0.5">
                        {step.links.map((link) => (
                            <TakeMeThere key={link.label} link={link} />
                        ))}
                    </div>
                )}

                {step.tip && (
                    <div className="flex items-start gap-2 rounded-lg border border-amber-500/25 bg-amber-500/5 px-3 py-2">
                        <Lightbulb className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p className="text-[13px] leading-relaxed text-amber-800 dark:text-amber-200">
                            <span className="font-semibold">Tip: </span>
                            {step.tip}
                        </p>
                    </div>
                )}

                {step.warning && (
                    <div className="flex items-start gap-2 rounded-lg border border-red-500/25 bg-red-500/5 px-3 py-2">
                        <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-red-600 dark:text-red-400" />
                        <p className="text-[13px] leading-relaxed text-red-800 dark:text-red-200">
                            <span className="font-semibold">Please note: </span>
                            {step.warning}
                        </p>
                    </div>
                )}
            </div>
        </li>
    );
}

function FaqList({ faqs }: Readonly<{ faqs: HelpArticle['faqs'] }>) {
    const [open, setOpen] = React.useState<number | null>(null);
    if (!faqs || faqs.length === 0) return null;
    return (
        <div className="space-y-2">
            {faqs.map((faq, index) => {
                const isOpen = open === index;
                return (
                    <div key={faq.question} className="overflow-hidden rounded-xl border border-border bg-card">
                        <button
                            type="button"
                            onClick={() => setOpen(isOpen ? null : index)}
                            className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-foreground transition-colors hover:bg-accent/40"
                            aria-expanded={isOpen}
                        >
                            {faq.question}
                            <ChevronRight
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                    isOpen && 'rotate-90',
                                )}
                            />
                        </button>
                        {isOpen && (
                            <p className="border-t border-border/60 px-4 py-3 text-sm leading-relaxed text-muted-foreground">
                                {faq.answer}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/* ---------------------------------------------------------------------- */
/* Article reader                                                         */
/* ---------------------------------------------------------------------- */

function ArticleView({
    article,
    onBack,
    onOpenArticle,
}: Readonly<{
    article: HelpArticle;
    onBack: () => void;
    onOpenArticle: (slug: string) => void;
}>) {
    const [feedback, setFeedback] = React.useState<'yes' | 'no' | null>(() => {
        try {
            const stored = JSON.parse(localStorage.getItem(FEEDBACK_STORAGE_KEY) ?? '{}') as Record<string, 'yes' | 'no'>;
            return stored[article.slug] ?? null;
        } catch {
            return null;
        }
    });

    const recordFeedback = (value: 'yes' | 'no') => {
        setFeedback(value);
        try {
            const stored = JSON.parse(localStorage.getItem(FEEDBACK_STORAGE_KEY) ?? '{}') as Record<string, 'yes' | 'no'>;
            stored[article.slug] = value;
            localStorage.setItem(FEEDBACK_STORAGE_KEY, JSON.stringify(stored));
        } catch {
            /* Storage unavailable — the answer still counts for this visit. */
        }
    };

    const related = getRelatedArticles(article);

    return (
        <div className="space-y-5 pb-8">
            {/* Back + meta row */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-[13px] font-semibold text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    All topics
                </button>
                <div className="flex flex-wrap items-center gap-2">
                    <CategoryChip category={article.category} />
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                        <Clock className="size-3" />
                        {article.minutes} min read
                    </span>
                </div>
            </div>

            {/* Article body card */}
            <article className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                <div className={cn('border-b border-border/60 bg-gradient-to-br from-primary/[0.06] via-transparent to-transparent py-6', PANEL_PADDING_X)}>
                    <IconTile name={article.icon} className="mb-3 size-10 rounded-xl" iconClassName="size-5" />
                    <h2 className="font-heading text-xl font-bold leading-tight text-foreground sm:text-2xl">
                        {article.title}
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{article.intro}</p>
                    <div className="mt-3">
                        <RoleChips roles={article.roles} />
                    </div>
                </div>

                <div className={cn('py-6', PANEL_PADDING_X)}>
                    <h3 className="mb-4 flex items-center gap-2 font-heading text-sm font-bold uppercase tracking-wider text-muted-foreground">
                        <ListChecks className="size-4" />
                        Step by step
                    </h3>
                    <ol className="space-y-5">
                        {article.steps.map((step, index) => (
                            <StepView key={step.title} step={step} index={index} />
                        ))}
                    </ol>

                    {article.faqs && article.faqs.length > 0 && (
                        <div className="mt-8">
                            <h3 className="mb-3 font-heading text-sm font-bold uppercase tracking-wider text-muted-foreground">
                                Common questions
                            </h3>
                            <FaqList faqs={article.faqs} />
                        </div>
                    )}

                    {/* Was this helpful? */}
                    <div className="mt-8 flex flex-col items-center gap-3 rounded-xl border border-dashed border-border bg-muted/40 px-4 py-5 text-center">
                        <p className="text-sm font-semibold text-foreground">Was this helpful?</p>
                        {feedback ? (
                            <p className="inline-flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 className="size-4" />
                                Thanks for your feedback — we are always improving these guides.
                            </p>
                        ) : (
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => recordFeedback('yes')}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3.5 py-1.5 text-[13px] font-semibold text-foreground transition-colors hover:bg-emerald-500/10 hover:text-emerald-700 dark:hover:text-emerald-400"
                                >
                                    <ThumbsUp className="size-3.5" />
                                    Yes, that helped
                                </button>
                                <button
                                    type="button"
                                    onClick={() => recordFeedback('no')}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3.5 py-1.5 text-[13px] font-semibold text-foreground transition-colors hover:bg-red-500/10 hover:text-red-700 dark:hover:text-red-400"
                                >
                                    <ThumbsDown className="size-3.5" />
                                    Not yet
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </article>

            {/* Related */}
            {related.length > 0 && (
                <div>
                    <h3 className="mb-2.5 font-heading text-sm font-bold uppercase tracking-wider text-muted-foreground">
                        You may also want to read
                    </h3>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {related.map((item) => (
                            <RelatedCard
                                key={item.slug}
                                article={item}
                                onOpen={() => onOpenArticle(item.slug)}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* Still stuck */}
            <StillStuckCard>
                <span className="font-semibold text-foreground">Still stuck?</span> Use the{' '}
                <span className="font-semibold">Support</span> button at the bottom of the left menu to chat with the
                support team — a real person will pick it up.
            </StillStuckCard>
        </div>
    );
}

function RelatedCard({
    article,
    onOpen,
}: Readonly<{ article: HelpArticle; onOpen: () => void }>) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="group flex items-center gap-2.5 rounded-xl border border-border bg-card p-3 text-left transition-colors hover:border-primary/40 hover:bg-accent/40"
        >
            <IconTile name={article.icon} className="size-8" />
            <span className="text-[13px] font-semibold leading-snug text-foreground group-hover:underline">
                {article.title}
            </span>
        </button>
    );
}

/* ---------------------------------------------------------------------- */
/* Browse view                                                            */
/* ---------------------------------------------------------------------- */

function ArticleRow({
    article,
    onOpen,
}: Readonly<{ article: HelpArticle; onOpen: () => void }>) {
    const category = getCategoryById(article.category);
    return (
        <button
            type="button"
            onClick={onOpen}
            className="group flex w-full items-start gap-3.5 rounded-xl border border-border bg-card p-4 text-left transition-all hover:border-primary/40 hover:bg-accent/40"
        >
            <IconTile name={article.icon} className="size-9" />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h3 className="font-heading text-sm font-semibold leading-snug text-foreground group-hover:underline">
                        {article.title}
                    </h3>
                    {category && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                            {category.label}
                        </span>
                    )}
                </div>
                <p className="mt-1 text-[13px] leading-relaxed text-muted-foreground">{article.summary}</p>
                <div className="mt-2 flex items-center gap-2">
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                        <Clock className="size-3" />
                        {article.minutes} min read
                    </span>
                </div>
            </div>
            <ChevronRight className="mt-1 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
        </button>
    );
}

function CategoryCard({
    category,
    active,
    onClick,
}: Readonly<{ category: HelpCategory; active: boolean; onClick: () => void }>) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex flex-col gap-2 rounded-xl border p-3.5 text-left transition-all',
                active
                    ? 'border-primary/50 bg-primary/5 shadow-sm'
                    : 'border-border bg-card hover:border-primary/30 hover:bg-accent/40',
            )}
        >
            <div className="flex items-center justify-between">
                <IconTile name={category.icon} className={cn('size-8', active && 'border-primary/30')} />
                <ChevronRight className="size-3.5 text-muted-foreground/60" />
            </div>
            <div>
                <p className="text-[13px] font-semibold leading-tight text-foreground">{category.label}</p>
                <p className="mt-0.5 text-[11px] leading-relaxed text-muted-foreground">{category.description}</p>
            </div>
        </button>
    );
}

/* ---------------------------------------------------------------------- */
/* Main page                                                              */
/* ---------------------------------------------------------------------- */

function matchesQuery(article: HelpArticle, query: string): boolean {
    const needle = query.trim().toLowerCase();
    if (!needle) return true;
    const haystack = [
        article.title,
        article.summary,
        article.intro,
        ...(article.keywords ?? []),
        ...article.steps.flatMap((step) => [step.title, step.body, step.tip ?? '', step.warning ?? '']),
        ...(article.faqs ?? []).flatMap((faq) => [faq.question, faq.answer]),
    ]
        .join(' ')
        .toLowerCase();
    return haystack.includes(needle);
}

export default function HelpIndex() {
    const { auth } = usePage<PageProps>().props;
    const userRoles = (auth?.user?.roles ?? []) as HelpRole[];
    const primaryRole = userRoles[0] ?? null;

    const [query, setQuery] = React.useState('');
    const [activeCategory, setActiveCategory] = React.useState<HelpCategoryId | 'all'>('all');
    const [openArticleSlug, setOpenArticleSlug] = React.useState<string | null>(null);

    // Support deep links like /help?article=welcome
    React.useEffect(() => {
        const slug = new URLSearchParams(globalThis.location.search).get('article');
        if (slug && HELP_ARTICLES.some((article) => article.slug === slug)) {
            setOpenArticleSlug(slug);
        }
    }, []);

    const visibleCategories = HELP_CATEGORIES.filter((category) =>
        category.roles.some((role) => userRoles.includes(role)),
    );
    const visibleArticles = HELP_ARTICLES.filter((article) =>
        article.roles.some((role) => userRoles.includes(role)),
    );

    // Only ever open an article the signed-in user is allowed to see
    // (also covers deep links like /help?article=admin-users).
    const openArticle = openArticleSlug
        ? (visibleArticles.find((article) => article.slug === openArticleSlug) ?? null)
        : null;

    // Keep the inner scroll container at the top when opening/closing an article.
    React.useEffect(() => {
        document.querySelector('main')?.scrollTo({ top: 0 });
    }, [openArticleSlug]);

    const searchResults =
        query.trim().length > 0
            ? visibleArticles.filter((article) => matchesQuery(article, query))
            : visibleArticles.filter(
                  (article) => activeCategory === 'all' || article.category === activeCategory,
              );

    const featured = visibleArticles
        .filter((article) => article.featured)
        .sort((a, b) => {
            const aPrimary = a.roles.includes(primaryRole ?? '');
            const bPrimary = b.roles.includes(primaryRole ?? '');
            return Number(bPrimary) - Number(aPrimary);
        })
        .slice(0, 4);

    const grouped = visibleCategories
        .map((category) => ({
            category,
            articles: searchResults.filter((article) => article.category === category.id),
        }))
        .filter((group) => group.articles.length > 0);

    const primaryRoleMeta = primaryRole ? ROLE_META[primaryRole] : null;

    let resultCountLabel = 'No matches.';
    if (searchResults.length > 0) {
        resultCountLabel = `${searchResults.length} guide${searchResults.length === 1 ? '' : 's'} found.`;
    }

    if (openArticle) {
        return (
            <AppLayout
                title="Help Center"
                breadcrumb={[{ label: 'Help Center', href: route('help.index') }, { label: 'Article' }]}
            >
                <ArticleView
                    key={openArticle.slug}
                    article={openArticle}
                    onBack={() => setOpenArticleSlug(null)}
                    onOpenArticle={setOpenArticleSlug}
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Help Center"
            breadcrumb={[{ label: 'Help Center' }]}
        >
            <div className="space-y-2 pb-6">
                {/* Hero + search */}
                <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div className={cn('bg-gradient-to-br from-primary/[0.08] via-card to-card py-7 sm:py-8', PANEL_PADDING_X)}>
                        <div className="mx-auto max-w-2xl text-center">
                            <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl border border-primary/25 bg-primary/10 text-primary">
                                <CircleHelp className="size-6" />
                            </div>
                            <h2 className="font-heading text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                How can we help you today?
                            </h2>
                            <p className="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-muted-foreground">
                                Simple, step by step answers written for everyone — no technical knowledge needed.
                                {primaryRoleMeta
                                    ? ` Showing the guides made for ${primaryRoleMeta.plural}.`
                                    : ''}
                            </p>

                            <SearchInput
                                value={query}
                                onSearchChange={setQuery}
                                type="search"
                                placeholder="Try: how do I upload photos, or what does flag mean?"
                                aria-label="Search the help center"
                                className="mx-auto mt-5 max-w-xl"
                            />

                            {query.trim().length > 0 && (
                                <div className="mx-auto mt-2 max-w-xl text-left">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {resultCountLabel}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Popular for your role */}
                {featured.length > 0 && query.trim().length === 0 && (
                    <section>
                        <h3 className="mb-2.5 font-heading text-sm font-bold uppercase tracking-wider text-muted-foreground">
                            Popular {primaryRoleMeta ? `for ${primaryRoleMeta.plural}` : 'guides'}
                        </h3>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {featured.map((article) => (
                                <ArticleRow
                                    key={article.slug}
                                    article={article}
                                    onOpen={() => setOpenArticleSlug(article.slug)}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {/* Browse by topic */}
                {query.trim().length === 0 && (
                    <section>
                        <h3 className="mb-2.5 font-heading text-sm font-bold uppercase tracking-wider text-muted-foreground">
                            Browse by topic
                        </h3>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                            {visibleCategories.map((category) => (
                                <CategoryCard
                                    key={category.id}
                                    category={category}
                                    active={activeCategory === category.id}
                                    onClick={() =>
                                        setActiveCategory((current) =>
                                            current === category.id ? 'all' : category.id,
                                        )
                                    }
                                />
                            ))}
                        </div>
                    </section>
                )}

                {/* Search results / all guides */}
                {searchResults.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border bg-card px-6 py-10 text-center">
                        <Search className="size-5 text-muted-foreground" />
                        <p className="text-sm font-semibold text-foreground">We could not find a match</p>
                        <p className="max-w-sm text-[13px] leading-relaxed text-muted-foreground">
                            Try different words — for example "blurry" instead of "replacement", or ask in a
                            different way like "how do I send a report".
                        </p>
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                setActiveCategory('all');
                            }}
                            className="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-[13px] font-semibold text-foreground transition-colors hover:bg-accent"
                        >
                            Show all guides
                        </button>
                    </div>
                ) : (
                    <section className="space-y-5">
                        {query.trim().length === 0 &&
                            grouped.map(({ category, articles }) => (
                                <div key={category.id}>
                                    <div className="mb-2.5 flex items-center gap-2">
                                        <IconTile name={category.icon} className="size-7" iconClassName="size-3.5" />
                                        <h3 className="font-heading text-sm font-bold text-foreground">
                                            {category.label}
                                        </h3>
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {articles.length}
                                        </span>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {articles.map((article) => (
                                            <ArticleRow
                                                key={article.slug}
                                                article={article}
                                                onOpen={() => setOpenArticleSlug(article.slug)}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}

                        {query.trim().length > 0 && (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {searchResults.map((article) => (
                                    <ArticleRow
                                        key={article.slug}
                                        article={article}
                                        onOpen={() => setOpenArticleSlug(article.slug)}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {/* Footer note */}
                <StillStuckCard>
                    <span className="font-semibold text-foreground">Still stuck?</span> Use the{' '}
                    <span className="font-semibold">Support</span> button at the bottom of the left menu to chat with
                    the support team, or ask your manager — nobody minds helping.
                </StillStuckCard>
            </div>
        </AppLayout>
    );
}
