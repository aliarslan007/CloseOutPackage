import { playNotificationSound } from '@/lib/notification-sound';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { Bell, CheckCheck, Clock, Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface NotificationItem {
    id: string;
    title: string;
    description: string;
    createdAt: string;
    read: boolean;
    category?: string;
    package_id?: number | null;
    pdf_type?: 'final' | 'partial' | null;
}

function formatTime(value?: string | null): string {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function markItemsRead(items: NotificationItem[]): NotificationItem[] {
    return items.map((item) => ({ ...item, read: true }));
}

export function NotificationCenter({
    onUnreadChatChange,
}: Readonly<{
    onUnreadChatChange?: (count: number) => void;
}>) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);

    const isInitialLoad = useRef(true);
    const prevUnreadCountRef = useRef(0);
    const knownIdsRef = useRef<Set<string>>(new Set());

    const unreadChatCount = items.filter((item) => item.category === 'chat' && !item.read).length;

    useEffect(() => {
        onUnreadChatChange?.(unreadChatCount);
    }, [unreadChatCount, onUnreadChatChange]);

    const loadNotifications = () => {
        axios
            .get(route('notifications.index'))
            .then((response) => {
                const fetchedItems = (response.data?.items ?? []) as NotificationItem[];
                const newUnreadCount = Number(response.data?.unread_count ?? 0);

                if (isInitialLoad.current) {
                    isInitialLoad.current = false;
                    prevUnreadCountRef.current = newUnreadCount;
                    knownIdsRef.current = new Set(fetchedItems.map((i) => i.id));
                } else {
                    const hasNewItem = fetchedItems.some(
                        (item) => !item.read && !knownIdsRef.current.has(item.id)
                    );
                    const unreadCountIncreased = newUnreadCount > prevUnreadCountRef.current;

                    if (hasNewItem || unreadCountIncreased) {
                        playNotificationSound();
                    }

                    prevUnreadCountRef.current = newUnreadCount;
                    fetchedItems.forEach((item) => knownIdsRef.current.add(item.id));
                }

                setItems(fetchedItems);
                setUnreadCount(newUnreadCount);
            })
            .catch(() => {
                // transient polling failure — keep the last known state
            });
    };

    useEffect(() => {
        loadNotifications();
        const timer = setInterval(loadNotifications, 5000);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!open) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        globalThis.addEventListener('keydown', handleKeyDown);
        return () => globalThis.removeEventListener('keydown', handleKeyDown);
    }, [open]);

    const handleMarkAllRead = () => {
        axios
            .post(route('notifications.read'))
            .then(() => {
                setItems((prev) => markItemsRead(prev));
                setUnreadCount(0);
            })
            .catch(() => {
                // transient failure — badge stays until the next poll
            });
    };

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                aria-haspopup="menu"
                aria-expanded={open}
                className="relative size-8 sm:size-9 rounded-full border border-border/80 bg-white dark:bg-zinc-900 text-foreground hover:bg-accent/50 flex items-center justify-center transition-all shadow-2xs cursor-pointer shrink-0"
                title="Notifications"
                aria-label="Notifications"
            >
                <Bell className="size-3.5 sm:size-4 text-foreground" strokeWidth={1.5} />
                {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex min-w-4 h-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white ring-2 ring-card">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <>
                    {/* Backdrop */}
                    <button
                        type="button"
                        aria-label="Close notifications"
                        tabIndex={-1}
                        className="fixed inset-0 z-[60] bg-black/20 dark:bg-black/50 sm:bg-transparent cursor-default animate-in fade-in duration-150"
                        onClick={() => setOpen(false)}
                    />

                    {/* Popover Panel */}
                    <div
                        role="menu"
                        tabIndex={-1}
                        className={cn(
                            'z-[70] rounded-2xl border border-border/80 bg-card text-card-foreground shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150',
                            // Mobile: fixed below topbar, pinned 12px from left and right screen edges
                            'max-sm:fixed max-sm:inset-x-3 max-sm:top-14 max-sm:w-auto max-sm:max-w-none',
                            // Desktop: absolute, right-aligned to the bell button with 320px width
                            'sm:absolute sm:top-full sm:right-0 sm:left-auto sm:mt-2 sm:w-80'
                        )}
                    >
                        <div className="flex items-center justify-between p-3 border-b border-border/80 bg-muted/40">
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-xs">Notifications</span>
                                {unreadCount > 0 && (
                                    <span className="bg-primary/10 text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        {unreadCount} new
                                    </span>
                                )}
                            </div>
                            {unreadCount > 0 && (
                                <button
                                    type="button"
                                    onClick={handleMarkAllRead}
                                    className="text-[11px] font-medium text-muted-foreground hover:text-foreground flex items-center gap-1 cursor-pointer transition-colors"
                                >
                                    <CheckCheck className="size-3" /> Mark all read
                                </button>
                            )}
                        </div>

                        <div className="max-h-72 overflow-y-auto overflow-x-hidden divide-y divide-border/60">
                            {items.length === 0 ? (
                                <p className="p-6 text-center text-xs text-muted-foreground">
                                    No notifications at this time.
                                </p>
                            ) : (
                                items.map((item) => (
                                    <div
                                        key={item.id}
                                        className={cn(
                                            'p-3 flex items-start gap-2.5 text-xs transition-colors hover:bg-muted/40',
                                            !item.read && 'bg-primary/5 font-medium'
                                        )}
                                    >
                                        <Clock className="size-3.5 text-muted-foreground shrink-0 mt-0.5" />
                                        <div className="flex-1 min-w-0 space-y-1">
                                            <p className="font-semibold text-foreground truncate">
                                                {item.title}
                                            </p>
                                            {item.description && (
                                                <p className="text-[11px] text-muted-foreground line-clamp-2 break-words">
                                                    {item.description}
                                                </p>
                                            )}
                                            <span className="block text-[10px] text-muted-foreground">
                                                {formatTime(item.createdAt)}
                                            </span>
                                            {item.category === 'pdf' &&
                                                item.package_id != null &&
                                                item.pdf_type && (
                                                    <a
                                                        href={route(
                                                            item.pdf_type === 'final'
                                                                ? 'packages.pdf.download'
                                                                : 'packages.pdf.partial-download',
                                                            item.package_id
                                                        )}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-1.5 flex w-fit items-center gap-1.5 rounded-md border border-primary/30 bg-primary/10 px-2 py-1 text-[10px] font-semibold text-primary hover:bg-primary/20 transition-colors"
                                                    >
                                                        <Download className="size-3" />
                                                        Download{' '}
                                                        {item.pdf_type === 'final'
                                                            ? 'Final'
                                                            : 'Partial'}{' '}
                                                        PDF
                                                    </a>
                                                )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

export default NotificationCenter;
