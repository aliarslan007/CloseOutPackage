import solucompLogo from '@/assets/images/solucomp-latest-logo.webp';
import { CommandPalette } from '@/components/navigation/command-palette';
import { SupportChatDialog } from '@/components/navigation/support-chat-dialog';
import { BreadcrumbSegment, parseBreadcrumb, Topbar } from '@/components/navigation/topbar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useSidebarStore } from '@/stores/sidebar.store';
import { useThemeStore } from '@/stores/theme.store';
import { PageProps } from '@/types/inertia';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bookmark,
    Building2,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
    CircleHelp,
    ClipboardList,
    FilePenLine,
    FileText,
    Image as ImageIcon,
    LayoutDashboard,
    LayoutTemplate,
    LifeBuoy,
    Mail,
    MapPin,
    MoreHorizontal,
    Radio,
    Send,
    Settings,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import * as React from 'react';

export interface AppLayoutProps {
    title?: string;
    breadcrumb?: BreadcrumbSegment[] | string;
    /** Rendered right next to/after the page title (tags/badges like market, status, PS). */
    headerTags?: React.ReactNode;
    /** Rendered directly below the title (e.g. location, PM info). */
    headerSubtitle?: React.ReactNode;
    /** Rendered in the center of the page title row on medium/large screens. */
    headerCenter?: React.ReactNode;
    /** Rendered on the same row as the page title (right side). */
    headerActions?: React.ReactNode;
    children: React.ReactNode;
}

export interface NavSubItem {
    label: string;
    href: string;
    roles?: string[];
}

export interface NavItem {
    label: string;
    href?: string;
    icon: React.ReactNode;
    roles?: string[];
    disabled?: boolean;
    subItems?: NavSubItem[];
}

const COLLAPSED_JUSTIFY = 'justify-center px-2';
const ACTIVE_LINK_CLASS = 'font-bold underline underline-offset-4 decoration-1';
const PROFILE_EDIT_ROUTE = 'profile.edit';

function isCurrentRoute(href?: string): boolean {
    if (!href) return false;
    try {
        const parsedUrl = new URL(href, globalThis.location.origin);
        return (
            globalThis.location.pathname === parsedUrl.pathname &&
            globalThis.location.search === parsedUrl.search
        );
    } catch {
        return false;
    }
}

function renderNavTrailing(item: NavItem, hasSub: boolean, isSubOpen: boolean) {
    if (item.disabled) {
        return (
            <span className="rounded-full bg-sidebar-accent/20 px-2 py-0.5 text-[10px] font-normal text-sidebar-foreground/70">
                Soon
            </span>
        );
    }
    if (hasSub && isSubOpen) {
        return <ChevronDown className="size-4" />;
    }
    return <ChevronRight className="size-4" />;
}

interface PlatformNavSubItemsProps {
    subItems: NavSubItem[];
    userRoles: string[];
    onCloseMobile: () => void;
}

function PlatformNavSubItems({
    subItems,
    userRoles,
    onCloseMobile,
}: Readonly<PlatformNavSubItemsProps>) {
    const visibleSubItems = subItems.filter(
        (sub) => !sub.roles || sub.roles.some((r) => userRoles.includes(r))
    );

    return (
        <div className="ml-[22px] pl-4 py-1 space-y-3 my-1 border-l border-sidebar-foreground/30">
            {visibleSubItems.map((sub) => {
                const active = isCurrentRoute(sub.href);
                return (
                    <Link
                        key={sub.label}
                        href={sub.href}
                        onClick={onCloseMobile}
                        data-nav-active={active ? '1' : undefined}
                        className={cn(
                            'block text-[14px] font-medium transition-colors',
                            active
                                ? ACTIVE_LINK_CLASS
                                : 'font-normal opacity-90 hover:opacity-100 hover:underline'
                        )}
                    >
                        {sub.label}
                    </Link>
                );
            })}
        </div>
    );
}

interface PlatformNavItemRowProps {
    item: NavItem;
    collapsed: boolean;
    isSubOpen: boolean;
    userRoles: string[];
    onToggleSubMenu: (label: string) => void;
    onCloseMobile: () => void;
}

