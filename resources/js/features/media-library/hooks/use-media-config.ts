import { useEffect, useState } from 'react';
import { mediaLibraryApi } from '../api/media-library.api';
import type { MediaLibraryConfig } from '../types/media-library.types';

const DEFAULT_CONFIG: MediaLibraryConfig = {
    allowed_mimes: [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/gif',
    ],
    max_file_bytes: 50 * 1024 * 1024,
    chunk_size_bytes: 5 * 1024 * 1024,
};

let cachedConfig: MediaLibraryConfig | null = null;
let pendingPromise: Promise<MediaLibraryConfig> | null = null;

export function useMediaConfig(): {
    config: MediaLibraryConfig;
    isLoading: boolean;
    error: Error | null;
} {
    const [config, setConfig] = useState<MediaLibraryConfig>(cachedConfig ?? DEFAULT_CONFIG);
    const [isLoading, setIsLoading] = useState<boolean>(!cachedConfig);
    const [error, setError] = useState<Error | null>(null);

    useEffect(() => {
        if (cachedConfig) {
            setConfig(cachedConfig);
            setIsLoading(false);
            return;
        }

        if (!pendingPromise) {
            pendingPromise = mediaLibraryApi
                .getConfig()
                .then((fetched) => {
                    cachedConfig = fetched;
                    return fetched;
                })
                .catch((err) => {
                    pendingPromise = null;
                    throw err;
                });
        }

        let isMounted = true;

        pendingPromise
            .then((result) => {
                if (!isMounted) return;
                setConfig(result);
                setIsLoading(false);
            })
            .catch((err) => {
                if (!isMounted) return;
                setError(err instanceof Error ? err : new Error(String(err)));
                setIsLoading(false);
            });

        return () => {
            isMounted = false;
        };
    }, []);

    return { config, isLoading, error };
}
