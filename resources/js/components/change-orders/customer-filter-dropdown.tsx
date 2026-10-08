import { cn } from '@/lib/utils';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Check, ChevronDown, Search, Users, Users2, X } from 'lucide-react';
import * as React from 'react';
import type { ChangeOrder, CustomerFilterOption } from './change-order-types';

export interface CustomerFilterDropdownProps {
    customers: CustomerFilterOption[];
    selectedCustomerId: number | 'all';
    onSelectCustomer: (customerId: number | 'all') => void;
    changeOrders?: ChangeOrder[];
    className?: string;
}

function getInitials(name?: string): string {
    if (!name) return 'CU';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

export function CustomerFilterDropdown({
    customers = [],
    selectedCustomerId,
    onSelectCustomer,
    changeOrders = [],
    className,
}: Readonly<CustomerFilterDropdownProps>) {
    const [search, setSearch] = React.useState('');

    // Compute change order counts per customer user for richer picker context
    const customerOrderCounts = React.useMemo(() => {
        const counts = new Map<number, number>();
        for (const order of changeOrders) {
            for (const recipient of order.recipients ?? []) {
                const userId = recipient.user_id || recipient.user?.id;
                if (userId) {
                    counts.set(userId, (counts.get(userId) ?? 0) + 1);
                }
            }
        }
        return counts;
    }, [changeOrders]);

    const selectedCustomer = React.useMemo(() => {
        if (selectedCustomerId === 'all') return null;
        return customers.find((c) => c.id === selectedCustomerId) ?? null;
    }, [customers, selectedCustomerId]);

    const filteredCustomers = React.useMemo(() => {
        if (!search.trim()) return customers;
        const q = search.toLowerCase().trim();
        return customers.filter((c) => {
            const nameMatch = c.name.toLowerCase().includes(q);
            const emailMatch = (c.email ?? '').toLowerCase().includes(q);
            const companyMatch = (c.company ?? '').toLowerCase().includes(q);
            return nameMatch || emailMatch || companyMatch;
        });
    }, [customers, search]);

    const hasSelectedCustomer = selectedCustomerId !== 'all';

    return (
        <Popover className="relative inline-block text-left shrink-0">
            {({ close }) => (
                <>
                    <PopoverButton
                        className={cn(
                            'h-9 rounded-md border border-input bg-card px-3 text-xs flex items-center justify-between gap-2 shadow-2xs hover:bg-muted/50 transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer max-w-[260px]',
                            hasSelectedCustomer &&
                                'border-primary/50 bg-primary/5 font-semibold text-primary',
                            className
                        )}
                        aria-label="Filter by customer"
                    >
                        <div className="flex items-center gap-2 min-w-0 flex-1">
                            {selectedCustomer ? (
                                <>
                                    <div
                                        className="w-5 h-5 rounded-full overflow-hidden shrink-0 border border-border flex items-center justify-center"
                                        style={{
                                            width: '20px',
                                            height: '20px',
                                            minWidth: '20px',
                                            minHeight: '20px',
                                            maxWidth: '20px',
                                            maxHeight: '20px',
                                        }}
                                    >
                                        {selectedCustomer.profile_photo_url ? (
                                            <img
                                                src={selectedCustomer.profile_photo_url}
                                                alt={selectedCustomer.name}
                                                className="w-full h-full object-cover block"
                                                style={{ width: '100%', height: '100%' }}
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-primary/15 text-primary font-bold text-[9px] flex items-center justify-center">
                                                {getInitials(selectedCustomer.name)}
                                            </div>
                                        )}
                                    </div>
                                    <span className="truncate text-left text-foreground">
                                        {selectedCustomer.name}
                                    </span>
                                    {selectedCustomer.company && (
                                        <span className="text-[10px] text-muted-foreground bg-muted/80 px-1.5 py-0.2 rounded font-normal shrink-0 border border-border/40 max-w-[90px] truncate">
                                            {selectedCustomer.company}
                                        </span>
                                    )}
                                </>
                            ) : (
                                <>
                                    <Users2 className="size-3.5 shrink-0 text-muted-foreground" />
                                    <span className="truncate text-left text-muted-foreground">
                                        All Customers
                                    </span>
                                </>
                            )}
                        </div>

                        <div className="flex items-center gap-1 shrink-0">
                            {hasSelectedCustomer ? (
                                /* A real <button> inside a <button>: valid, and it
                                   brings Enter/Space activation for free — the
                                   role="button" + tabIndex + onKeyDown version
                                   re-implemented that badly. */
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSelectCustomer('all');
                                    }}
                                    className="p-0.5 hover:bg-primary/20 rounded text-primary transition-colors cursor-pointer"
                                    title="Clear customer filter"
                                    aria-label="Clear customer filter"
                                >
                                    <X className="size-3" />
                                </button>
                            ) : (
                                <ChevronDown className="size-3 text-muted-foreground" />
                            )}
                        </div>
                    </PopoverButton>

                    <PopoverPanel
                        transition
                        className="absolute left-0 z-50 mt-1.5 w-80 rounded-xl border border-border/80 bg-card p-2 shadow-lg transition duration-150 ease-out focus:outline-none data-closed:scale-95 data-closed:opacity-0"
                    >
                        {/* Search Bar inside popover */}
                        <div className="relative mb-2 px-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search customer name, email, company..."
                                className="w-full rounded-lg border border-input bg-muted/40 py-1.5 pl-8 pr-3 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:bg-card focus:outline-none focus:ring-1 focus:ring-primary"
                            />
                        </div>

                        <div className="max-h-64 overflow-y-auto space-y-0.5 pr-0.5">
                            {/* "All Customers" Option */}
                            <button
                                type="button"
                                onClick={() => {
                                    onSelectCustomer('all');
                                    close();
                                }}
                                className={cn(
                                    'w-full flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg text-xs text-left transition-colors cursor-pointer',
                                    selectedCustomerId === 'all'
                                        ? 'bg-primary/10 text-primary font-semibold'
                                        : 'hover:bg-muted text-foreground'
                                )}
                            >
                                <div className="flex items-center gap-2.5 min-w-0">
                                    <div className="size-7 rounded-full bg-muted flex items-center justify-center shrink-0">
                                        <Users className="size-3.5 text-muted-foreground" />
                                    </div>
                                    <div className="flex flex-col min-w-0">
                                        <span className="truncate font-medium">All Customers</span>
                                        <span className="text-[11px] text-muted-foreground">
                                            Show all customer change orders
                                        </span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1.5 shrink-0">
                                    <span className="text-[10px] text-muted-foreground font-medium px-1.5 py-0.5 rounded-full bg-muted/70">
                                        {changeOrders.length}
                                    </span>
                                    {selectedCustomerId === 'all' && (
                                        <Check className="size-3.5 text-primary" />
                                    )}
                                </div>
                            </button>

                            {/* Filtered Customer List */}
                            {filteredCustomers.length === 0 ? (
                                <div className="py-6 text-center text-xs text-muted-foreground">
                                    No customer contacts found
                                </div>
                            ) : (
                                filteredCustomers.map((customer) => {
                                    const isSelected = selectedCustomerId === customer.id;
                                    const count = customerOrderCounts.get(customer.id) ?? 0;

                                    return (
                                        <button
                                            key={customer.id}
                                            type="button"
                                            onClick={() => {
                                                onSelectCustomer(customer.id);
                                                close();
                                            }}
                                            className={cn(
                                                'w-full flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg text-xs text-left transition-colors cursor-pointer',
                                                isSelected
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'hover:bg-muted text-foreground'
                                            )}
                                        >
                                            <div className="flex items-center gap-2.5 min-w-0 flex-1">
                                                <div
                                                    className="w-7 h-7 rounded-full overflow-hidden shrink-0 border border-border flex items-center justify-center"
                                                    style={{
                                                        width: '28px',
                                                        height: '28px',
                                                        minWidth: '28px',
                                                        minHeight: '28px',
                                                        maxWidth: '28px',
                                                        maxHeight: '28px',
                                                    }}
                                                >
                                                    {customer.profile_photo_url ? (
                                                        <img
                                                            src={customer.profile_photo_url}
                                                            alt={customer.name}
                                                            className="w-full h-full object-cover block"
                                                            style={{
                                                                width: '100%',
                                                                height: '100%',
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center">
                                                            {getInitials(customer.name)}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex flex-col min-w-0 flex-1">
                                                    <div className="flex items-center gap-1.5 min-w-0">
                                                        <span className="truncate font-semibold text-xs text-foreground">
                                                            {customer.name}
                                                        </span>
                                                        {customer.company && (
                                                            <span className="text-[10px] text-muted-foreground bg-muted/80 px-1.5 py-0.2 rounded font-normal shrink-0 border border-border/40 max-w-[100px] truncate">
                                                                {customer.company}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {customer.email && (
                                                        <span className="text-[11px] text-muted-foreground truncate">
                                                            {customer.email}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-1.5 shrink-0">
                                                {count > 0 && (
                                                    <span className="text-[10px] text-muted-foreground font-medium px-1.5 py-0.5 rounded-full bg-muted/70">
                                                        {count} {count === 1 ? 'order' : 'orders'}
                                                    </span>
                                                )}
                                                {isSelected && (
                                                    <Check className="size-3.5 text-primary" />
                                                )}
                                            </div>
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </PopoverPanel>
                </>
            )}
        </Popover>
    );
}
