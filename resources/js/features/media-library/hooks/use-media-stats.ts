import { useCallback, useEffect, useState } from 'react';
import { mediaLibraryApi } from '../api/media-library.api';
import type { MediaStatsResponse } from '../types/media-library.types';

export function useMediaStats(userId?: number) {
    const [stats, setStats] = useState<MediaStatsResponse | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<Error | null>(null);

    const fetchStats = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await mediaLibraryApi.getStats(userId);
            setStats(data);
        } catch (err: unknown) {
            setError(err instanceof Error ? err : new Error('Failed to load storage stats.'));
        } finally {
            setIsLoading(false);
        }
    }, [userId]);

    useEffect(() => {
        fetchStats();
    }, [fetchStats]);

    return { stats, isLoading, error, refetch: fetchStats };
}
