import { LoggerService } from '@/services/logger.service';
import { useCallback, useEffect, useState } from 'react';

export type DraftStatus = 'saved' | 'saving' | 'unsaved' | 'offline_saved';

export interface UseDraftOptions<T> {
    key: string;
    initialData: T;
    onSave?: (data: T) => Promise<void>;
    debounceMs?: number;
}

export function useDraft<T>({ key, initialData, onSave, debounceMs = 3000 }: UseDraftOptions<T>) {
    const [data, setData] = useState<T>(() => {
        try {
            const saved = localStorage.getItem(`cop_draft_${key}`);
            return saved ? JSON.parse(saved) : initialData;
        } catch {
            return initialData;
        }
    });

    const [status, setStatus] = useState<DraftStatus>('saved');
    const [lastSavedTime, setLastSavedTime] = useState<Date | null>(null);
    const [lastSavedData, setLastSavedData] = useState<string>(() => JSON.stringify(data));

    const saveDraft = useCallback(
        async (currentData: T) => {
            setStatus('saving');
            try {
                localStorage.setItem(`cop_draft_${key}`, JSON.stringify(currentData));
                if (onSave && navigator.onLine) {
                    await onSave(currentData);
                }
                setStatus(navigator.onLine ? 'saved' : 'offline_saved');
                setLastSavedData(JSON.stringify(currentData));
                setLastSavedTime(new Date());
            } catch (err) {
                LoggerService.error('use-draft', err);
                setStatus('unsaved');
            }
        },
        [key, onSave]
    );

    useEffect(() => {
        const currentDataString = JSON.stringify(data);
        if (currentDataString === lastSavedData) {
            return;
        }

        setStatus('unsaved');
        const timer = setTimeout(() => {
            saveDraft(data);
        }, debounceMs);

        return () => clearTimeout(timer);
    }, [data, debounceMs, saveDraft, lastSavedData]);

    const clearDraft = useCallback(() => {
        localStorage.removeItem(`cop_draft_${key}`);
        setStatus('saved');
    }, [key]);

    return {
        data,
        setData,
        status,
        lastSavedTime,
        saveDraft: () => saveDraft(data),
        clearDraft,
    };
}

export default useDraft;
