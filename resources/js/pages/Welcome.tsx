import { User } from '@/types/inertia';
import { Head, Link } from '@inertiajs/react';

export interface WelcomeProps {
    auth: {
        user?: User | null;
    };
    laravelVersion: string;
    phpVersion: string;
}

export function Welcome({ auth }: Readonly<WelcomeProps>) {
    return (
        <>
            <Head title="Welcome" />
            <div className="bg-background text-muted-foreground">
                <img
                    id="background"
                    className="absolute -left-20 top-0 max-w-[877px]"
                    src="https://laravel.com/assets/img/welcome/background.svg"
                    alt="Background"
                />
                <div className="relative flex min-h-screen flex-col items-center justify-center selection:bg-primary selection:text-primary-foreground">
                    <div className="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                        <header className="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3">
                            <div className="flex lg:col-start-2 lg:justify-center">
                                <span className="text-2xl font-bold text-foreground">
                                    Close-Out Package Portal
                                </span>
                            </div>
                        </header>

                        <main className="mt-6">
                            <div className="rounded-xl border border-border bg-card p-8 shadow-sm">
                                <h1 className="text-3xl font-bold text-foreground">
                                    SOLUCOMP Close-Out Package System
                                </h1>
                                <p className="mt-4 text-base text-muted-foreground">
                                    Capture cell-site construction photos through a 3-stage
                                    workflow, manage approvals, and generate branded PDF packages.
                                </p>

                                <div className="mt-6">
                                    {auth.user ? (
                                        <Link
                                            href={route('dashboard')}
                                            className="inline-flex h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90"
                                        >
                                            Go to Dashboard
                                        </Link>
                                    ) : (
                                        <a
                                            href={route('login')}
                                            className="inline-flex h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90"
                                        >
                                            Sign in with Single Sign-On
                                        </a>
                                    )}
                                </div>
                            </div>
                        </main>

                        <footer className="py-16 text-center text-sm text-muted-foreground">
                            &copy; {new Date().getFullYear()} SOLUCOMP – Network Made Easy&reg;. All Rights Reserved.
                        </footer>
                    </div>
                </div>
            </div>
        </>
    );
}

export default Welcome;
