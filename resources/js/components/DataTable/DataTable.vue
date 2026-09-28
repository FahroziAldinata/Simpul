<script setup lang="ts" generic="T extends Record<string, any>">
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Inbox } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DataTableColumn, DataTablePagination } from './types';

const props = withDefaults(
    defineProps<{
        columns: DataTableColumn[];
        data: T[];
        loading?: boolean;
        pagination?: DataTablePagination;
        sortBy?: string;
        sortOrder?: 'asc' | 'desc';
        emptyTitle?: string;
        emptyDescription?: string;
        emptyActionLabel?: string;
    }>(),
    {
        loading: false,
        sortBy: '',
        sortOrder: 'asc',
        emptyTitle: 'Tidak ada data ditemukan',
        emptyDescription: 'Coba ubah kata kunci pencarian atau sesuaikan filter yang aktif.',
        emptyActionLabel: '',
    },
);

const emit = defineEmits<{
    (e: 'sort', key: string, order: 'asc' | 'desc'): void;
    (e: 'pageChange', page: number): void;
    (e: 'perPageChange', perPage: number): void;
    (e: 'emptyAction'): void;
}>();

const visibleColumns = computed(() => {
    return props.columns.filter((c) => c.visible !== false);
});

function handleSort(key: string) {
    let newOrder: 'asc' | 'desc' = 'asc';
    if (props.sortBy === key) {
        newOrder = props.sortOrder === 'asc' ? 'desc' : 'asc';
    }
    emit('sort', key, newOrder);
}

