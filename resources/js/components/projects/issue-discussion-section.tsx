import { preparePhotoForUpload } from '@/services/compression.service';
import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    FileVideo,
    ImagePlus,
    Loader2,
    Paperclip,
    Search,
    Send,
    Smile,
    Sparkles,
    UploadCloud,
    Users,
    X,
} from 'lucide-react';
import * as React from 'react';
import { IssueAttachmentViewer } from './issue-attachment-viewer';
import { IssueComment, IssueItem, ProjectStakeholder, QUICK_REPLY_CHIPS } from './issue-types';

export interface IssueDiscussionSectionProps {
    projectId: number | string;
    issue: IssueItem;
    canComment: boolean;
    currentUserId?: number;
    currentUserPhotoUrl?: string | null;
    currentUserName?: string | null;
    manager?: ProjectStakeholder | null;
    managers?: ProjectStakeholder[];
    fieldSupervisors?: ProjectStakeholder[];
}

const COMMON_EMOJIS = [
    '👍',
    '👌',
    '📍',
    '🔑',
    '🚧',
    '✅',
    '⚠️',
    '📷',
    '📞',
    '⏳',
    '🔒',
    '🛠️',
    '🚨',
    '📋',
    '⚡',
    '🏢',
    '🏗️',
    '🤝',
];

const MAX_FILE_SIZE_MB = 5;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024; // 5 MB

function getInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatFeedTime(isoString: string): string {
    try {
        const date = new Date(isoString);
        return date.toLocaleTimeString(undefined, {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return isoString;
    }
}

function formatDateTime(isoString: string): string {
    try {
        const date = new Date(isoString);
        return date.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return isoString;
    }
}

function renderRoleBadge(roleKey?: string, isSelf?: boolean) {
    if (isSelf) {
        return (
            <span className="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide bg-amber-600 text-white shadow-2xs">
                You
            </span>
        );
    }

    switch (roleKey) {
        case 'admin':
            return (
                <span className="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide bg-rose-600 text-white shadow-2xs">
                    Admin
                </span>
            );
        case 'manager':
            return (
                <span className="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide bg-blue-600 text-white shadow-2xs">
                    Manager
                </span>
            );
        case 'field_supervisor':
            return (
                <span className="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide bg-emerald-600 text-white shadow-2xs">
                    Field Tech
                </span>
            );
        case 'customer':
            return (
                <span className="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide bg-purple-600 text-white shadow-2xs">
                    Customer
                </span>
            );
        default:
            return (
                <span className="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide bg-muted text-muted-foreground border border-border shadow-2xs">
                    {roleKey ?? 'User'}
                </span>
            );
    }
}

interface CommentRowProps {
    comment: IssueComment;
    isSelf: boolean;
    currentUserPhotoUrl?: string | null;
    currentUserName?: string | null;
}

const CommentRow = React.memo(function CommentRow({
    comment,
    isSelf,
    currentUserPhotoUrl,
    currentUserName,
}: Readonly<CommentRowProps>) {
    let photoUrl = comment.author?.profile_photo_url;
    let displayName = comment.author?.name || 'User';

    if (isSelf) {
        photoUrl = comment.author?.profile_photo_url || currentUserPhotoUrl;
        displayName = currentUserName || comment.author?.name || 'You';
    }

    const roleKey = comment.author?.roles?.[0]?.name;

    return (
        <div className="flex items-start gap-3 py-2">
            {photoUrl ? (
                <img
                    src={photoUrl}
                    alt={displayName}
                    className="w-8 h-8 min-w-8 min-h-8 max-w-8 max-h-8 rounded-full object-cover border border-border/80 shrink-0 mt-0.5"
                    loading="lazy"
                />
            ) : (
                <div className="flex w-8 h-8 min-w-8 min-h-8 max-w-8 max-h-8 items-center justify-center rounded-full bg-muted text-xs font-bold text-muted-foreground border border-border shrink-0 mt-0.5">
                    {getInitials(displayName)}
                </div>
            )}

            <div className="flex-1 min-w-0 space-y-1">
                {/* Header Line: Name + Role Badge on Left, Timestamp on Right */}
                <div className="flex items-center justify-between gap-2 flex-wrap">
                    <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="text-xs font-bold text-foreground">{displayName}</span>
                        {renderRoleBadge(roleKey, isSelf)}
                    </div>
                    <span className="text-[11px] text-muted-foreground font-medium shrink-0 ml-auto">
                        {formatFeedTime(comment.created_at)}
                    </span>
                </div>

                {/* Message Body */}
                <p className="text-xs text-foreground/90 leading-relaxed whitespace-pre-wrap break-words">
                    {comment.body}
                </p>

                {/* Attached Media Previews */}
                {comment.attachments && comment.attachments.length > 0 && (
                    <div className="pt-1">
                        <IssueAttachmentViewer attachments={comment.attachments} />
                    </div>
                )}
            </div>
        </div>
    );
});

export function IssueDiscussionSection({
    projectId,
    issue,
    canComment,
    currentUserId,
    currentUserPhotoUrl,
    currentUserName,
    manager,
    managers,
    fieldSupervisors = [],
}: Readonly<IssueDiscussionSectionProps>) {
    const [body, setBody] = React.useState('');
    const [attachments, setAttachments] = React.useState<File[]>([]);
    const [showQuickReplies, setShowQuickReplies] = React.useState(false);
    const [showEmojiPicker, setShowEmojiPicker] = React.useState(false);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [processing, setProcessing] = React.useState(false);
    const [compressing, setCompressing] = React.useState(false);
    const [isDragging, setIsDragging] = React.useState(false);
    const [errorMessage, setErrorMessage] = React.useState<string | null>(null);

    const dragCounter = React.useRef(0);
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);
    const fileInputRef = React.useRef<HTMLInputElement>(null);
    const imageInputRef = React.useRef<HTMLInputElement>(null);
    const emojiPickerRef = React.useRef<HTMLDivElement>(null);
    const emojiButtonRef = React.useRef<HTMLButtonElement>(null);
    const feedEndRef = React.useRef<HTMLDivElement>(null);
    const isResolved = Boolean(issue.resolved_at);

    // Auto-scroll on new comments
    React.useEffect(() => {
        feedEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [issue.comments.length]);

    // Close emoji picker when clicking outside
    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Node;
            if (
                emojiPickerRef.current &&
                !emojiPickerRef.current.contains(target) &&
                emojiButtonRef.current &&
                !emojiButtonRef.current.contains(target)
            ) {
                setShowEmojiPicker(false);
            }
        };
        if (showEmojiPicker) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [showEmojiPicker]);

    const processSelectedFiles = async (files: File[]) => {
        setErrorMessage(null);
        const validFiles: File[] = [];

        for (const file of files) {
            if (file.size > MAX_FILE_SIZE_BYTES) {
                setErrorMessage(`"${file.name}" exceeds the ${MAX_FILE_SIZE_MB}MB limit and was skipped.`);
                continue;
            }
            validFiles.push(file);
        }

        if (validFiles.length === 0) return;

        setCompressing(true);
        try {
            const processedFiles: File[] = [];
            for (const file of validFiles) {
                if (file.type.startsWith('image/')) {
                    const prepared = await preparePhotoForUpload(file, {
                        maxMb: 15,
                        maxSideLength: 1920,
                    });
                    const finalFile = prepared.ok ? prepared.file : file;
                    if (finalFile.size > MAX_FILE_SIZE_BYTES) {
                        setErrorMessage(`"${file.name}" exceeds the ${MAX_FILE_SIZE_MB}MB limit after compression and was skipped.`);
                        continue;
                    }
                    processedFiles.push(finalFile);
                } else {
                    processedFiles.push(file);
                }
            }
            setAttachments((prev) => [...prev, ...processedFiles].slice(0, 10));
        } catch {
            setAttachments((prev) => [...prev, ...validFiles].slice(0, 10));
        } finally {
            setCompressing(false);
        }
    };

    const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files || e.target.files.length === 0) return;
        const selectedFiles = Array.from(e.target.files);
        e.target.value = '';
        await processSelectedFiles(selectedFiles);
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current += 1;
        if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
            setIsDragging(true);
        }
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current -= 1;
        if (dragCounter.current <= 0) {
            dragCounter.current = 0;
            setIsDragging(false);
        }
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = async (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current = 0;
        setIsDragging(false);

        if (!e.dataTransfer.files || e.dataTransfer.files.length === 0) return;
        const droppedFiles = Array.from(e.dataTransfer.files);
        await processSelectedFiles(droppedFiles);
    };

    const handleRemoveFile = (index: number) => {
        setAttachments((prev) => prev.filter((_, i) => i !== index));
    };

    const handleAddEmoji = (emoji: string) => {
        setBody((prev) => prev + emoji);
        setShowEmojiPicker(false);
        if (textareaRef.current) textareaRef.current.focus();
    };

    const handleSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        const trimmed = body.trim();
        if ((!trimmed && attachments.length === 0) || processing || compressing || isResolved) {
            return;
        }

        setProcessing(true);
        router.post(
            route('projects.issues.comments.store', [projectId, issue.hash_id || issue.id]),
            { body: trimmed || 'Sent attachment', attachments },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setBody('');
                    setAttachments([]);
                    setShowEmojiPicker(false);
                    setShowQuickReplies(false);
                    setErrorMessage(null);
                    if (textareaRef.current) textareaRef.current.focus();
                    feedEndRef.current?.scrollIntoView({ behavior: 'smooth' });
                },
                onError: (errors) => {
                    const firstError = Object.values(errors).find(Boolean);
                    if (firstError) {
                        setErrorMessage(firstError);
                    }
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    const handleQuickChip = (chipText: string) => {
        setBody(chipText);
        setShowQuickReplies(false);
        if (textareaRef.current) textareaRef.current.focus();
    };

    const isBusy = processing || compressing;

    // Support both single manager prop or array of managers
    const allManagers = React.useMemo(() => {
        if (managers && managers.length > 0) return managers;
        if (manager) return [manager];
        return [];
    }, [manager, managers]);

    // High-performance search and filter memoization
    const normalizedQuery = searchQuery.trim().toLowerCase();
    const filteredComments = React.useMemo(() => {
        let result = issue.comments;

        if (normalizedQuery) {
            result = result.filter((c) => {
                const bodyMatch = c.body?.toLowerCase().includes(normalizedQuery);
                const authorMatch = c.author?.name?.toLowerCase().includes(normalizedQuery);
                return Boolean(bodyMatch || authorMatch);
            });
        }

        return result;
    }, [issue.comments, normalizedQuery]);

    return (
        <div className="w-full rounded-md border border-border/80 bg-card p-4 sm:p-5 shadow-2xs space-y-3 flex flex-col">
            {/* Top Bar: Activity & Comments + Search */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2.5 border-b border-border/60">
                <div className="flex items-center gap-2">
                    <h3 className="text-sm font-bold text-foreground">
                        Activity & Comments ({issue.comments.length})
                    </h3>
                </div>

                <div className="flex items-center gap-2 w-full sm:w-auto sm:ml-auto">
                    {/* Search Input */}
                    <div className="relative flex-1 sm:flex-initial">
                        <Search className="size-3.5 text-muted-foreground absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search comments..."
                            className="h-8 pl-8 pr-3 text-xs rounded-lg bg-muted/40 border border-border/70 text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary w-full sm:w-36 md:w-44 transition-all"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery('')}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-3" />
                            </button>
                        )}
                    </div>

                </div>
            </div>

            {/* Team Involved Header Banner in Discussion Feed */}
            <div className="flex items-center gap-1.5 sm:gap-2 flex-wrap text-xs text-muted-foreground pb-2 border-b border-border/40">
                <span className="font-bold text-foreground text-[11px] flex items-center gap-1 shrink-0">
                    <Users className="size-3 text-muted-foreground" /> Team:
                </span>
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-700 dark:text-rose-400 text-[10px] font-semibold border border-rose-500/20 shrink-0">
                    Admin: System Admin
                </span>
                {allManagers.length > 0 ? (
                    allManagers.map((m) => (
                        <span
                            key={m.id}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-700 dark:text-blue-400 text-[10px] font-semibold border border-blue-500/20 truncate max-w-[150px] shrink-0"
                        >
                            PM: {m.name}
                        </span>
                    ))
                ) : (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-muted/60 text-muted-foreground text-[10px] font-medium border border-border/50 shrink-0">
                        No PM
                    </span>
                )}
                {fieldSupervisors.length > 0 ? (
                    fieldSupervisors.map((tech) => (
                        <span
                            key={tech.id}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-[10px] font-semibold border border-emerald-500/20 truncate max-w-[150px] shrink-0"
                        >
                            Tech: {tech.name}
                        </span>
                    ))
                ) : (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-muted/60 text-muted-foreground text-[10px] font-medium border border-border/50 shrink-0">
                        No Field Techs
                    </span>
                )}
            </div>

            {/* Timeline Stream Feed */}
            <div className="space-y-3 max-h-[440px] overflow-y-auto pr-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden divide-y divide-border/40">
                {/* Comment Rows */}
                {filteredComments.map((comment) => {
                    const isSelf = Boolean(
                        currentUserId &&
                        (comment.author?.id === currentUserId || comment.user_id === currentUserId)
                    );
                    return (
                        <CommentRow
                            key={comment.id}
                            comment={comment}
                            isSelf={isSelf}
                            currentUserPhotoUrl={currentUserPhotoUrl}
                            currentUserName={currentUserName}
                        />
                    );
                })}

                {filteredComments.length === 0 && issue.comments.length > 0 && (
                    <div className="py-6 text-center text-xs text-muted-foreground">
                        No comments match your search.
                    </div>
                )}

                {/* System Log: Resolved event if resolved */}
                {isResolved && issue.resolved_at && (
                    <div className="py-2.5 px-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800 dark:text-emerald-300 flex items-center justify-between gap-2 mt-2">
                        <div className="space-y-0.5">
                            <span className="font-bold text-emerald-950 dark:text-emerald-200 text-[11px] flex items-center gap-1.5">
                                <CheckCircle2 className="size-3.5 text-emerald-600" />
                                System Log
                            </span>
                            <p className="italic text-[11px]">
                                *Status changed to 'Resolved' on {formatDateTime(issue.resolved_at)}
                                .*
                            </p>
                        </div>
                        <span className="text-[10px] text-emerald-700 dark:text-emerald-400 shrink-0 font-medium">
                            {formatFeedTime(issue.resolved_at)}
                        </span>
                    </div>
                )}

                <div ref={feedEndRef} />
            </div>

            {/* Bottom Section: Add Comment */}
            {!isResolved && canComment && (
                <div className="pt-2 border-t border-border/60 space-y-2">
                    <span className="text-xs font-bold text-foreground block">Add Comment</span>

                    {/* Composer Box with Drag and Drop Support */}
                    <div
                        role="region"
                        aria-label="Comment composer and drop zone"
                        onDragEnter={handleDragEnter}
                        onDragOver={handleDragOver}
                        onDragLeave={handleDragLeave}
                        onDrop={handleDrop}
                        className="relative rounded-xl border border-border/80 bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary/50 transition-all"
                    >
                        {/* Drag and Drop Visual Overlay (pointer-events-none prevents drag shake) */}
                        {isDragging && (
                            <div className="absolute inset-0 z-40 bg-primary/10 backdrop-blur-2xs border-2 border-dashed border-primary rounded-xl flex flex-col items-center justify-center text-primary text-xs font-semibold gap-1 animate-in fade-in duration-100 pointer-events-none">
                                <UploadCloud className="size-6 animate-bounce" />
                                <span>Drop photos or videos here (up to 10MB)</span>
                            </div>
                        )}

                        {/* Quick Replies Drawer inside / above composer */}
                        {showQuickReplies && (
                            <div className="flex items-center gap-1.5 flex-wrap p-2.5 bg-muted/40 border-b border-border/60 animate-in fade-in slide-in-from-top-1 duration-150">
                                {QUICK_REPLY_CHIPS.map((chip) => (
                                    <button
                                        key={chip}
                                        type="button"
                                        onClick={() => handleQuickChip(chip)}
                                        className="px-2.5 py-0.5 rounded-full text-xs font-medium bg-card border border-border/80 text-foreground hover:bg-foreground hover:text-background transition-all shadow-2xs"
                                    >
                                        {chip}
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Uploading / Compressing Progress Banner */}
                        {isBusy && (
                            <div className="flex items-center gap-2 px-3 py-1.5 bg-primary/10 border-b border-primary/20 text-primary text-xs font-medium animate-in fade-in duration-150">
                                <svg
                                    className="size-3.5 animate-spin text-primary shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    />
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v8H4z"
                                    />
                                </svg>
                                <span className="font-semibold">
                                    {compressing
                                        ? 'Optimizing & compressing media...'
                                        : 'Uploading comment & attachments, please wait...'}
                                </span>
                            </div>
                        )}

                        {/* Error Notification */}
                        {errorMessage && (
                            <div className="flex items-center justify-between gap-2 px-3 py-1.5 bg-destructive/10 border-b border-destructive/20 text-destructive text-xs font-medium">
                                <span>{errorMessage}</span>
                                <button
                                    type="button"
                                    onClick={() => setErrorMessage(null)}
                                    className="text-destructive/80 hover:text-destructive"
                                >
                                    <X className="size-3" />
                                </button>
                            </div>
                        )}

                        {/* Staged Attachments Tray */}
                        {attachments.length > 0 && (
                            <div className="flex items-center gap-2 flex-wrap p-2 bg-muted/30 border-b border-border/60">
                                {attachments.map((file, idx) => (
                                    <div
                                        key={`${file.name}-${idx}`}
                                        className="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-card text-xs font-medium text-foreground border border-border/60 shadow-2xs"
                                    >
                                        {file.type.startsWith('video/') ? (
                                            <FileVideo className="size-3 text-blue-500 shrink-0" />
                                        ) : (
                                            <ImagePlus className="size-3 text-emerald-500 shrink-0" />
                                        )}
                                        <span className="max-w-[120px] truncate text-[11px]">
                                            {file.name}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveFile(idx)}
                                            disabled={isBusy}
                                            className="size-3.5 rounded-full flex items-center justify-center hover:bg-destructive/20 hover:text-destructive"
                                            title="Remove attachment"
                                        >
                                            <X className="size-2.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Textarea */}
                        <textarea
                            ref={textareaRef}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                            placeholder="Write a comment..."
                            disabled={isBusy}
                            rows={2}
                            maxLength={2000}
                            className="w-full p-3 text-xs bg-transparent border-0 focus:ring-0 focus:outline-none ring-0 shadow-none outline-none resize-none placeholder:text-muted-foreground text-foreground"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    handleSubmit();
                                }
                            }}
                        />

                        {/* Bottom Action Toolbar */}
                        <div className="relative flex items-center justify-between gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-2 bg-muted/20 border-t border-border/50 rounded-b-xl">
                            {/* Hidden file inputs */}
                            <input
                                type="file"
                                ref={fileInputRef}
                                onChange={handleFileChange}
                                multiple
                                disabled={isBusy}
                                accept="image/*,video/*,.mp4,.mov,.avi,.webm,.mkv,.heic,.heif"
                                className="hidden"
                            />
                            <input
                                type="file"
                                ref={imageInputRef}
                                onChange={handleFileChange}
                                multiple
                                disabled={isBusy}
                                accept="image/*,.heic,.heif,.jpg,.jpeg,.png,.webp"
                                className="hidden"
                            />

                            {/* Toolbar buttons */}
                            <div className="flex items-center gap-0.5 sm:gap-1.5 flex-nowrap shrink-0 overflow-x-auto [scrollbar-width:none]">
                                <button
                                    type="button"
                                    onClick={() => fileInputRef.current?.click()}
                                    disabled={isBusy}
                                    className="inline-flex items-center gap-1 p-1.5 sm:px-2.5 sm:py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50"
                                    title="Attach files (up to 10MB)"
                                >
                                    <Paperclip className="size-3.5" />
                                    <span className="hidden sm:inline">Attach</span>
                                </button>

                                {/* Emoji Trigger */}
                                <button
                                    ref={emojiButtonRef}
                                    type="button"
                                    onClick={() => setShowEmojiPicker(!showEmojiPicker)}
                                    disabled={isBusy}
                                    className="inline-flex items-center gap-1 p-1.5 sm:px-2.5 sm:py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50"
                                    title="Insert Emoji"
                                >
                                    <Smile className="size-3.5" />
                                    <span className="hidden sm:inline">Emoji</span>
                                </button>

                            {/* Emoji Picker - outside overflow container so it's not clipped */}
                            {showEmojiPicker && (
                                <div
                                    ref={emojiPickerRef}
                                    className="absolute bottom-full mb-2 left-2.5 sm:left-3 z-50 p-2.5 rounded-lg bg-card text-foreground border border-border shadow-2xl grid grid-cols-6 gap-1 w-56 sm:w-64 max-w-[calc(100vw-3rem)] animate-in fade-in zoom-in-95 duration-150"
                                >
                                    {COMMON_EMOJIS.map((emoji) => (
                                        <button
                                            key={emoji}
                                            type="button"
                                            onClick={() => handleAddEmoji(emoji)}
                                            className="size-8 rounded-md hover:bg-muted flex items-center justify-center text-base transition-colors"
                                        >
                                            {emoji}
                                        </button>
                                    ))}
                                </div>
                            )}

                            <button
                                type="button"
                                onClick={() => imageInputRef.current?.click()}
                                disabled={isBusy}
                                className="inline-flex items-center gap-1 p-1.5 sm:px-2.5 sm:py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50"
                                title="Upload Image"
                            >
                                <ImagePlus className="size-3.5" />
                                <span className="hidden sm:inline">Image</span>
                            </button>

                                <button
                                    type="button"
                                    onClick={() => setShowQuickReplies(!showQuickReplies)}
                                    disabled={isBusy}
                                    className="inline-flex items-center gap-1 p-1.5 sm:px-2.5 sm:py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50"
                                    title="Quick Replies"
                                >
                                    <Sparkles className="size-3.5 text-amber-500" />
                                    <span className="hidden md:inline">Quick Replies</span>
                                </button>
                            </div>

                            {/* Post Comment Button */}
                            <button
                                type="button"
                                onClick={() => handleSubmit()}
                                disabled={isBusy || (!body.trim() && attachments.length === 0)}
                                className="inline-flex items-center justify-center gap-1.5 px-3 sm:px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-bold uppercase tracking-wider transition-colors shadow-2xs shrink-0 ml-auto"
                            >
                                {isBusy ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Send className="size-3" />
                                )}
                                <span className="hidden sm:inline">POST COMMENT</span>
                                <span className="sm:hidden">POST</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
