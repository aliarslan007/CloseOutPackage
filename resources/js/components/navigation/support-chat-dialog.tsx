import { ChatMessageData, ChatWidget } from '@/components/shared/chat-widget';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { MessageSquare, MessageSquareOff, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface ChatProject {
    id: number;
    name: string;
    unread_count?: number;
}

const CHAT_MESSAGES_ROUTE = 'projects.chat.messages';

function projectLabel(project: ChatProject): string {
    return project.unread_count
        ? `${project.name} (${project.unread_count} new)`
        : project.name;
}

export function SupportChatDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const [projects, setProjects] = useState<ChatProject[]>([]);
    const [projectId, setProjectId] = useState<number | null>(null);
    const [messages, setMessages] = useState<ChatMessageData[]>([]);
    const [loadingProjects, setLoadingProjects] = useState(false);
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadProjects = useCallback(() => {
        setError(null);
        setLoadingProjects(true);
        axios
            .get(route('chat.projects'))
            .then((response) => {
                const data = (response.data?.data ?? []) as ChatProject[];
                setProjects(data);
                const firstUnread = data.find((project) => (project.unread_count ?? 0) > 0);
                setProjectId((firstUnread ?? data[0])?.id ?? null);
            })
            .catch(() => setError('Could not load your projects. Please try again.'))
            .finally(() => setLoadingProjects(false));
    }, []);

    useEffect(() => {
        if (open) {
            loadProjects();
        }
    }, [open, loadProjects]);

    useEffect(() => {
        if (!open || projectId === null) {
            return;
        }
        setError(null);
        setLoadingMessages(true);
        axios
            .get(route(CHAT_MESSAGES_ROUTE, { project: projectId }))
            .then((response) => setMessages((response.data?.data ?? []) as ChatMessageData[]))
            .catch(() => setError('Could not load messages. Please try again.'))
            .finally(() => setLoadingMessages(false));
    }, [open, projectId]);

    useEffect(() => {
        if (!open || projectId === null) {
            return;
        }
        axios
            .put(route('projects.chat.read', { project: projectId }))
            .catch(() => {
                // transient failure — messages stay unread until the next open
            });
    }, [open, projectId]);

    useEffect(() => {
        if (!open || projectId === null) {
            return;
        }
        const timer = setInterval(() => {
            axios
                .get(route(CHAT_MESSAGES_ROUTE, { project: projectId }))
                .then((response) => setMessages((response.data?.data ?? []) as ChatMessageData[]))
                .catch(() => {
                    // transient polling failure — keep the last known messages
                });
            axios
                .get(route('chat.projects'))
                .then((response) =>
                    setProjects((response.data?.data ?? []) as ChatProject[])
                )
                .catch(() => {
                    // transient polling failure — keep the last known projects
                });
        }, 5000);
        return () => clearInterval(timer);
    }, [open, projectId]);

    const handleSend = (message: string) => {
        if (projectId === null) {
            return;
        }
        setSending(true);
        axios
            .post(route('projects.chat.store', { project: projectId }), { body: message })
            .then(() => axios.get(route(CHAT_MESSAGES_ROUTE, { project: projectId })))
            .then((response) => setMessages((response.data?.data ?? []) as ChatMessageData[]))
            .catch(() => setError('Could not send your message. Please try again.'))
            .finally(() => setSending(false));
    };

    const selectedProjectName =
        projects.find((project) => project.id === projectId)?.name ?? null;
    const hasAnyUnread = projects.some((project) => (project.unread_count ?? 0) > 0);

    let chatContent;
    if (loadingProjects) {
        chatContent = (
            <div className="p-8 text-center text-xs text-muted-foreground">
                Loading your projects…
            </div>
        );
    } else if (projects.length === 0) {
        const loadFailed = error !== null;
        chatContent = (
            <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-10 text-center">
                <MessageSquareOff className="size-8 text-muted-foreground/60" />
                <p className="text-sm font-semibold text-foreground">
                    {loadFailed ? "Couldn't load your projects" : 'No projects to chat about yet'}
                </p>
                <p className="max-w-xs text-xs text-muted-foreground">
                    {loadFailed
                        ? 'Something went wrong while fetching your sites. Please try again.'
                        : 'Your assigned sites will appear here as soon as a manager adds you to a project.'}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-2 h-8 gap-1.5 text-xs"
                    onClick={loadProjects}
                >
                    <RefreshCw className="size-3.5" /> {loadFailed ? 'Try again' : 'Check again'}
                </Button>
            </div>
        );
    } else {
        chatContent = (
            <>
                {error && (
                    <p className="rounded-lg bg-red-500/10 border border-red-500/30 p-2 text-xs text-red-600 dark:text-red-400">
                        {error}
                    </p>
                )}
                <div className="flex items-center gap-2">
                    <label
                        htmlFor="chat-project"
                        className="text-xs font-medium text-muted-foreground shrink-0"
                    >
                        Project
                    </label>
                    <select
                        id="chat-project"
                        value={projectId ?? ''}
                        onChange={(e) => setProjectId(Number(e.target.value) || null)}
                        className={cn(
                            'h-9 flex-1 min-w-0 rounded-md border border-input bg-card px-2 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-ring',
                            hasAnyUnread && 'border-amber-500/60 ring-1 ring-amber-500/30'
                        )}
                    >
                        {projects.map((project) => (
                            <option key={project.id} value={project.id}>
                                {projectLabel(project)}
                            </option>
                        ))}
                    </select>
                </div>

                <ChatWidget
                    messages={messages}
                    onSendMessage={handleSend}
                    loading={loadingMessages || sending}
                    className="h-[480px]"
                />
            </>
        );
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MessageSquare className="size-4 text-primary" /> Site Chat
                        {selectedProjectName && (
                            <span className="text-muted-foreground font-normal text-xs truncate">
                                — {selectedProjectName}
                            </span>
                        )}
                    </DialogTitle>
                </DialogHeader>
                <div className="space-y-3">{chatContent}</div>
            </DialogContent>
        </Dialog>
    );
}

export default SupportChatDialog;