function PlatformNavItemRow({
    item,
    collapsed,
    isSubOpen,
    userRoles,
    onToggleSubMenu,
    onCloseMobile,
}: Readonly<PlatformNavItemRowProps>) {
    const hasSub = Boolean(item.subItems && item.subItems.length > 0);
    const isDirectActive = item.href ? isCurrentRoute(item.href) : false;
    const isParentActive =
        isDirectActive || (item.subItems ?? []).some((sub) => isCurrentRoute(sub.href));

    if (hasSub && !collapsed) {
        return (
            <div className="relative">
                <button
                    type="button"
                    disabled={item.disabled}
                    data-nav-active={isParentActive ? '1' : undefined}
                    onClick={() => onToggleSubMenu(item.label)}
                    className={cn(
                        'w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-[14px] font-medium transition-all hover:opacity-85 text-sidebar-foreground cursor-pointer',
                        isParentActive ? 'font-bold' : 'font-normal',
                        item.disabled && 'opacity-50 cursor-not-allowed hover:opacity-50'
                    )}
                >
                    <div className="flex items-center gap-3">
                        {item.icon}
                        <span>{item.label}</span>
                    </div>
                    <span className="opacity-100 pr-1">
                        {renderNavTrailing(item, hasSub, isSubOpen)}
                    </span>
                </button>
                {isSubOpen && item.subItems && (
                    <PlatformNavSubItems
                        subItems={item.subItems}
                        userRoles={userRoles}
                        onCloseMobile={onCloseMobile}
                    />
                )}
            </div>
        );
    }

    return (
        <div className="relative">
            <Link
                href={item.href || item.subItems?.[0]?.href || '#'}
                title={item.label}
                aria-label={item.label}
                onClick={onCloseMobile}
                data-nav-active={isParentActive ? '1' : undefined}
                className={cn(
                    'w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-[14px] font-medium transition-all hover:opacity-85 text-sidebar-foreground',
                    isParentActive ? 'font-bold' : 'font-normal',
                    item.disabled && 'opacity-50 pointer-events-none hover:opacity-50',
                    collapsed && COLLAPSED_JUSTIFY
                )}
            >
                <div className={cn('flex items-center gap-3', collapsed && 'justify-center')}>
                    {item.icon}
                    {!collapsed && (
                        <span className={cn(!hasSub && isDirectActive && ACTIVE_LINK_CLASS)}>
                            {item.label}
                        </span>
                    )}
                </div>
                {!collapsed && (
                    <span className="opacity-100 pr-1">
                        {renderNavTrailing(item, false, false)}
                    </span>
                )}
            </Link>
        </div>
    );
}

/**
 * Renders the logged-in user's profile photo, falling back to the first
 * initial when no photo is uploaded.
 */
function UserAvatar() {
    const { auth } = usePage<PageProps>().props;
    const photoUrl = auth?.user?.profile_photo_url;

    if (photoUrl) {
        return (
            <img
                src={photoUrl}
                alt={auth?.user?.name || 'User'}
                className="size-full rounded-full object-cover"
            />
        );
    }

    return (
        <span className="flex size-full items-center justify-center">
            {(auth?.user?.name || 'U').charAt(0)}
        </span>
    );
}

function pathOfHref(href?: string): string {
    try {
        return new URL(href ?? '', globalThis.location.origin).pathname;
    } catch {
        return '';
    }
}

function matchesNavSection(items: Array<{ href?: string }>, pagePath: string): boolean {
    return items.some((item) => {
        const navPath = pathOfHref(item.href);
        return navPath !== '' && (pagePath === navPath || pagePath.startsWith(`${navPath}/`));
    });
}

function resolveSectionRoot(
    pagePath: string,
    platformNavItems: NavItem[],
    settingsNavItem: NavItem,
    workspaceNavItems: NavItem[]
): string | null {
    // Bottom utility pages (Settings, Help Center, Profile) are platform chrome,
    // not a workspace, so they root at Platform like the rest of the nav.
    const platformUtility: Array<{ href?: string }> = [
        settingsNavItem,
        { href: route('help.index') },
        { href: route(PROFILE_EDIT_ROUTE) },
    ];
    if (
        matchesNavSection(platformNavItems, pagePath) ||
        matchesNavSection(platformUtility, pagePath)
    ) {
        return 'Platform';
    }
    if (matchesNavSection(workspaceNavItems, pagePath)) {
        return 'Workspaces';
    }
    return null;
}

