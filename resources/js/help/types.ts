/**
 * Help Center content model.
 *
 * The help center is written for everyone — including users with no
 * technical background — so articles are organised around the question
 * the user is trying to answer ("How do I upload photos?") rather than
 * around feature names. Every article can carry "Take me there" buttons
 * that deep-link into the real application screens.
 */

export type HelpRole = 'admin' | 'manager' | 'field_supervisor' | 'customer';

export type HelpCategoryId =
    | 'getting-started'
    | 'projects'
    | 'packages'
    | 'photos'
    | 'review'
    | 'pdfs'
    | 'admin'
    | 'communication'
    | 'customer';

/** A button inside a step that jumps straight into the app. */
export interface HelpLink {
    /** Friendly button label, e.g. "Go to my projects". */
    label: string;
    /** Ziggy route name (the same names used across the app). */
    route: string;
    /** Route parameters, e.g. { project: 12 }. */
    params?: Record<string, string | number>;
    /** Extra query string values, e.g. { tab: 'activity' }. */
    query?: Record<string, string>;
}

export interface HelpStep {
    /** Short, plain-language heading, e.g. "Open Projects". */
    title: string;
    /** What to do, written for a non-technical reader. */
    body: string;
    /** Optional buttons that take the reader straight to the screen. */
    links?: HelpLink[];
    /** A friendly hint in a "Tip" box. */
    tip?: string;
    /** A friendly caution in a "Please note" box. */
    warning?: string;
}

export interface HelpFaq {
    question: string;
    answer: string;
}

export interface HelpArticle {
    /** Unique id used by related links. Lowercase, hyphenated. */
    slug: string;
    /** The question-style title, e.g. "How do I upload photos for a step?" */
    title: string;
    /** One-line summary shown on cards and in search results. */
    summary: string;
    /** Which roles this article is shown to. */
    roles: HelpRole[];
    category: HelpCategoryId;
    /** Key of a lucide-react icon (resolved in the page, not in content). */
    icon: string;
    /** Estimated reading time in minutes. */
    minutes: number;
    /** Extra search words (synonyms and jargon the reader may type). */
    keywords?: string[];
    /** Friendly opening paragraph that reassures and explains. */
    intro: string;
    /** Ordered, numbered steps. */
    steps: HelpStep[];
    faqs?: HelpFaq[];
    /** Slugs of related articles. */
    related?: string[];
    /** Marked articles are surfaced in the "Popular for your role" row. */
    featured?: boolean;
}

export interface HelpCategory {
    id: HelpCategoryId;
    /** Short name shown on the category card, e.g. "Projects & visibility". */
    label: string;
    /** One-line friendly description. */
    description: string;
    icon: string;
    /** Which roles see this category. */
    roles: HelpRole[];
}
