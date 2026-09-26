<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { index as auditLogsIndex } from '@/routes/audit-logs';

interface ActivityProperties {
    attributes?: Record<string, unknown>;
    old?: Record<string, unknown>;
}

interface Causer {
    id: number;
    name: string;
    email: string;
}

interface Activity {
    id: number;
    log_name: string | null;
    description: string;
    subject_type: string | null;
    subject_id: string | null;
    causer_type: string | null;
    causer_id: string | null;
    causer: Causer | null;
    properties: ActivityProperties;
    event: string | null;
    created_at: string;
}

interface PaginatedActivities {
    data: Activity[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Filters {
    causer_id?: string;
    subject_type?: string;
    from?: string;
    to?: string;
}

const props = defineProps<{
    activities: PaginatedActivities;
    filters: Filters;
}>();

const localFilters = ref<Filters>({ ...props.filters });

function applyFilters() {
    const params: Record<string, string> = {};
    if (localFilters.value.causer_id)
        params.causer_id = localFilters.value.causer_id;
    if (localFilters.value.subject_type)
        params.subject_type = localFilters.value.subject_type;
    if (localFilters.value.from) params.from = localFilters.value.from;
    if (localFilters.value.to) params.to = localFilters.value.to;
    router.get(
        auditLogsIndex.url({ query: params }),
        {},
        { preserveState: true },
    );
}

function resetFilters() {
    localFilters.value = {};
    router.get(auditLogsIndex.url(), {}, { preserveState: true });
}

function shortModelName(fqcn: string | null): string {
    if (!fqcn) return '-';
    return fqcn.split('\\').pop() ?? fqcn;
}

function eventColor(event: string | null): string {
    switch (event) {
        case 'created':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'updated':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'deleted':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

watch(
    () => props.filters,
    (val) => {
        localFilters.value = { ...val };
    },
);
</script>

<template>
    <Head title="Audit Log" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-foreground">Audit Log</h1>
            <p class="text-sm text-muted-foreground">
                Riwayat perubahan data pada sistem.
            </p>
        </div>

        <!-- Filters -->
        <div
            class="flex flex-wrap items-end gap-4 rounded-lg border bg-card p-4"
        >
            <div class="grid gap-1.5">
                <Label for="filter-causer">User ID</Label>
                <Input
                    id="filter-causer"
                    v-model="localFilters.causer_id"
                    placeholder="ID user"
                    class="w-32"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="filter-subject">Model</Label>
                <Input
                    id="filter-subject"
                    v-model="localFilters.subject_type"
                    placeholder="App\\Models\\User"
                    class="w-48"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="filter-from">Dari</Label>
                <Input
                    id="filter-from"
                    v-model="localFilters.from"
                    type="date"
                    class="w-40"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="filter-to">Sampai</Label>
                <Input
                    id="filter-to"
                    v-model="localFilters.to"
                    type="date"
                    class="w-40"
                />
            </div>
            <div class="flex gap-2">
                <Button size="sm" @click="applyFilters">Filter</Button>
                <Button size="sm" variant="outline" @click="resetFilters">
                    Reset
                </Button>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-lg border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-40">Waktu</TableHead>
                        <TableHead class="w-24">Event</TableHead>
                        <TableHead class="w-28">Model</TableHead>
                        <TableHead class="w-36">User</TableHead>
                        <TableHead>Perubahan</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="activity in activities.data"
                        :key="activity.id"
                    >
                        <TableCell class="text-xs text-muted-foreground">
                            {{ formatDate(activity.created_at) }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="secondary"
                                :class="eventColor(activity.event)"
                            >
                                {{ activity.event ?? '-' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="font-mono text-xs">
                            {{ shortModelName(activity.subject_type) }}
                            <span
                                v-if="activity.subject_id"
                                class="text-muted-foreground"
                            >
                                #{{ activity.subject_id.slice(0, 8) }}
                            </span>
                        </TableCell>
                        <TableCell class="text-sm">
                            {{
                                activity.causer?.name ??
                                activity.causer?.email ??
                                'System'
                            }}
                        </TableCell>
                        <TableCell>
                            <div
                                v-if="
                                    activity.properties?.old ||
                                    activity.properties?.attributes
                                "
                                class="max-w-md space-y-1"
                            >
                                <div
                                    v-for="(val, key) in activity.properties
                                        ?.attributes ?? {}"
                                    :key="String(key)"
                                    class="flex gap-2 text-xs"
                                >
                                    <span
                                        class="shrink-0 font-medium text-foreground"
                                    >
                                        {{ key }}:
                                    </span>
                                    <span
                                        v-if="
                                            activity.properties?.old &&
                                            key in activity.properties.old
                                        "
                                        class="text-red-500 line-through dark:text-red-400"
                                    >
                                        {{
                                            activity.properties.old[
                                                key as string
                                            ]
                                        }}
                                    </span>
                                    <span
                                        class="text-green-600 dark:text-green-400"
                                    >
                                        {{ val }}
                                    </span>
                                </div>
                            </div>
                            <span v-else class="text-xs text-muted-foreground">
                                {{ activity.description }}
                            </span>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="activities.data.length === 0">
                        <TableCell
                            :colspan="5"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Tidak ada aktivitas ditemukan.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div
            v-if="activities.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                Halaman {{ activities.current_page }} dari
                {{ activities.last_page }} ({{ activities.total }} entri)
            </span>
            <div class="flex gap-2">
                <Button
                    v-if="activities.prev_page_url"
                    size="sm"
                    variant="outline"
                    @click="router.get(activities.prev_page_url!)"
                >
                    ← Sebelumnya
                </Button>
                <Button
                    v-if="activities.next_page_url"
                    size="sm"
                    variant="outline"
                    @click="router.get(activities.next_page_url!)"
                >
                    Selanjutnya →
                </Button>
            </div>
        </div>
    </div>
</template>
