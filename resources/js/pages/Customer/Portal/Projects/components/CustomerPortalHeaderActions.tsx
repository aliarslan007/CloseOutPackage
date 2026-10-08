import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ArrowLeft, FileSpreadsheet } from 'lucide-react';
import { CustomerProjectDetails } from '../types';

interface CustomerPortalHeaderActionsProps {
    project: CustomerProjectDetails;
}

export function CustomerPortalHeaderActions({
    project,
}: Readonly<CustomerPortalHeaderActionsProps>) {
    return (
        <div className="flex items-center gap-2 flex-wrap shrink-0">
            <Button
                asChild
                variant="outline"
                size="sm"
                className="h-8 text-xs gap-1.5 font-medium shadow-2xs"
            >
                <Link href={route('projects.change-orders.index', project.hash_id || project.id)}>
                    <FileSpreadsheet className="size-3.5" /> Change Orders
                </Link>
            </Button>

            <Button
                asChild
                variant="outline"
                size="sm"
                className="h-8 text-xs gap-1.5 font-medium shadow-2xs"
            >
                <Link href={route('projects.show', project.hash_id || project.id)}>
                    <ArrowLeft className="size-3.5" /> Site Details
                </Link>
            </Button>
        </div>
    );
}

export default CustomerPortalHeaderActions;