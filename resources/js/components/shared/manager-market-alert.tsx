import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export interface ManagerMarketAlertProps {
    message: string;
}

export function ManagerMarketAlert({ message }: Readonly<ManagerMarketAlertProps>) {
    if (!message) {
        return null;
    }

    return (
        <div className="rounded-md border border-destructive/25 bg-destructive/10 p-3 text-xs text-destructive">
            <p className="font-medium">{message}</p>
            <Link
                href={route('admin.markets.index')}
                className="mt-1.5 inline-flex items-center gap-1 font-semibold underline underline-offset-2 hover:text-destructive/80"
            >
                Go to Markets to assign access
                <ArrowRight className="size-3" />
            </Link>
        </div>
    );
}