export function AppLayout({
    title,
    breadcrumb,
    headerTags,
    headerSubtitle,
    headerCenter,
    headerActions,
    children,
}: Readonly<AppLayoutProps>) {
    const [commandPaletteOpen, setCommandPaletteOpen] = React.useState(false);
    const [supportChatOpen, setSupportChatOpen] = React.useState(false);
    const [unreadChatCount, setUnreadChatCount] = React.useState(0);
    const { theme } = useThemeStore();

    // Inlined Sidebar State & Config
    const { collapsed, mobileOpen, setMobileOpen } = useSidebarStore();
    const [openSubMenu, setOpenSubMenu] = React.useState<string | null>('Dashboard');

    // Close the mobile drawer when a visit begins. The top loading bar is NOT
    // drawn here — NProgress (configured in app.tsx) is the single progress bar,
    // so there is no second indicator competing with it.
    React.useEffect(() => {
        const removeStart = router.on('start', () => {
            setMobileOpen(false);
        });

        return () => {
            removeStart();
        };
    }, [setMobileOpen]);

    const { auth } = usePage<PageProps>().props;
    const { url } = usePage();
    const appVersion = usePage<PageProps>().props.app?.version;
    const userRoles = auth?.user?.roles || [];
    const isAdmin = userRoles.includes('admin');
    // Matches SettingsPolicy::viewAny — admins plus managers, who get the
    // customer hierarchy tab only.
    const canSeeSettings = isAdmin || userRoles.includes('manager');

    const platformNavItems: NavItem[] = React.useMemo(
        () => [
            {
                label: 'Dashboard',
                href: route('dashboard'),
                icon: <LayoutDashboard className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager', 'field_supervisor', 'customer'],
                subItems: [
                    {
                        label: 'Overview',
                        href: route('dashboard'),
                        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
                    },
                    {
                        label: 'Activity Logs',
                        href: `${route('dashboard')}?tab=activity`,
                        roles: ['admin', 'manager'],
                    },
                ],
            },
            {
                label: 'Markets',
                href: route('admin.markets.index'),
                icon: <MapPin className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin'],
            },
            {
                label: 'Users',
                href: route('admin.users.index'),
                icon: <Users className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin'],
            },
            {
                label: 'Templates',
                href: route('admin.templates.index'),
                icon: <FileText className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin'],
                subItems: [
                    { label: 'All Templates', href: route('admin.templates.index') },
                    {
                        label: 'Subcategory Headings',
                        href: route('admin.templates.category-presets.index'),
                    },
                ],
            },
            {
                label: 'Favorite Templates',
                href: route('favorites.index'),
                icon: <Bookmark className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['field_supervisor'],
            },
            {
                label: 'Projects',
                href: route('projects.index'),
                icon: <Building2 className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager', 'field_supervisor'],
            },
            {
                label: 'SOW',
                href: route('sow.reports.index'),
                icon: <ClipboardList className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager', 'field_supervisor'],
            },
            {
                label: 'Change Orders',
                href: route('change-orders.index'),
                icon: <FilePenLine className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager', 'customer'],
            },
            {
                label: 'Media Library',
                href: route('media-library.index'),
                icon: <ImageIcon className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager', 'field_supervisor'],
            },
            {
                label: 'Email Templates',
                href: route('admin.email-templates.index'),
                icon: <LayoutTemplate className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager'],
            },
            {
                label: 'Customer Emails',
                href: route('manager.customer-emails.index'),
                icon: <Send className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager'],
            },
            {
                label: 'Email Audit Logs',
                href: isAdmin
                    ? route('admin.email-logs.index')
                    : route('manager.email-logs.manager'),
                icon: <Mail className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['admin', 'manager'],
            },
            {
                label: 'Customer Portal',
                href: route('portal.index'),
                icon: <ShieldCheck className="size-4 shrink-0" strokeWidth={1.5} />,
                roles: ['customer'],
            },
        ],
        [isAdmin]
    );

    // Keep the submenu containing the current route expanded (e.g. landing
    // directly on Subcategory Headings opens Templates).
    React.useEffect(() => {
        const match = platformNavItems.find(
            (item) =>
                isCurrentRoute(item.href) ||
                (item.subItems ?? []).some((sub) => isCurrentRoute(sub.href))
        );
        if (match) setOpenSubMenu(match.label);
    }, [url, platformNavItems]);

    // Ensure theme class on <html> on mount/change
    React.useEffect(() => {
        if (theme === 'system') {
            const isDark = globalThis.matchMedia('(prefers-color-scheme: dark)').matches;
            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } else if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }, [theme]);

    const workspaceNavItems = [
        {
            label: 'Live Tracking',
            href: route('tracking.index'),
            icon: <Radio className="size-4 shrink-0" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
        },
        {
            label: 'Live Visibility',
            href: route('manager.visibility'),
            icon: <BarChart3 className="size-4 shrink-0" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
        },
        {
            label: 'Customer Access',
            href: route('admin.customers.index'),
            icon: <ShieldCheck className="size-4 shrink-0" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
        },
        {
            label: 'Deleted Packages',
            href: route('admin.packages.deleted'),
            icon: <Trash2 className="size-4 shrink-0" strokeWidth={1.5} />,
            roles: ['admin'],
        },
    ];

    // Config belongs with the bottom utility cluster, not inside a long
    // scrollable nav — settings stay one click away at any scroll depth.
    const settingsNavItem: NavItem = {
        label: 'App Settings',
        href: route('admin.settings.index'),
        icon: <Settings className="size-4 shrink-0" strokeWidth={1.5} />,
        roles: ['admin', 'manager'],
    };

    const filteredPlatformItems = platformNavItems.filter(
        (item) => !item.roles || item.roles.some((r) => userRoles.includes(r))
    );

    const filteredWorkspaceItems = workspaceNavItems.filter(
        (item) => !item.roles || item.roles.some((r) => userRoles.includes(r))
    );

    const toggleSubMenu = (label: string) => {
        setOpenSubMenu((prev) => (prev === label ? null : label));
    };

    // The scrollbar is invisible (no-scrollbar), so a bottom fade tells users
    // there are more items below the fold; it clears once they reach the end.
    // The scroll position is shared across navigations via sessionStorage, so
    // deep menus stay reachable without re-scrolling after every page change.
    const NAV_SCROLL_KEY = 'cop.sidebar-nav-scroll';
    const navRef = React.useRef<HTMLDivElement>(null);
    const [navScroll, setNavScroll] = React.useState({ scrollable: false, atEnd: true });

    const updateNavScroll = React.useCallback(() => {
        const el = navRef.current;
        if (el === null) {
            return;
        }

        setNavScroll({
            scrollable: el.scrollHeight > el.clientHeight + 4,
            atEnd: el.scrollTop + el.clientHeight >= el.scrollHeight - 8,
        });
    }, []);

    const persistNavScroll = React.useCallback(() => {
        const el = navRef.current;
        if (el === null) {
            return;
        }

        try {
            sessionStorage.setItem(NAV_SCROLL_KEY, String(el.scrollTop));
        } catch {
            // Private mode / storage-less contexts: scroll simply won't persist.
        }
    }, []);

    const handleNavScroll = React.useCallback(() => {
        updateNavScroll();
        persistNavScroll();
    }, [updateNavScroll, persistNavScroll]);

    React.useEffect(() => {
        updateNavScroll();

        const el = navRef.current;
        if (el === null) {
            return;
        }

        const observer = new ResizeObserver(updateNavScroll);
        observer.observe(el);

        return () => observer.disconnect();
    }, [updateNavScroll, collapsed, mobileOpen]);

    // After every navigation: if the page's own menu entry is hidden below the
    // fold, reveal it; otherwise restore the last scroll position.
    React.useEffect(() => {
        const el = navRef.current;
        if (el === null) {
            return;
        }

        const active = el.querySelector<HTMLElement>('[data-nav-active="1"]');

        if (active !== null) {
            const containerRect = el.getBoundingClientRect();
            const rect = active.getBoundingClientRect();
            const isVisible = rect.top >= containerRect.top && rect.bottom <= containerRect.bottom;

            if (!isVisible) {
                active.scrollIntoView({ block: 'center' });
                updateNavScroll();
                persistNavScroll();
            }

            return;
        }

        try {
            const saved = Number(sessionStorage.getItem(NAV_SCROLL_KEY) || 0);
            if (saved > 0) {
                el.scrollTop = saved;
            }
        } catch {
            // Private mode: nothing to restore.
        }
        updateNavScroll();
    }, [url, collapsed, mobileOpen, updateNavScroll, persistNavScroll]);

    const sidebarContent = (
        <div className="flex flex-col h-full bg-sidebar text-sidebar-foreground select-none transition-colors duration-200 no-scrollbar">
            {/* Header / Brand Logo */}
            <div
                className={cn(
                    'flex h-14 items-center justify-between px-4 mt-2',
                    collapsed && COLLAPSED_JUSTIFY
                )}
            >
                <Link href={route('dashboard')} className="flex items-center gap-3">
                    <img
                        src={solucompLogo}
                        alt="SoluComp"
                        className="h-9 w-auto shrink-0 object-contain"
                    />
                    {!collapsed && (
                        <div className="flex flex-col leading-none gap-1">
                            <span className="font-bold text-[15px] font-heading text-sidebar-foreground tracking-tight">
                                COP
                            </span>
                            <span className="text-[12px] font-medium text-sidebar-foreground">
                                Close Out Package
                            </span>
                        </div>
                    )}
                </Link>
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setMobileOpen(false)}
                        className="lg:hidden size-9 rounded-full text-sidebar-foreground border border-sidebar-border/30 bg-sidebar/20"
                        aria-label="Close navigation menu"
                    >
                        <X className="size-4.5" />
                    </Button>
                </div>
            </div>

            {/* Navigation Items */}
            <div className="relative flex-1 min-h-0">
                <div
                    ref={navRef}
                    onScroll={handleNavScroll}
                    className={cn(
                        'h-full overflow-y-auto py-6 space-y-6 no-scrollbar pb-safe',
                        collapsed ? 'px-1.5' : 'px-3'
                    )}
                >
                    {/* Platform Section */}
                    <div className="space-y-1">
                        {!collapsed && (
                            <p className="px-2 text-[12px] font-semibold opacity-80 mb-3 uppercase tracking-wider">
                                Platform
                            </p>
                        )}

                        {filteredPlatformItems.map((item) => (
                            <PlatformNavItemRow
                                key={item.label}
                                item={item}
                                collapsed={collapsed}
                                isSubOpen={openSubMenu === item.label}
                                userRoles={userRoles}
                                onToggleSubMenu={toggleSubMenu}
                                onCloseMobile={() => setMobileOpen(false)}
                            />
                        ))}
                    </div>

                    {/* Workspaces Section — visible in both expanded and collapsed modes */}
                    {filteredWorkspaceItems.length > 0 && (
                        <div className="space-y-1">
                            {collapsed ? (
                                <div className="my-2 border-t border-sidebar-foreground/20 mx-1" />
                            ) : (
                                <p className="px-2 text-[12px] font-semibold opacity-80 mb-3 uppercase tracking-wider">
                                    Workspaces
                                </p>
                            )}
                            {filteredWorkspaceItems.map((ws) => (
                                <Link
                                    key={ws.label}
                                    href={ws.href}
                                    title={ws.label}
                                    aria-label={ws.label}
                                    onClick={() => setMobileOpen(false)}
                                    data-nav-active={isCurrentRoute(ws.href) ? '1' : undefined}
                                    className={cn(
                                        'w-full flex items-center px-2.5 py-2 rounded-lg text-[14px] font-medium hover:bg-sidebar-accent/10 transition-colors text-sidebar-foreground',
                                        isCurrentRoute(ws.href) && ACTIVE_LINK_CLASS,
                                        collapsed ? COLLAPSED_JUSTIFY : 'justify-between'
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'flex items-center gap-3 min-w-0',
                                            collapsed && 'justify-center'
                                        )}
                                    >
                                        {ws.icon}
                                        {!collapsed && (
                                            <span className="whitespace-normal leading-tight">
                                                {ws.label}
                                            </span>
                                        )}
                                    </div>
                                    {!collapsed && (
                                        <MoreHorizontal className="size-4 opacity-80 shrink-0 ml-2" />
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
                {navScroll.scrollable && !navScroll.atEnd && (
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-sidebar from-55% to-sidebar/0"
                    >
                        <div className="absolute inset-x-0 bottom-0 flex h-14 items-end justify-center pb-2.5">
                            <ChevronDown
                                className="size-5 text-sidebar-foreground/85"
                                strokeWidth={2.5}
                            />
                        </div>
                    </div>
                )}
            </div>

            {/* Support / Feedback Section — accessible in both expanded and collapsed modes */}
            <div className={cn('pb-2 space-y-0.5', collapsed ? 'px-1' : 'px-3')}>
                <Link
                    href={route('help.index')}
                    title="Help Center"
                    aria-label="Help Center"
                    onClick={() => setMobileOpen(false)}
                    className={cn(
                        'w-full flex items-center gap-3 px-2.5 py-2 rounded-lg text-[14px] font-medium hover:opacity-80 transition-colors text-sidebar-foreground',
                        collapsed && COLLAPSED_JUSTIFY
                    )}
                >
                    <CircleHelp className="size-4" strokeWidth={1.5} />
                    {!collapsed && <span>Help Center</span>}
                </Link>
                <button
                    type="button"
                    title="Support Chat"
                    aria-label="Support Chat"
                    onClick={() => {
                        setMobileOpen(false);
                        setSupportChatOpen(true);
                    }}
                    className={cn(
                        'w-full flex items-center gap-3 px-2.5 py-2 rounded-lg text-[14px] font-medium hover:opacity-80 transition-colors text-sidebar-foreground cursor-pointer',
                        collapsed && COLLAPSED_JUSTIFY
                    )}
                >
                    <LifeBuoy className="size-4" strokeWidth={1.5} />
                    {!collapsed && <span>Support</span>}
                    {unreadChatCount > 0 && (
                        <span className={cn('flex items-center gap-1.5', !collapsed && 'ml-auto')}>
                            <span className="size-2 rounded-full bg-red-500 animate-pulse" />
                            {!collapsed && (
                                <span className="text-[10px] font-bold text-red-500">
                                    {unreadChatCount}
                                </span>
                            )}
                        </span>
                    )}
                </button>
            </div>

            {/* Settings — pinned below the utility cluster so it never
                depends on scrolling a long admin nav. Managers see the
                customer hierarchy tab only; the rest is withheld server-side. */}
            {canSeeSettings &&
                (collapsed ? (
                    <div className="p-2 mb-2 flex justify-center">
                        <Link
                            href={settingsNavItem.href}
                            onClick={() => setMobileOpen(false)}
                            title="App Settings"
                            aria-label="App Settings"
                            className={cn(
                                'flex size-9 items-center justify-center rounded-full border border-sidebar-border/60 transition-colors hover:bg-sidebar-accent/10 text-sidebar-foreground',
                                isCurrentRoute(settingsNavItem.href) &&
                                    'border-primary text-primary font-bold'
                            )}
                        >
                            {settingsNavItem.icon}
                        </Link>
                    </div>
                ) : (
                    <div className="mx-3 mb-2 border-t border-sidebar-border/40 pt-2">
                        <Link
                            href={settingsNavItem.href}
                            onClick={() => setMobileOpen(false)}
                            className="flex items-center gap-3 px-2.5 py-2 rounded-lg text-[14px] font-medium hover:opacity-80 transition-colors text-sidebar-foreground"
                        >
                            {settingsNavItem.icon}
                            <span>{settingsNavItem.label}</span>
                            {isCurrentRoute(settingsNavItem.href) && (
                                <span className="ml-auto size-1.5 rounded-full bg-primary" />
                            )}
                        </Link>
                    </div>
                ))}

            {/* Bottom User Section — opens the profile page */}
            {collapsed ? (
                <div className="p-2 mb-3 flex justify-center">
                    <Link
                        href={route(PROFILE_EDIT_ROUTE)}
                        onClick={() => setMobileOpen(false)}
                        title={auth?.user?.name}
                        aria-label="View and edit your profile"
                        className="rounded-full transition-opacity hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <div className="size-9 rounded-full bg-primary text-primary-foreground font-bold flex items-center justify-center text-sm shrink-0 uppercase overflow-hidden">
                            <UserAvatar />
                        </div>
                    </Link>
                </div>
            ) : (
                <div className="p-4 mb-2 space-y-3">
                    <Link
                        href={route(PROFILE_EDIT_ROUTE)}
                        onClick={() => setMobileOpen(false)}
                        title="View and edit your profile"
                        className="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 -mx-2 transition-colors hover:bg-sidebar-accent/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                            <div className="size-9 rounded-full bg-primary text-primary-foreground font-bold flex items-center justify-center text-sm shrink-0 uppercase overflow-hidden">
                                <UserAvatar />
                            </div>
                            <div className="flex flex-col min-w-0">
                                <span className="text-[14px] font-bold text-sidebar-foreground leading-tight truncate">
                                    {auth?.user?.name || 'User'}
                                </span>
                                <span className="text-[12px] text-sidebar-foreground opacity-80 leading-tight mt-0.5 truncate">
                                    {auth?.user?.email || ''}
                                </span>
                            </div>
                        </div>
                        <ChevronsUpDown
                            className="size-4 shrink-0 text-sidebar-foreground opacity-70"
                            strokeWidth={1.5}
                        />
                    </Link>
                    {appVersion && (
                        <div className="text-center text-[11px] text-sidebar-foreground/65 border-t border-sidebar-border/15 pt-2.5 shrink-0 font-medium tracking-wide">
                            Version {appVersion}
                        </div>
                    )}
                </div>
            )}
        </div>
    );

    const currentSegments = parseBreadcrumb(breadcrumb ?? title ?? 'Dashboard');
    const pagePath = pathOfHref(currentSegments[0]?.href ?? url);
    const sectionRoot = resolveSectionRoot(
        pagePath,
        platformNavItems,
        settingsNavItem,
        workspaceNavItems
    );
    const resolvedBreadcrumb: BreadcrumbSegment[] =
        sectionRoot && currentSegments[0]?.label !== sectionRoot
            ? [{ label: sectionRoot }, ...currentSegments]
            : currentSegments;

    return (
        <div className="h-[100dvh] overflow-hidden flex bg-background text-foreground transition-colors duration-200">
            {/* Desktop Sidebar (Inlined, No vertical divider borders) */}
            <aside
                className={cn(
                    'hidden lg:block h-screen sticky top-0 z-30 transition-all duration-300 shrink-0',
                    collapsed ? 'w-16' : 'w-64'
                )}
            >
                {sidebarContent}
            </aside>

            {/* Mobile Drawer (Inlined) */}
            {mobileOpen && (
                <>
                    <button
                        type="button"
                        aria-label="Close menu"
                        tabIndex={-1}
                        className="fixed inset-0 z-40 bg-foreground/60 lg:hidden"
                        onClick={() => setMobileOpen(false)}
                    />
                    <aside className="fixed left-0 top-0 z-50 h-full w-64 bg-sidebar animate-in slide-in-from-left duration-200 lg:hidden">
                        {sidebarContent}
                    </aside>
                </>
            )}

            {/* Main Outer Area with expanded floating card */}
            <div className="flex-1 flex flex-col min-w-0 h-full p-1 sm:p-1.5 lg:p-2 overflow-hidden">
                {/* Floating Main Page Card - Enlarged Bounds (soft tint to reduce glare) */}
                <div className="flex-1 bg-muted/50 text-card-foreground rounded-2xl lg:rounded-[24px] border border-border/80 shadow-xl flex flex-col overflow-hidden h-full">
                    {/* Topbar Navigation Header */}
                    <Topbar
                        breadcrumb={resolvedBreadcrumb}
                        onOpenCommandPalette={() => setCommandPaletteOpen(true)}
                        onUnreadChatChange={setUnreadChatCount}
                    />

                    {/* Main Content Slot */}
                    <main className="flex-1 px-3 sm:px-4 lg:px-5 pt-2 overflow-y-auto w-full mx-auto pb-safe overscroll-y-contain">
                        <div className="pb-28 sm:pb-16 lg:pb-12 space-y-2">
                            {/* Global Page Heading + Center Slot + Actions on the same row */}
                            {title && (
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-x-4 gap-y-3 pb-1 min-w-0">
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex items-center gap-2.5 flex-wrap min-w-0">
                                            <h1 className="font-heading text-xl font-bold tracking-tight text-foreground leading-tight min-w-0 break-words [overflow-wrap:anywhere]">
                                                {title}
                                            </h1>
                                            {headerTags}
                                        </div>
                                        {headerSubtitle && (
                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground min-w-0">
                                                {headerSubtitle}
                                            </div>
                                        )}
                                    </div>
                                    {headerCenter && (
                                        <div className="flex items-center justify-center min-w-0 sm:mx-auto order-3 sm:order-2 shrink-0">
                                            {headerCenter}
                                        </div>
                                    )}
                                    {headerActions && (
                                        <div className="flex items-center gap-2 min-w-0 order-2 sm:order-3 sm:ml-auto w-full sm:w-auto justify-start sm:justify-end flex-wrap sm:flex-nowrap shrink-0">
                                            {headerActions}
                                        </div>
                                    )}
                                </div>
                            )}

                            {children}
                        </div>
                    </main>
                </div>
            </div>

            {/* ⌘K Command Palette */}
            <CommandPalette open={commandPaletteOpen} onOpenChange={setCommandPaletteOpen} />

            {/* Support Chat */}
            <SupportChatDialog open={supportChatOpen} onClose={() => setSupportChatOpen(false)} />
        </div>
    );
}

export default AppLayout;
