import { create } from 'zustand';

export interface UploadQueueItem {
    id: string;
    file: File;
    packageId: number;
    stageId: number;
    categoryId: number;
    status: 'queued' | 'uploading' | 'completed' | 'failed';
    progress: number;
    error?: string;
    previewUrl?: string;
}

export interface UploadQueueState {
    items: UploadQueueItem[];
    addItem: (item: Omit<UploadQueueItem, 'id' | 'status' | 'progress'>) => void;
    removeItem: (id: string) => void;
    updateProgress: (id: string, progress: number) => void;
    setStatus: (id: string, status: UploadQueueItem['status'], error?: string) => void;
    clearCompleted: () => void;
}

export const useUploadQueueStore = create<UploadQueueState>()((set) => ({
    items: [],
    addItem: (item: Omit<UploadQueueItem, 'id' | 'status' | 'progress'>) => {
        const id = `upload_${crypto.randomUUID()}`;
        const previewUrl = item.file.type.startsWith('image/')
            ? URL.createObjectURL(item.file)
            : undefined;

        set((state: UploadQueueState) => ({
            items: [
                ...state.items,
                {
                    ...item,
                    id,
                    status: 'queued',
                    progress: 0,
                    previewUrl,
                },
            ],
        }));
    },
    removeItem: (id: string) =>
        set((state: UploadQueueState) => ({
            items: state.items.filter((item: UploadQueueItem) => item.id !== id),
        })),
    updateProgress: (id: string, progress: number) =>
        set((state: UploadQueueState) => ({
            items: state.items.map((item: UploadQueueItem) =>
                item.id === id ? { ...item, progress, status: 'uploading' } : item
            ),
        })),
    setStatus: (id: string, status: UploadQueueItem['status'], error?: string) =>
        set((state: UploadQueueState) => ({
            items: state.items.map((item: UploadQueueItem) =>
                item.id === id ? { ...item, status, error } : item
            ),
        })),
    clearCompleted: () =>
        set((state: UploadQueueState) => ({
            items: state.items.filter((item: UploadQueueItem) => item.status !== 'completed'),
        })),
}));

export default useUploadQueueStore;
