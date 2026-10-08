import { ErrorBoundary } from '@/app/providers/error-boundary';
import { OfflineBanner } from '@/components/shared/offline-banner';
import { ReactNode } from 'react';
import { Toaster } from 'sonner';

export interface AppProvidersProps {
    children: ReactNode;
}

export function AppProviders({ children }: Readonly<AppProvidersProps>) {
    return (
        <ErrorBoundary>
            <OfflineBanner />
            {children}
            <Toaster position="bottom-right" richColors closeButton />
        </ErrorBoundary>
    );
}

export default AppProviders;
