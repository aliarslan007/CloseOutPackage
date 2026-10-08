import Dropdown from '@/components/Dropdown';
import NotificationCenter from '@/components/navigation/notification-center';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useSidebarStore } from '@/stores/sidebar.store';
import { useThemeStore } from '@/stores/theme.store';
import { PageProps } from '@/types/inertia';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    CircleHelp,
    Laptop,
    LogOut,
    Moon,
    PanelLeft,
    Search,
    ShieldCheck,
    Sun,
} from 'lucide-react';
import * as React from 'react';

export interface BreadcrumbSegment {
    label: string;
    href?: string;
}

export interface TopbarProps {
    breadcrumb?: BreadcrumbSegment[] | string;
    onOpenCommandPalette?: () => void;
    onUnreadChatChange?: (count: number) => void;
}

export function parseBreadcrumb(breadcrumb?: BreadcrumbSegment[] | string): BreadcrumbSegment[] {
    if (Array.isArray(breadcrumb)) {
        return breadcrumb;
    }

    const parts = (breadcrumb ?? 'Dashboard').split('>').map((part) => part.trim());
    return parts.map((label, index) => ({
        label,
        href: index < parts.length - 1 ? route('dashboard') : undefined,
    }));
}

const WORKSPACE_META: Record<string, { label: string }> = {
    admin: { label: 'Admin Workspace' },
    manager: { label: 'Manager Workspace' },
    field_supervisor: { label: 'Field Technician Workspace' },
    customer: { label: 'Verizon Customer Workspace' },
};

/**
 * Graphite platform chrome. The active role is a watermark for where you are,
 * not a status message — colour on this row would compete with the page's own
 * signals, so every workspace shares one neutral chip.
 */
const WORKSPACE_CHIP_CLASS =
    'border-transparent bg-stone-800 text-stone-50 dark:bg-stone-700 dark:text-stone-50';

