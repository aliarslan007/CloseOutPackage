import { create } from 'zustand';
import type { MediaAsset, QueuedFile } from '../types/media-library.types';

export interface UploadQueueState {
    files: QueuedFile[];
    isPaused: boolean;
    isDrawerOpen: boolean;
    isMinimized: boolean;
    addFiles: (
        newFiles: File[],
        projectId?: number | null,
        marketId?: number | null,
    ) => QueuedFile[];
    updateFile: (id: string, patch: Partial<QueuedFile>) => void;
    removeFile: (id: string) => void;
    retryFile: (id: string) => void;
    retryFailed: () => void;
    cancelFile: (id: string) => void;
    cancelAll: () => void;
    pause: () => void;
    resume: () => void;
    setDrawerOpen: (isOpen: boolean) => void;
    toggleMinimize: () => void;
    clearCompleted: () => void;
}

function generateId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    const arr = new Uint32Array(2);
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        crypto.getRandomValues(arr);
    } else {
        arr[0] = Date.now();
        arr[1] = Date.now() ^ 0x5a5a5a;
    }
    return `${Date.now()}-${arr[0].toString(36)}-${arr[1].toString(36)}`;
}

type AssetCompletedCallback = (asset: MediaAsset) => void;
const assetCompletedListeners = new Set<AssetCompletedCallback>();

export function registerAssetCompletedListener(cb: AssetCompletedCallback): () => void {
    assetCompletedListeners.add(cb);
    return () => {
        assetCompletedListeners.delete(cb);
    };
}

export function notifyAssetCompleted(asset: MediaAsset): void {
    assetCompletedListeners.forEach((cb) => {
        try {
            cb(asset);
        } catch {
            // Ignore subscriber errors
        }
    });
}

export const useUploadQueueStore = create<UploadQueueState>((set, get) => ({
    files: [],
    isPaused: false,
    isDrawerOpen: false,
    isMinimized: false,

    addFiles: (newFiles, projectId = null, marketId = null) => {
        const queued: QueuedFile[] = newFiles.map((file) => ({
            id: generateId(),
            file,
            status: 'pending',
            progress: 0,
            retryCount: 0,
            projectId,
            marketId,
        }));

        set((state) => ({
            files: [...state.files, ...queued],
            isDrawerOpen: true,
            isMinimized: false,
        }));

        return queued;
    },

    updateFile: (id, patch) => {
        set((state) => ({
            files: state.files.map((item) => {
                if (item.id !== id) return item;
                return { ...item, ...patch };
            }),
        }));
    },

    removeFile: (id) => {
        const file = get().files.find((f) => f.id === id);
        if (file?.previewUrl) {
            URL.revokeObjectURL(file.previewUrl);
        }

        set((state) => {
            const nextFiles = state.files.filter((f) => f.id !== id);
            return {
                files: nextFiles,
                isDrawerOpen: nextFiles.length > 0 ? state.isDrawerOpen : false,
            };
        });
    },

    retryFile: (id) => {
        set((state) => ({
            files: state.files.map((item) => {
                if (item.id !== id) return item;
                return {
                    ...item,
                    status: 'pending',
                    progress: 0,
                    error: undefined,
                    retryCount: 0,
                };
            }),
            isPaused: false,
            isDrawerOpen: true,
        }));
    },

    retryFailed: () => {
        set((state) => ({
            files: state.files.map((item) => {
                if (item.status !== 'failed') return item;
                return {
                    ...item,
                    status: 'pending',
                    progress: 0,
                    error: undefined,
                    retryCount: 0,
                };
            }),
            isPaused: false,
        }));
    },

    cancelFile: (id) => {
        const file = get().files.find((f) => f.id === id);
        if (file?.previewUrl) {
            URL.revokeObjectURL(file.previewUrl);
        }

        set((state) => ({
            files: state.files.map((item) => {
                if (item.id !== id) return item;
                return {
                    ...item,
                    status: 'aborted',
                    error: 'Upload cancelled.',
                };
            }),
        }));
    },

    cancelAll: () => {
        get().files.forEach((f) => {
            if (f.previewUrl) {
                URL.revokeObjectURL(f.previewUrl);
            }
        });

        set((state) => ({
            files: state.files.map((item) => {
                if (item.status === 'done') return item;
                return {
                    ...item,
                    status: 'aborted',
                    error: 'Cancelled.',
                };
            }),
        }));
    },

    pause: () => set({ isPaused: true }),
    resume: () => set({ isPaused: false }),

    setDrawerOpen: (isOpen) => set({ isDrawerOpen: isOpen }),
    toggleMinimize: () => set((state) => ({ isMinimized: !state.isMinimized })),

    clearCompleted: () => {
        const completed = get().files.filter((f) => f.status === 'done' || f.status === 'aborted');
        completed.forEach((f) => {
            if (f.previewUrl) {
                URL.revokeObjectURL(f.previewUrl);
            }
        });

        set((state) => {
            const nextFiles = state.files.filter((f) => f.status !== 'done' && f.status !== 'aborted');
            return {
                files: nextFiles,
                isDrawerOpen: nextFiles.length > 0 ? state.isDrawerOpen : false,
            };
        });
    },
}));
