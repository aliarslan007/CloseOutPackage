import { useEffect, useState } from 'react';

export function useOffline() {
    const [isOffline, setIsOffline] = useState(!navigator.onLine);

    useEffect(() => {
        const handleOnline = () => setIsOffline(false);
        const handleOffline = () => setIsOffline(true);

        globalThis.addEventListener('online', handleOnline);
        globalThis.addEventListener('offline', handleOffline);

        return () => {
            globalThis.removeEventListener('online', handleOnline);
            globalThis.removeEventListener('offline', handleOffline);
        };
    }, []);

    return isOffline;
}

export default useOffline;
