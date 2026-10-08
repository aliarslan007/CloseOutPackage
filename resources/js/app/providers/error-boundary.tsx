import React, { Component, ErrorInfo, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { LoggerService } from '@/services/logger.service';
import { AlertTriangle } from 'lucide-react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null,
    };

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        LoggerService.error('error-boundary', error, { componentStack: errorInfo.componentStack });
    }

    private readonly handleReload = () => {
        globalThis.location.reload();
    };

    public render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div className="flex min-h-screen items-center justify-center p-4 bg-background">
                    <Card className="w-full max-w-md border-destructive/30 shadow-lg">
                        <CardHeader className="text-center">
                            <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-destructive/10 text-destructive mb-2">
                                <AlertTriangle className="size-6" />
                            </div>
                            <CardTitle className="text-xl">Something went wrong</CardTitle>
                        </CardHeader>
                        <CardContent className="text-center space-y-4">
                            <p className="text-sm text-muted-foreground">
                                An unexpected application error occurred. You can reload the page or return to the dashboard.
                            </p>
                            {this.state.error && (
                                <div className="rounded-md bg-muted p-3 text-left">
                                    <p className="font-mono text-xs text-destructive break-words">
                                        {this.state.error.message}
                                    </p>
                                </div>
                            )}
                            <div className="flex justify-center gap-3">
                                <Button variant="outline" onClick={() => (globalThis.location.href = '/dashboard')}>
                                    Go to Dashboard
                                </Button>
                                <Button onClick={this.handleReload}>
                                    Reload Page
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