function WorkspacePill(): React.ReactElement | null {
    const { auth } = usePage<PageProps>().props;
    const role = auth.user?.roles?.[0] ?? null;
    const meta = role ? WORKSPACE_META[role] : null;

    if (!role || !meta) {
        return null;
    }

    return (
        <span
            title={`You are signed in to the ${meta.label}`}
            className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold ${WORKSPACE_CHIP_CLASS}`}
        >
            <ShieldCheck className="size-3 shrink-0" />
            <span>{meta.label}</span>
        </span>
    );
}

export function Topbar({
    breadcrumb = 'Dashboard',
    onOpenCommandPalette,
    onUnreadChatChange,
}: Readonly<TopbarProps>) {
    const { toggleCollapsed, toggleMobileOpen } = useSidebarStore();
    const { theme, setTheme } = useThemeStore();

    const segments = parseBreadcrumb(breadcrumb);
    const lastIndex = segments.length - 1;

    return (
        <header className="relative z-30 h-12 border-b border-border/60 bg-card px-2.5 sm:px-4 flex items-center justify-between gap-2 sm:gap-3 shrink-0 select-none">
            {/* Left: Sidebar Collapse Toggle + Mobile-Safe Breadcrumbs */}
            <div className="flex items-center gap-1.5 sm:gap-3 min-w-0 flex-1 overflow-hidden pr-2">
                {/* Desktop Collapse Toggle Icon */}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={toggleCollapsed}
                    className="hidden lg:flex size-8 sm:size-9 text-muted-foreground hover:text-foreground hover:bg-muted/60 rounded-full shrink-0"
                    title="Toggle Sidebar"
                    aria-label="Toggle Sidebar"
                >
                    <PanelLeft className="size-4" strokeWidth={1.5} />
                </Button>

                {/* Mobile Drawer Toggle */}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={toggleMobileOpen}
                    className="lg:hidden size-8 sm:size-9 text-muted-foreground hover:text-foreground rounded-full shrink-0"
                    aria-label="Open navigation menu"
                >
                    <PanelLeft className="size-4" strokeWidth={1.5} />
                </Button>

                {/* Breadcrumb Trail */}
                <nav
                    aria-label="Breadcrumb"
                    className="flex items-center gap-1 sm:gap-1.5 text-xs sm:text-sm font-heading font-medium leading-none min-w-0 overflow-hidden"
                >
                    {segments.map((segment, index) => {
                        const isLast = index === lastIndex;
                        const isFirst = index === 0;
                        const isMiddle = !isFirst && !isLast;

                        return (
                            <React.Fragment key={segment.label + index}>
                                {index > 0 && (
                                    <ChevronRight className="size-3 sm:size-3.5 text-muted-foreground/60 shrink-0" />
                                )}
                                {segment.href && !isLast ? (
                                    <Link
                                        href={segment.href}
                                        className={cn(
                                            'text-muted-foreground hover:text-foreground transition-colors font-medium truncate max-w-[80px] xs:max-w-[120px] sm:max-w-[150px] lg:max-w-[180px]',
                                            isMiddle && index < lastIndex - 1 && 'hidden sm:inline-block'
                                        )}
                                        title={segment.label}
                                    >
                                        {segment.label}
                                    </Link>
                                ) : (
                                    <span
                                        className={cn(
                                            'truncate max-w-[100px] xs:max-w-[140px] sm:max-w-[180px] lg:max-w-[200px]',
                                            isLast ? 'text-foreground font-semibold' : 'text-muted-foreground font-medium'
                                        )}
                                        title={segment.label}
                                    >
                                        {segment.label}
                                    </span>
                                )}
                            </React.Fragment>
                        );
                    })}
                </nav>
            </div>

            {/* Center / Right-Adjacent: Workspace Pill */}
            <div className="hidden md:flex items-center justify-center shrink-0">
                <WorkspacePill />
            </div>

            {/* Right: Search, Notification, Theme Toggle */}
            <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                {/* Search Icon Only Button */}
                <button
                    type="button"
                    onClick={onOpenCommandPalette}
                    className="size-8 sm:size-9 rounded-full border border-border/80 bg-white dark:bg-zinc-900 text-foreground hover:bg-accent/50 flex items-center justify-center transition-all shadow-2xs shrink-0"
                    title="Search (⌘K)"
                    aria-label="Search"
                >
                    <Search className="size-3.5 sm:size-4 text-foreground" strokeWidth={1.5} />
                </button>

                {/* Help Center Button */}
                <Link
                    href={route('help.index')}
                    className="size-8 sm:size-9 rounded-full border border-border/80 bg-white dark:bg-zinc-900 text-foreground hover:bg-accent/50 flex items-center justify-center transition-all shadow-2xs shrink-0"
                    title="Help Center"
                    aria-label="Help Center"
                >
                    <CircleHelp className="size-3.5 sm:size-4 text-foreground" strokeWidth={1.5} />
                </Link>

                {/* Notifications Center */}
                <div className="shrink-0">
                    <NotificationCenter onUnreadChatChange={onUnreadChatChange} />
                </div>

                {/* Light / Dark / System Theme Dropdown */}
                <Dropdown>
                    <Dropdown.Trigger>
                        <span
                            className="size-8 sm:size-9 rounded-full border border-border/80 bg-white dark:bg-zinc-900 text-foreground hover:bg-accent/50 flex items-center justify-center transition-all shadow-2xs cursor-pointer shrink-0"
                            title="Switch Theme"
                            aria-label="Switch Theme"
                        >
                            {theme === 'dark' ? (
                                <Moon className="size-3.5 sm:size-4 text-foreground" strokeWidth={1.5} />
                            ) : (
                                <Sun className="size-3.5 sm:size-4 text-foreground" strokeWidth={1.5} />
                            )}
                        </span>
                    </Dropdown.Trigger>

                    <Dropdown.Content
                        align="right"
                        contentClasses="w-32 py-1 shadow-lg border bg-card rounded-xl overflow-hidden font-sans text-[13px]"
                    >
                        <button
                            type="button"
                            onClick={() => setTheme('light')}
                            className="w-full text-left px-3 py-2 hover:bg-accent hover:text-accent-foreground transition-colors flex items-center gap-2 cursor-pointer"
                        >
                            <Sun className="size-3.5" strokeWidth={1.5} /> Light
                        </button>
                        <button
                            type="button"
                            onClick={() => setTheme('dark')}
                            className="w-full text-left px-3 py-2 hover:bg-accent hover:text-accent-foreground transition-colors flex items-center gap-2 cursor-pointer"
                        >
                            <Moon className="size-3.5" strokeWidth={1.5} /> Dark
                        </button>
                        <button
                            type="button"
                            onClick={() => setTheme('system')}
                            className="w-full text-left px-3 py-2 hover:bg-accent hover:text-accent-foreground transition-colors flex items-center gap-2 cursor-pointer"
                        >
                            <Laptop className="size-3.5" strokeWidth={1.5} /> System
                        </button>
                    </Dropdown.Content>
                </Dropdown>

                {/* Log Out Button */}
                <button
                    type="button"
                    onClick={() => router.post(route('logout'))}
                    className="size-8 sm:size-9 rounded-full border border-border/80 bg-white dark:bg-zinc-900 text-muted-foreground hover:text-destructive hover:border-destructive/40 hover:bg-destructive/10 flex items-center justify-center transition-all shadow-2xs shrink-0"
                    title="Log Out"
                    aria-label="Log Out"
                >
                    <LogOut className="size-3.5 sm:size-4" strokeWidth={1.5} />
                </button>
            </div>
        </header>
    );
}

export default Topbar;
