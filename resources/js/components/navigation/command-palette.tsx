import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types/inertia';
import {
    Dialog,
    DialogContent,
} from '@/components/ui/dialog';
import {
    Search,
    LayoutDashboard,
    Activity,
    Building2,
    Bookmark,
    FileText,
    MapPin,
    Users,
    BarChart3,
    Mail,
    Send,
    ShieldCheck,
    Settings,
    Trash2,
    CircleHelp,
    User,
    LogOut,
} from 'lucide-react';

export interface CommandPaletteProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface CommandAction {
    id: string;
    label: string;
    description: string;
    icon: React.ReactNode;
    roles?: string[];
    action: () => void;
}

export function CommandPalette({ open, onOpenChange }: Readonly<CommandPaletteProps>) {
    const [query, setQuery] = React.useState('');
    const { auth } = usePage<PageProps>().props;
    const userRoles = React.useMemo(() => auth?.user?.roles || [], [auth]);
    const isAdmin = userRoles.includes('admin');

    React.useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                onOpenChange(!open);
            }
        };

        globalThis.addEventListener('keydown', handleKeyDown);
        return () => globalThis.removeEventListener('keydown', handleKeyDown);
    }, [open, onOpenChange]);

    const navigate = React.useCallback((path: string) => {
        onOpenChange(false);
        setQuery('');
        router.visit(path);
    }, [onOpenChange]);

    const allActions: CommandAction[] = React.useMemo(() => [
        {
            id: 'dashboard',
            label: 'Dashboard',
            description: 'Go to main overview dashboard',
            icon: <LayoutDashboard className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager', 'field_supervisor'],
            action: () => navigate(route('dashboard')),
        },
        {
            id: 'activity-logs',
            label: 'Activity Logs',
            description: 'View system audit activity logs',
            icon: <Activity className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
            action: () => navigate(`${route('dashboard')}?tab=activity`),
        },
        {
            id: 'projects',
            label: 'Projects',
            description: 'View all cell site projects & jobs',
            icon: <Building2 className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager', 'field_supervisor'],
            action: () => navigate(route('projects.index')),
        },
        {
            id: 'customer-portal',
            label: 'Customer Portal',
            description: 'View assigned Verizon projects & deliverables',
            icon: <ShieldCheck className="size-4" strokeWidth={1.5} />,
            roles: ['customer'],
            action: () => navigate(route('portal.index')),
        },
        {
            id: 'favorite-templates',
            label: 'Favorite Templates',
            description: 'Saved custom category templates',
            icon: <Bookmark className="size-4" strokeWidth={1.5} />,
            roles: ['field_supervisor'],
            action: () => navigate(route('favorites.index')),
        },
        {
            id: 'templates',
            label: 'Templates',
            description: 'Manage master COP picture categories & templates',
            icon: <FileText className="size-4" strokeWidth={1.5} />,
            roles: ['admin'],
            action: () => navigate(route('admin.templates.index')),
        },
        {
            id: 'markets',
            label: 'Markets',
            description: 'Manage region markets and state boundaries',
            icon: <MapPin className="size-4" strokeWidth={1.5} />,
            roles: ['admin'],
            action: () => navigate(route('admin.markets.index')),
        },
        {
            id: 'users',
            label: 'Users',
            description: 'Manage platform user accounts & roles',
            icon: <Users className="size-4" strokeWidth={1.5} />,
            roles: ['admin'],
            action: () => navigate(route('admin.users.index')),
        },
        {
            id: 'live-visibility',
            label: 'Live Visibility',
            description: 'Real-time cell-site stage & photo uploads',
            icon: <BarChart3 className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
            action: () => navigate(route('manager.visibility')),
        },
        {
            id: 'customer-emails',
            label: 'Customer Emails',
            description: 'Customer email outbox for manual delivery & scheduling',
            icon: <Send className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
            action: () => navigate(route('manager.customer-emails.index')),
        },
        {
            id: 'email-audit-logs',
            label: 'Email Audit Logs',
            description: 'Track sent close-out package notification emails',
            icon: <Mail className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
            action: () => navigate(isAdmin ? route('admin.email-logs.index') : route('manager.email-logs.manager')),
        },
        {
            id: 'customer-access',
            label: 'Customer Access',
            description: 'Manage customer portal access permissions',
            icon: <ShieldCheck className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
            action: () => navigate(route('admin.customers.index')),
        },
        {
            id: 'deleted-packages',
            label: 'Deleted Packages',
            description: 'View and manage deleted close-out packages',
            icon: <Trash2 className="size-4 text-destructive" strokeWidth={1.5} />,
            roles: ['admin'],
            action: () => navigate(route('admin.packages.deleted')),
        },
        {
            id: 'app-settings',
            label: 'App Settings',
            description: 'Application-wide settings & defaults',
            icon: <Settings className="size-4" strokeWidth={1.5} />,
            roles: ['admin', 'manager'],
            action: () => navigate(route('admin.settings.index')),
        },
        {
            id: 'help-center',
            label: 'Help Center',
            description: 'Guides, COP workflow documentation & FAQs',
            icon: <CircleHelp className="size-4" strokeWidth={1.5} />,
            action: () => navigate(route('help.index')),
        },
        {
            id: 'profile',
            label: 'Profile',
            description: 'Edit account and profile settings',
            icon: <User className="size-4" strokeWidth={1.5} />,
            action: () => navigate(route('profile.edit')),
        },
        {
            id: 'logout',
            label: 'Log Out',
            description: 'End session and return to sign in',
            icon: <LogOut className="size-4 text-destructive" strokeWidth={1.5} />,
            action: () => {
                onOpenChange(false);
                router.post(route('logout'));
            },
        },
    ], [isAdmin, navigate, onOpenChange]);

    const accessibleActions = React.useMemo(() => {
        return allActions.filter(
            (act) => !act.roles || act.roles.some((r) => userRoles.includes(r))
        );
    }, [allActions, userRoles]);

    const filteredActions = React.useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return accessibleActions;
        }
        return accessibleActions.filter(
            (act) =>
                act.label.toLowerCase().includes(q) ||
                act.description.toLowerCase().includes(q)
        );
    }, [accessibleActions, query]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="p-0 overflow-hidden max-w-lg border border-border bg-card text-card-foreground shadow-2xl rounded-2xl">
                <div className="flex items-center px-4 py-2.5 border-b border-border/60 bg-muted/20">
                    <Search className="size-4 shrink-0 text-muted-foreground mr-2.5" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Type a page name or command to search..."
                        className="w-full bg-transparent border-none outline-none focus:outline-none focus:ring-0 focus-visible:ring-0 focus-visible:outline-none h-9 text-xs font-mono text-foreground placeholder:text-muted-foreground p-0 shadow-none"
                        autoFocus
                    />
                </div>
                <div className="max-h-72 overflow-y-auto p-2 space-y-1 no-scrollbar">
                    {filteredActions.length === 0 ? (
                        <p className="p-4 text-center text-xs font-mono text-muted-foreground">
                            No matching commands found.
                        </p>
                    ) : (
                        filteredActions.map((act) => (
                            <button
                                key={act.id}
                                onClick={act.action}
                                className="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left text-xs font-mono hover:bg-muted/60 transition-colors cursor-pointer"
                            >
                                <span className="text-muted-foreground">{act.icon}</span>
                                <div className="flex flex-col min-w-0">
                                    <span className="font-bold text-foreground truncate">
                                        {act.label}
                                    </span>
                                    <span className="text-[11px] text-muted-foreground truncate">
                                        {act.description}
                                    </span>
                                </div>
                            </button>
                        ))
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default CommandPalette;
