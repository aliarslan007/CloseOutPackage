import * as React from 'react';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, LogIn } from 'lucide-react';

import { useFlashToasts } from '@/hooks/use-flash-toast';
import { AppLayout } from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';

export type SessionExpiredPageProps = PageProps<Record<string, never>>;

export function SessionExpired(): React.ReactElement {
    useFlashToasts();

    const signIn = (): void => {
        window.location.assign(route('login'));
    };

    return (
        <AppLayout
            title="Session expired"
            breadcrumb={[{ label: 'Session expired' }]}
        >
            <Head title="Session expired" />

            <div className="flex min-h-[60vh] items-center justify-center">
                <div className="w-full max-w-md space-y-6 rounded-lg border border-border bg-card p-8 text-center shadow-sm">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <AlertTriangle className="size-6" />
                    </div>

                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Your session has expired
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            For your security, you were signed out after a period of inactivity.
                            Sign in again to continue where you left off.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 pt-2">
                        <button
                            type="button"
                            onClick={signIn}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                        >
                            <LogIn className="size-4" />
                            Sign in again
                        </button>

                        <Link
                            href="/"
                            className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                        >
                            Go to home
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default SessionExpired;