function handlePerPageChange(event: Event) {
    const val = parseInt((event.target as HTMLSelectElement).value, 10);
    emit('perPageChange', val);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Table Scroll Container with Sticky Header & First Column -->
        <div class="relative w-full overflow-auto rounded-lg border bg-card shadow-xs max-h-[70vh]">
            <Table class="w-full text-sm">
                <!-- Sticky Header -->
                <TableHeader class="sticky top-0 z-20 bg-card border-b shadow-2xs">
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            v-for="col in visibleColumns"
                            :key="col.key"
                            :class="[
                                col.sticky ? 'sticky left-0 z-30 bg-card font-semibold shadow-r' : '',
                                col.numeric ? 'text-right tabular-nums' : '',
                                col.headerClass || '',
                                'whitespace-nowrap px-4 py-3 text-xs uppercase tracking-wider text-muted-foreground select-none'
                            ]"
                        >
                            <button
                                v-if="col.sortable"
                                type="button"
                                class="inline-flex items-center gap-1.5 font-medium hover:text-foreground transition-colors cursor-pointer"
                                @click="handleSort(col.key)"
                            >
                                <span>{{ col.label }}</span>
                                <ArrowUp v-if="sortBy === col.key && sortOrder === 'asc'" class="h-3.5 w-3.5 text-primary" />
                                <ArrowDown v-else-if="sortBy === col.key && sortOrder === 'desc'" class="h-3.5 w-3.5 text-primary" />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40 hover:opacity-100" />
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </TableHead>

                        <!-- Actions Column Header -->
                        <TableHead v-if="$slots.actions" class="w-16 text-right px-4 py-3 text-xs uppercase tracking-wider text-muted-foreground">
                            Aksi
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <!-- Table Body -->
                <TableBody>
                    <!-- Loading State: Skeleton Rows -->
                    <template v-if="loading">
                        <TableRow v-for="i in 5" :key="`skeleton-${i}`" class="even:bg-muted/20">
                            <TableCell
                                v-for="col in visibleColumns"
                                :key="col.key"
                                :class="[
                                    col.sticky ? 'sticky left-0 bg-card shadow-r' : '',
                                    'px-4 py-3'
                                ]"
                            >
                                <Skeleton class="h-4 w-full max-w-[120px]" />
                            </TableCell>
                            <TableCell v-if="$slots.actions" class="px-4 py-3 text-right">
                                <Skeleton class="h-8 w-8 ml-auto rounded-md" />
                            </TableCell>
                        </TableRow>
                    </template>

                    <!-- Data Rows -->
                    <template v-else-if="data.length > 0">
                        <TableRow
                            v-for="(row, rowIdx) in data"
                            :key="String((row as any).id ?? rowIdx)"
                            class="group transition-colors even:bg-muted/25 hover:bg-muted/60"
                        >
                            <TableCell
                                v-for="col in visibleColumns"
                                :key="col.key"
                                :class="[
                                    col.sticky ? 'sticky left-0 z-10 bg-card group-hover:bg-muted/60 group-even:bg-muted/25 font-medium shadow-r' : '',
                                    col.numeric ? 'text-right tabular-nums' : '',
                                    col.class || '',
                                    'px-4 py-3 whitespace-nowrap'
                                ]"
                            >
                                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                    {{ row[col.key] !== null && row[col.key] !== undefined ? row[col.key] : '-' }}
                                </slot>
                            </TableCell>

                            <!-- Actions Slot (3 Dots Menu) -->
                            <TableCell v-if="$slots.actions" class="px-4 py-3 text-right whitespace-nowrap">
                                <slot name="actions" :row="row" />
                            </TableCell>
                        </TableRow>
                    </template>

                    <!-- Empty State -->
                    <template v-else>
                        <TableRow class="hover:bg-transparent">
                            <TableCell
                                :colspan="visibleColumns.length + ($slots.actions ? 1 : 0)"
                                class="h-64 text-center"
                            >
                                <div class="flex flex-col items-center justify-center p-6 text-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-3">
                                        <Inbox class="h-6 w-6" />
                                    </div>
                                    <h3 class="text-base font-semibold text-foreground mb-1">
                                        {{ emptyTitle }}
                                    </h3>
                                    <p class="max-w-sm text-sm text-muted-foreground mb-4">
                                        {{ emptyDescription }}
                                    </p>
                                    <slot name="empty">
                                        <Button
                                            v-if="emptyActionLabel"
                                            variant="outline"
                                            size="sm"
                                            @click="emit('emptyAction')"
                                        >
                                            {{ emptyActionLabel }}
                                        </Button>
                                    </slot>
                                </div>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <!-- Server-side Pagination Controls -->
        <div v-if="pagination && pagination.total > 0" class="flex flex-col sm:flex-row items-center justify-between gap-4 px-2 text-xs text-muted-foreground">
            <!-- Row Count & Per-Page Selector -->
            <div class="flex items-center gap-2">
                <span>Menampilkan {{ pagination.from || 0 }} - {{ pagination.to || 0 }} dari {{ pagination.total }} data</span>
                <span class="text-muted-foreground/50">|</span>
                <div class="flex items-center gap-1.5">
                    <label for="dt-per-page" class="text-xs">Baris per halaman:</label>
                    <select
                        id="dt-per-page"
                        class="h-7 rounded-md border border-input bg-background px-2 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring cursor-pointer"
                        :value="pagination.perPage"
                        @change="handlePerPageChange"
                    >
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Page Navigation Buttons -->
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 w-7 p-0"
                    :disabled="pagination.currentPage <= 1 || loading"
                    @click="emit('pageChange', pagination.currentPage - 1)"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                    <span class="sr-only">Halaman Sebelumnya</span>
                </Button>

                <span class="px-2 font-medium text-foreground">
                    {{ pagination.currentPage }} / {{ pagination.lastPage }}
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 w-7 p-0"
                    :disabled="pagination.currentPage >= pagination.lastPage || loading"
                    @click="emit('pageChange', pagination.currentPage + 1)"
                >
                    <ChevronRight class="h-3.5 w-3.5" />
                    <span class="sr-only">Halaman Selanjutnya</span>
                </Button>
            </div>
        </div>
    </div>
</template>
