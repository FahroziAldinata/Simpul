export interface DataTableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    sticky?: boolean;
    numeric?: boolean;
    class?: string;
    headerClass?: string;
    visible?: boolean;
}

export interface DataTablePagination {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from?: number | null;
    to?: number | null;
}
