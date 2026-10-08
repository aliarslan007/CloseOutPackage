import * as React from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { MessageSquare, Send, User } from 'lucide-react';

export interface ChatMessage {
    id: number;
    sender_name: string;
    message: string;
    created_at: string;
    is_me: boolean;
}

export interface ProjectChatProps {
    project: { id: number; hash_id?: string; name: string; ps_number: string };
    messages: ChatMessage[];
}

export function ChatIndex({ project, messages = [] }: Readonly<ProjectChatProps>) {
    const form = useForm({
        message: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.data.message.trim()) return;
        form.post(route('projects.chat.store', project.hash_id || project.id), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AppLayout
            title={`Chat - ${project.name}`}
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', project.hash_id || project.id) },
                { label: 'Chat' },
            ]}
        >
            <div className="max-w-4xl mx-auto h-[calc(100vh-140px)] flex flex-col bg-card rounded-xl border border-border/80 shadow-2xs overflow-hidden font-mono">
                {/* Chat Header */}
                <div className="p-4 border-b border-border bg-muted/30 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <MessageSquare className="size-5 text-primary" />
                        <div>
                            <h2 className="font-bold text-sm text-foreground">{project.name}</h2>
                            <p className="text-[11px] text-muted-foreground">PS: {project.ps_number} • Direct Manager & Field Tech Chat</p>
                        </div>
                    </div>
                </div>

                {/* Messages Feed */}
                <div className="flex-1 p-4 overflow-y-auto space-y-3">
                    {messages.length === 0 ? (
                        <div className="h-full flex items-center justify-center text-xs text-muted-foreground">
                            No messages in this project stream. Start the conversation below.
                        </div>
                    ) : (
                        messages.map((msg) => (
                            <div
                                key={msg.id}
                                className={`flex flex-col max-w-[80%] space-y-1 ${
                                    msg.is_me ? 'ml-auto items-end' : 'mr-auto items-start'
                                }`}
                            >
                                <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                                    <User className="size-3" /> {msg.sender_name} • {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                </span>
                                <div
                                    className={`p-3 rounded-xl text-xs leading-relaxed ${
                                        msg.is_me
                                            ? 'bg-primary text-primary-foreground rounded-tr-none'
                                            : 'bg-muted/60 text-foreground border border-border/60 rounded-tl-none'
                                    }`}
                                >
                                    {msg.message}
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* Input Bar */}
                <form onSubmit={handleSubmit} className="p-3 border-t border-border bg-card flex items-center gap-2">
                    <input
                        type="text"
                        value={form.data.message}
                        onChange={(e) => form.setData('message', e.target.value)}
                        placeholder="Type a message to project team..."
                        className="flex-1 px-3 py-2 text-xs rounded-lg border border-border bg-card focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                    <Button type="submit" size="sm" disabled={form.processing || !form.data.message.trim()} className="gap-1.5">
                        <Send className="size-3.5" /> Send
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

export default ChatIndex;
