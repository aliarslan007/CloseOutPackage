import * as React from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { SearchInput } from '@/components/shared/search-input';
import { EmptyCard } from '@/components/shared/empty-card';
import { Button } from '@/components/ui/button';
import { ChevronDown, ChevronUp, ChevronsUpDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export interface ColumnDef<T> {
    accessorKey: keyof T | string;
    header: string;
    cell?: (row: T) => React.ReactNode;
    sortable?: boolean;
    className?: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface DataTableProps<T> {
    columns: ColumnDef<T>[];
    data: T[];
    meta?: PaginationMeta;
    searchValue?: string;
    onSearch?: (query: string) => void;
    sortColumn?: string;
    sortDirection?: 'asc' | 'desc';
    onSort?: (column: string, direction: 'asc' | 'desc') => void;
    loading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    onRowClick?: (row: T) => void;
    rowClassName?: (row: T) => string | undefined;
    className?: string;
    tableContainerClassName?: string;
}

function renderSortIcon(isSorted: boolean, sortDirection?: 'asc' | 'desc') {
    if (!isSorted) {
        return <ChevronsUpDown className="size-3.5 opacity-50" />;
    }
    if (sortDirection === 'asc') {
        return <ChevronUp className="size-3.5" />;
    }
    return <ChevronDown className="size-3.5" />;
}

export function DataTable<T extends Record<string, unknown>>({
    columns,
    data,
    meta,
    searchValue = '',
    onSearch,
    sortColumn,
    sortDirection,
    onSort,
    loading = false,
    emptyTitle = 'No data found',
    emptyDescription = 'There are no records to display at this time.',
    onRowClick,
    rowClassName,
    className,
    tableContainerClassName,
}: DataTableProps<T>) {
    const handleSort = (columnKey: string) => {
        if (!onSort) return;
        const newDirection = sortColumn === columnKey && sortDirection === 'asc' ? 'desc' : 'asc';
        onSort(columnKey, newDirection);
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(page));
        router.get(
            window.location.pathname,
            Object.fromEntries(params.entries()),
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleRowClick = (row: T, event: React.MouseEvent<HTMLTableRowElement>) => {
        const target = event.target as HTMLElement;
        if (target.closest('a, button, input, select, textarea')) return;
        onRowClick?.(row);
    };

    const renderTableBody = () => {
        if (loading) {
            return Array.from({ length: 5 }).map((_, rIdx) => (
                <TableRow key={rIdx}>
                    {columns.map((_, cIdx) => (
                        <TableCell key={cIdx}>
                            <Skeleton className="h-5 w-full" />
                        </TableCell>
                    ))}
                </TableRow>
            ));
        }

        if (data.length === 0) {
            return (
                <TableRow>
                    <TableCell colSpan={columns.length} className="h-48 text-center">
                        <EmptyCard title={emptyTitle} description={emptyDescription} />
                    </TableCell>
                </TableRow>
            );
        }

        return data.map((row, rIdx) => (
            <TableRow
                key={rIdx}
                onClick={(event) => handleRowClick(row, event)}
                className={cn(
                    onRowClick && 'cursor-pointer hover:bg-muted/60',
                    rowClassName?.(row)
                )}
            >
                {columns.map((col, cIdx) => (
                    <TableCell key={cIdx} className={col.className}>
                        {col.cell
                            ? col.cell(row)
                            : String(row[col.accessorKey] ?? '')}
                    </TableCell>
                ))}
            </TableRow>
        ));
    };

    return (
        <div className={cn('space-y-4 w-full', className)}>
            {onSearch && (
                <div className="flex items-center justify-between gap-4">
                    <SearchInput
                        value={searchValue}
                        onSearchChange={onSearch}
                        placeholder="Search records..."
                        className="max-w-xs"
                    />
                </div>
            )}

            <div
                className={cn(
                    'rounded-md border bg-card text-card-foreground shadow-2xs overflow-hidden',
                    tableContainerClassName
                )}
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((col, idx) => {
                                const isSorted = sortColumn === col.accessorKey;
                                return (
                                    <TableHead
                                        key={idx}
                                        className={cn(
                                            'whitespace-nowrap',
                                            col.sortable && 'cursor-pointer select-none hover:text-foreground',
                                            col.className
                                        )}
                                        onClick={() => col.sortable && handleSort(String(col.accessorKey))}
                                    >
                                        <div
                                            className={cn(
                                                'flex items-center gap-1.5',
                                                col.className?.includes('text-right') &&
                                                    'justify-end'
                                            )}
                                        >
                                            <span>{col.header}</span>
                                            {col.sortable && (
                                                <span className="text-muted-foreground">
                                                    {renderSortIcon(isSorted, sortDirection)}
                                                </span>
                                            )}
                                        </div>
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    </TableHeader>
                    <TableBody>{renderTableBody()}</TableBody>
                </Table>
            </div>

            {meta && meta.last_page > 1 && (
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-muted-foreground px-1">
                    <div>
                        Showing <span className="font-semibold text-foreground">{meta.from}</span> to{' '}
                        <span className="font-semibold text-foreground">{meta.to}</span> of{' '}
                        <span className="font-semibold text-foreground">{meta.total}</span> entries
                    </div>
                    <div className="flex items-center justify-between sm:justify-end gap-2 flex-wrap">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={meta.current_page <= 1}
                            onClick={() => handlePageChange(meta.current_page - 1)}
                        >
                            <ChevronLeft className="size-4 mr-1" />
                            Previous
                        </Button>
                        <span className="font-medium">
                            Page {meta.current_page} of {meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => handlePageChange(meta.current_page + 1)}
                        >
                            Next
                            <ChevronRight className="size-4 ml-1" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default DataTable;
