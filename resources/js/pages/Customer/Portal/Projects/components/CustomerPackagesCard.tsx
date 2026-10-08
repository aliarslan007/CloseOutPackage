import { EmptyCard } from '@/components/shared/empty-card';
import { FileText, Layers } from 'lucide-react';
import { CustomerPackageItem } from '../types';
import { CustomerPackageItemCard } from './CustomerPackageItemCard';

interface CustomerPackagesCardProps {
    packages: CustomerPackageItem[];
    /** hash_id (or id) of the package whose photos/checklist are being viewed. */
    activePackageKey?: string | null;
    /** Called when the customer clicks a package card to view its deliverables. */
    onSelect?: (pkg: CustomerPackageItem) => void;
}

export function CustomerPackagesCard({
    packages,
    activePackageKey,
    onSelect,
}: Readonly<CustomerPackagesCardProps>) {
    return (
        <div className="space-y-3">
            <h2 className="flex items-center gap-2 text-sm font-semibold tracking-tight text-foreground">
                <Layers className="size-4 text-muted-foreground" />
                Close-Out Packages
                <span className="text-muted-foreground tabular-nums">({packages.length})</span>
            </h2>

            {packages.length === 0 ? (
                <EmptyCard
                    title="No Approved Packages Yet"
                    description="Packages will appear here once all steps and checklists are fully completed and approved by the project manager."
                    icon={<FileText className="size-10 text-blue-500/80" />}
                />
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    {packages.map((pkg) => (
                        <CustomerPackageItemCard
                            key={pkg.id}
                            pkg={pkg}
                            activePackageKey={activePackageKey}
                            onSelect={onSelect}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default CustomerPackagesCard;
