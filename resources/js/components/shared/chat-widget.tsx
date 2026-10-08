import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { MessageSquare, Send, Smile } from 'lucide-react';
import * as React from 'react';

export interface ChatMessageData {
    id: number;
    senderName: string;
    senderRole: string;
    message: string;
    createdAt: string;
    isSelf?: boolean;
}

export interface ChatWidgetProps {
    messages: ChatMessageData[];
    onSendMessage: (message: string) => void;
    loading?: boolean;
    className?: string;
}

const QUICK_EMOJIS = ['👍', '👏', '✅', '⚠️', '📷', '🏗️', '📄', '🔧', '🚧', '☑️', '📍', '🚚'];

export function ChatWidget({
    messages,
    onSendMessage,
    loading = false,
    className,
}: Readonly<ChatWidgetProps>) {
    const [text, setText] = React.useState('');
    const [showEmojis, setShowEmojis] = React.useState(false);
    const scrollRef = React.useRef<HTMLDivElement | null>(null);
    const nearBottomRef = React.useRef(true);

    React.useEffect(() => {
        const el = scrollRef.current;
        if (!el) {
            return;
        }
        const onScroll = () => {
            nearBottomRef.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
        };
        el.addEventListener('scroll', onScroll);
        return () => el.removeEventListener('scroll', onScroll);
    }, []);

    React.useEffect(() => {
        const el = scrollRef.current;
        if (el && nearBottomRef.current) {
            el.scrollTop = el.scrollHeight;
        }
    }, [messages]);

    const handleSubmit = (event: React.SyntheticEvent) => {
        event.preventDefault();
        if (text.trim()) {
            onSendMessage(text.trim());
            setText('');
        }
    };

    const handleEmoji = (emoji: string) => {
        setText((prev) => `${prev}${emoji}`);
        setShowEmojis(false);
    };

    return (
        <Card className={cn('flex flex-col h-[480px] shadow-sm', className)}>
            <CardHeader className="py-3 px-4 border-b bg-muted/30">
                <CardTitle className="text-sm font-bold flex items-center gap-2">
                    <MessageSquare className="size-4 text-primary" /> Project Site Chat
                </CardTitle>
            </CardHeader>

            <CardContent className="flex-1 flex flex-col justify-between p-0 overflow-hidden">
                {/* Messages List */}
                <div
                    ref={scrollRef}
                    className="flex-1 overflow-y-auto no-scrollbar p-4 space-y-3"
                >
                    {messages.length === 0 && loading && (
                        <div className="space-y-4">
                            {[0, 1, 2].map((index) => (
                                <div
                                    key={index}
                                    className={cn(
                                        'flex flex-col gap-1.5',
                                        index % 2 === 0 ? 'items-start' : 'items-end'
                                    )}
                                >
                                    <div className="h-2.5 w-16 rounded-full bg-muted animate-pulse" />
                                    <div
                                        className={cn(
                                            'h-10 rounded-2xl bg-muted animate-pulse',
                                            index % 2 === 0
                                                ? 'w-3/5 rounded-bl-none'
                                                : 'w-1/2 rounded-br-none'
                                        )}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    {messages.length === 0 && !loading && (
                        <p className="text-center text-xs text-muted-foreground pt-12">
                            No messages yet. Send a message to start conversation with field tech /
                            manager.
                        </p>
                    )}
                    {messages.length > 0 && (
                        messages.map((msg) => (
                            <div
                                key={msg.id}
                                className={cn(
                                    'flex flex-col max-w-[80%] space-y-1',
                                    msg.isSelf ? 'ml-auto items-end' : 'mr-auto items-start'
                                )}
                            >
                                <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground px-1">
                                    <span className="font-semibold">{msg.senderName}</span>
                                    <span>•</span>
                                    <span>{msg.createdAt}</span>
                                </div>
                                <div
                                    className={cn(
                                        'p-3 rounded-2xl text-xs leading-relaxed break-words',
                                        msg.isSelf
                                            ? 'bg-primary text-primary-foreground rounded-br-none'
                                            : 'bg-muted text-foreground rounded-bl-none'
                                    )}
                                >
                                    {msg.message}
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* Emoji Picker */}
                {showEmojis && (
                    <div className="flex flex-wrap gap-1 px-3 pt-2 border-t bg-card">
                        {QUICK_EMOJIS.map((emoji) => (
                            <button
                                key={emoji}
                                type="button"
                                onClick={() => handleEmoji(emoji)}
                                className="size-8 rounded-lg text-base hover:bg-accent transition-colors"
                                aria-label={`Insert ${emoji}`}
                            >
                                {emoji}
                            </button>
                        ))}
                    </div>
                )}

                {/* Input Bar */}
                <form onSubmit={handleSubmit} className="p-3 border-t bg-card flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setShowEmojis(!showEmojis)}
                        className="h-9 px-2"
                        aria-label="Insert emoji"
                        aria-expanded={showEmojis}
                    >
                        <Smile className="size-4" />
                    </Button>
                    <Input
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        placeholder="Type a message..."
                        className="h-9 text-xs"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        loading={loading}
                        disabled={!text.trim()}
                        className="h-9 px-3"
                        aria-label="Send message"
                    >
                        <Send className="size-3.5" />
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default ChatWidget;
