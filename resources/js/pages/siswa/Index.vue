<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    Columns3,
    CreditCard,
    Filter,
    GraduationCap,
    MoreHorizontal,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Search,
    Trash2,
    UserCheck,
    UserX,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MutasiDialog from '@/components/Siswa/MutasiDialog.vue';
import SiswaFormDialog from '@/components/Siswa/SiswaFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTable } from '@/components/DataTable';
import type { DataTableColumn, DataTablePagination } from '@/components/DataTable/types';
import type { RombelOption, SemesterInfo, SiswaItem } from '@/types/siswa';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    siswa: PaginatedData<SiswaItem>;
    rombelList: RombelOption[];
    currentSemester?: SemesterInfo | null;
    filters: {
        search?: string;
        rombel_id?: string;
        tingkat?: string;
        jenis_kelamin?: string;
        status?: string;
        kelengkapan?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
    userPreferences?: string[] | null;
    canManage: boolean;
    isWaliKelas: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kesiswaan',
                href: '/siswa',
            },
            {
                title: 'Data Siswa',
                href: '/siswa',
            },
        ],
    },
});

// Search & Filter state
const search = ref(props.filters.search || '');
const rombelId = ref(props.filters.rombel_id || '');
const tingkat = ref(props.filters.tingkat || '');
const jenisKelamin = ref(props.filters.jenis_kelamin || '');
const status = ref(props.filters.status || '');
const kelengkapan = ref(props.filters.kelengkapan || '');
const sortBy = ref(props.filters.sort_by || 'nama');
const sortOrder = ref<'asc' | 'desc'>((props.filters.sort_order as 'asc' | 'desc') || 'asc');
const perPage = ref(props.filters.per_page || 25);

// Column Visibility Management (T-05.05)
const defaultColumns = [
    'nama',
    'nisn',
    'nik',
    'jenis_kelamin',
    'rombel',
    'status',
    'kelengkapan',
    'kontak',
    'actions',
];

const visibleColumnKeys = ref<string[]>(
    props.userPreferences && Array.isArray(props.userPreferences) && props.userPreferences.length > 0
        ? props.userPreferences
        : defaultColumns
);

const columnDefinitions: DataTableColumn[] = [
    { key: 'nama', label: 'Nama Siswa', sortable: true, sticky: true },
    { key: 'nisn', label: 'NISN', sortable: true, numeric: true },
    { key: 'nik', label: 'NIK', sortable: true, numeric: true },
    { key: 'jenis_kelamin', label: 'L/P', sortable: false },
    { key: 'rombel', label: 'Rombel', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'kelengkapan', label: 'Kelengkapan', sortable: false },
    { key: 'kontak', label: 'Kontak & Alamat', sortable: false },
    { key: 'actions', label: '', sortable: false, class: 'w-[60px] text-right' },
];

const toggleableColumns = [
    { key: 'nisn', label: 'NISN' },
    { key: 'nik', label: 'NIK' },
    { key: 'jenis_kelamin', label: 'Jenis Kelamin' },
    { key: 'rombel', label: 'Rombel Aktif' },
    { key: 'status', label: 'Status Siswa' },
    { key: 'kelengkapan', label: 'Kelengkapan Data' },
    { key: 'kontak', label: 'Kontak & Alamat' },
];

function toggleColumn(key: string, checked: boolean) {
    if (checked) {
        if (!visibleColumnKeys.value.includes(key)) {
            visibleColumnKeys.value.push(key);
        }
    } else {
        visibleColumnKeys.value = visibleColumnKeys.value.filter((k) => k !== key);
    }
    savePreferences();
}

function savePreferences() {
    router.patch(
        '/user/preferences',
        {
            preferences: {
                siswa_columns: visibleColumnKeys.value,
            },
        },
        { preserveScroll: true, preserveState: true }
    );
}

const activeColumns = computed<DataTableColumn[]>(() => {
    return columnDefinitions.filter((col) => {
        if (col.key === 'nama' || col.key === 'actions') return true;
        return visibleColumnKeys.value.includes(col.key);
    });
});

const paginationData = computed<DataTablePagination>(() => ({
    currentPage: props.siswa.current_page,
    lastPage: props.siswa.last_page,
    perPage: props.siswa.per_page,
    total: props.siswa.total,
    from: props.siswa.from,
    to: props.siswa.to,
}));

// Synchronize query parameters
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function applyQuery() {
    router.get(
        '/siswa',
        {
            search: search.value || undefined,
            rombel_id: rombelId.value || undefined,
            tingkat: tingkat.value || undefined,
            jenis_kelamin: jenisKelamin.value || undefined,
            status: status.value || undefined,
            kelengkapan: kelengkapan.value || undefined,
            sort_by: sortBy.value,
            sort_order: sortOrder.value,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyQuery();
    }, 300);
});

watch([rombelId, tingkat, jenisKelamin, status, kelengkapan, perPage], () => {
    applyQuery();
});

function handleSort(column: string) {
    if (sortBy.value === column) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortOrder.value = 'asc';
    }
    applyQuery();
}

function handlePageChange(page: number) {
    router.get(
        '/siswa',
        {
            search: search.value || undefined,
            rombel_id: rombelId.value || undefined,
            tingkat: tingkat.value || undefined,
            jenis_kelamin: jenisKelamin.value || undefined,
            status: status.value || undefined,
            kelengkapan: kelengkapan.value || undefined,
            sort_by: sortBy.value,
            sort_order: sortOrder.value,
            per_page: perPage.value,
            page,
        },
        { preserveState: true, preserveScroll: true }
    );
}

function resetFilters() {
    search.value = '';
    rombelId.value = '';
    tingkat.value = '';
    jenisKelamin.value = '';
    status.value = '';
    kelengkapan.value = '';
    sortBy.value = 'nama';
    sortOrder.value = 'asc';
}

const hasActiveFilters = computed(() => {
    return (
        !!search.value ||
        !!rombelId.value ||
        !!tingkat.value ||
        !!jenisKelamin.value ||
        !!status.value ||
        !!kelengkapan.value
    );
});

// Form Dialog State
const isFormOpen = ref(false);
const selectedSiswa = ref<SiswaItem | null>(null);

function openCreate() {
    selectedSiswa.value = null;
    isFormOpen.value = true;
}

function openEdit(item: SiswaItem) {
    selectedSiswa.value = item;
    isFormOpen.value = true;
}

// Mutasi Dialog State
const isMutasiOpen = ref(false);
const selectedSiswaForMutasi = ref<SiswaItem | null>(null);

function openMutasi(item: SiswaItem) {
    selectedSiswaForMutasi.value = item;
    isMutasiOpen.value = true;
}

// Delete Confirmation Dialog
const isDeleteOpen = ref(false);
const siswaToDelete = ref<SiswaItem | null>(null);

function confirmDelete(item: SiswaItem) {
    siswaToDelete.value = item;
    isDeleteOpen.value = true;
}

function executeDelete() {
    if (!siswaToDelete.value) return;

    router.delete(`/siswa/${siswaToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteOpen.value = false;
            siswaToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Data Siswa" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    <GraduationCap class="h-6 w-6 text-primary" />
                    Data Siswa
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manajemen data pokok siswa, penempatan rombel aktif, dan biodata perwalian.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Column Visibility Popover -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-9">
                            <Columns3 class="h-4 w-4 mr-1.5" />
                            Kolom
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuLabel>Tampilkan Kolom</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            v-for="col in toggleableColumns"
                            :key="col.key"
                            :checked="visibleColumnKeys.includes(col.key)"
                            @update:checked="(val: boolean) => toggleColumn(col.key, val)"
                        >
                            {{ col.label }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Cetak Kartu Rombel Button (When Rombel is selected) -->
                <Button
                    v-if="rombelId"
                    variant="outline"
                    size="sm"
                    class="h-9"
                    as-child
                >
                    <a :href="`/rombel/${rombelId}/kartu`" target="_blank" class="flex items-center">
                        <Printer class="h-4 w-4 mr-1.5" />
                        Cetak Kartu Rombel
                    </a>
                </Button>

                <!-- Tambah Siswa Button (Operator & Super Admin) -->
                <Button v-if="canManage" size="sm" class="h-9" @click="openCreate">
                    <Plus class="h-4 w-4 mr-1.5" />
                    Tambah Siswa
                </Button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-lg border bg-card p-4 space-y-3 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <!-- Global Indexed Search -->
                <div class="md:col-span-2 relative">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        class="pl-9 h-9"
                        placeholder="Cari nama, NISN, atau NIK siswa..."
                    />
                </div>

                <!-- Filter Rombel -->
                <div>
                    <Select
                        :model-value="rombelId || '__ALL__'"
                        @update:model-value="(val: any) => (rombelId = val === '__ALL__' ? '' : val)"
                    >
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Semua Rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__ALL__">Semua Rombel</SelectItem>
                            <SelectItem
                                v-for="r in rombelList"
                                :key="r.id"
                                :value="r.id"
                            >
                                Tingkat {{ r.tingkat }} - {{ r.nama }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Filter Kelengkapan (SQL Query Scope) -->
                <div>
                    <Select
                        :model-value="kelengkapan || '__ALL__'"
                        @update:model-value="(val: any) => (kelengkapan = val === '__ALL__' ? '' : val)"
                    >
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Kelengkapan Data" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__ALL__">Semua Kelengkapan</SelectItem>
                            <SelectItem value="lengkap">Data Lengkap</SelectItem>
                            <SelectItem value="belum_lengkap">Belum Lengkap</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Secondary Filters Row -->
            <div class="flex flex-wrap items-center gap-2 pt-1 border-t text-xs">
                <span class="text-muted-foreground flex items-center gap-1 font-medium mr-1">
                    <Filter class="h-3.5 w-3.5" /> Filter Cepat:
                </span>

                <!-- Gender -->
                <Select
                    :model-value="jenisKelamin || '__ALL__'"
                    @update:model-value="(val: any) => (jenisKelamin = val === '__ALL__' ? '' : val)"
                >
                    <SelectTrigger class="h-7 w-[120px] text-xs">
                        <SelectValue placeholder="Gender" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__ALL__">Semua L/P</SelectItem>
                        <SelectItem value="L">Laki-laki (L)</SelectItem>
                        <SelectItem value="P">Perempuan (P)</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Status -->
                <Select
                    :model-value="status || '__ALL__'"
                    @update:model-value="(val: any) => (status = val === '__ALL__' ? '' : val)"
                >
                    <SelectTrigger class="h-7 w-[130px] text-xs">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__ALL__">Semua Status</SelectItem>
                        <SelectItem value="aktif">Aktif</SelectItem>
                        <SelectItem value="mutasi_keluar">Mutasi Keluar</SelectItem>
                        <SelectItem value="drop_out">Drop Out</SelectItem>
                        <SelectItem value="lulus">Lulus</SelectItem>
                        <SelectItem value="non_aktif">Non-Aktif</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Tingkat -->
                <Select
                    :model-value="tingkat || '__ALL__'"
                    @update:model-value="(val: any) => (tingkat = val === '__ALL__' ? '' : val)"
                >
                    <SelectTrigger class="h-7 w-[120px] text-xs">
                        <SelectValue placeholder="Tingkat" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__ALL__">Semua Tingkat</SelectItem>
                        <SelectItem value="10">Tingkat 10</SelectItem>
                        <SelectItem value="11">Tingkat 11</SelectItem>
                        <SelectItem value="12">Tingkat 12</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Reset Button -->
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    class="h-7 text-xs px-2 text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <RotateCcw class="h-3 w-3 mr-1" />
                    Reset Filter
                </Button>
            </div>
        </div>

        <!-- DataTable -->
        <DataTable
            :columns="activeColumns"
            :data="siswa.data"
            :pagination="paginationData"
            :sort-column="sortBy"
            :sort-direction="sortOrder"
            :per-page="perPage"
            sticky-header
            sticky-first-column
            zebra
            @sort="handleSort"
            @page-change="handlePageChange"
            @update:per-page="(val: number) => (perPage = val)"
        >
            <!-- Cell: Nama & Kelengkapan Status -->
            <template #cell-nama="{ row }: { row: SiswaItem }">
                <div class="flex items-center gap-2.5 min-w-[200px]">
                    <div class="h-8 w-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-medium text-xs">
                        {{ row.nama ? row.nama.charAt(0).toUpperCase() : '?' }}
                    </div>
                    <div class="flex flex-col">
                        <span class="font-medium text-foreground hover:underline cursor-pointer" @click="openEdit(row)">
                            {{ row.nama }}
                        </span>
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span>{{ row.tempat_lahir || '-' }}</span>
                            <span v-if="row.tanggal_lahir">• {{ row.tanggal_lahir.substring(0, 10) }}</span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Cell: NISN -->
            <template #cell-nisn="{ row }: { row: SiswaItem }">
                <span class="font-mono text-xs tabular-nums text-foreground">
                    {{ row.nisn || '-' }}
                </span>
            </template>

            <!-- Cell: NIK -->
            <template #cell-nik="{ row }: { row: SiswaItem }">
                <span class="font-mono text-xs tabular-nums text-muted-foreground">
                    {{ row.nik || '-' }}
                </span>
            </template>

            <!-- Cell: L/P -->
            <template #cell-jenis_kelamin="{ row }: { row: SiswaItem }">
                <Badge
                    variant="outline"
                    class="text-xs px-1.5"
                    :class="row.jenis_kelamin === 'L' ? 'border-blue-400 text-blue-600 dark:text-blue-400' : 'border-pink-400 text-pink-600 dark:text-pink-400'"
                >
                    {{ row.jenis_kelamin }}
                </Badge>
            </template>

            <!-- Cell: Rombel -->
            <template #cell-rombel="{ row }: { row: SiswaItem }">
                <Badge v-if="row.anggota_rombel_aktif?.rombel" variant="secondary" class="text-xs">
                    Tingkat {{ row.anggota_rombel_aktif.rombel.tingkat }} - {{ row.anggota_rombel_aktif.rombel.nama }}
                </Badge>
                <span v-else class="text-xs text-muted-foreground/70 italic">
                    Belum masuk rombel
                </span>
            </template>

            <!-- Cell: Status -->
            <template #cell-status="{ row }: { row: SiswaItem }">
                <Badge
                    class="text-xs capitalize font-normal"
                    :class="{
                        'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30': row.status === 'aktif',
                        'bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30': row.status === 'mutasi_keluar',
                        'bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30': row.status === 'drop_out',
                        'bg-blue-500/15 text-blue-700 dark:text-blue-400 border border-blue-500/30': row.status === 'lulus',
                        'bg-muted text-muted-foreground': row.status === 'non_aktif',
                    }"
                >
                    {{ row.status.replace('_', ' ') }}
                </Badge>
            </template>

            <!-- Cell: Kelengkapan Data Badge (withExists / eager load backed) -->
            <template #cell-kelengkapan="{ row }: { row: SiswaItem }">
                <Badge
                    v-if="row.is_data_lengkap"
                    variant="outline"
                    class="border-emerald-500/40 text-emerald-600 dark:text-emerald-400 gap-1 text-[11px] font-normal"
                >
                    <UserCheck class="h-3 w-3" />
                    Lengkap
                </Badge>
                <Badge
                    v-else
                    variant="outline"
                    class="border-amber-500/40 text-amber-600 dark:text-amber-400 gap-1 text-[11px] font-normal"
                >
                    <UserX class="h-3 w-3" />
                    Belum Lengkap
                </Badge>
            </template>

            <!-- Cell: Kontak & Alamat -->
            <template #cell-kontak="{ row }: { row: SiswaItem }">
                <div class="flex flex-col text-xs max-w-[220px]">
                    <span class="truncate font-mono tabular-nums text-foreground">{{ row.no_hp || '-' }}</span>
                    <span class="truncate text-muted-foreground" :title="row.alamat || ''">{{ row.alamat || 'Alamat belum diisi' }}</span>
                </div>
            </template>

            <!-- Cell: 3-Dots Actions Menu -->
            <template #cell-actions="{ row }: { row: SiswaItem }">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                            <MoreHorizontal class="h-4 w-4" />
                            <span class="sr-only">Aksi</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-40">
                        <DropdownMenuItem @click="openEdit(row)">
                            <Pencil class="h-3.5 w-3.5 mr-2" />
                            {{ isWaliKelas ? 'Ubah Kontak/Wali' : 'Edit' }}
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openMutasi(row)">
                            <ArrowRightLeft class="h-3.5 w-3.5 mr-2" />
                            Mutasi & Riwayat
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="`/siswa/${row.id}/kartu`" target="_blank" class="flex items-center w-full">
                                <CreditCard class="h-3.5 w-3.5 mr-2" />
                                Cetak Kartu
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator v-if="canManage" />
                        <DropdownMenuItem
                            v-if="canManage"
                            class="text-destructive focus:text-destructive"
                            @click="confirmDelete(row)"
                        >
                            <Trash2 class="h-3.5 w-3.5 mr-2" />
                            Hapus
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </DataTable>
    </div>

    <!-- Create / Edit Dialog -->
    <SiswaFormDialog
        :open="isFormOpen"
        :siswa="selectedSiswa"
        :rombel-list="rombelList"
        :current-semester="currentSemester"
        :is-wali-kelas="isWaliKelas"
        @update:open="(val: boolean) => (isFormOpen = val)"
        @success="isFormOpen = false"
    />

    <!-- Mutasi & Riwayat Kelas Dialog -->
    <MutasiDialog
        :open="isMutasiOpen"
        :siswa="selectedSiswaForMutasi"
        :rombel-list="rombelList"
        :current-semester="currentSemester"
        :can-manage="canManage"
        @update:open="(val: boolean) => (isMutasiOpen = val)"
        @success="() => router.reload({ only: ['siswa'] })"
    />

    <!-- Delete Confirmation Modal -->
    <Dialog :open="isDeleteOpen" @update:open="(val: boolean) => (isDeleteOpen = val)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Konfirmasi Hapus Siswa</DialogTitle>
                <DialogDescription>
                    Apakah Anda yakin ingin menghapus data siswa
                    <strong>{{ siswaToDelete?.nama }}</strong> (NISN: {{ siswaToDelete?.nisn }})?
                    Data akan dipindahkan ke arsip soft-delete.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="isDeleteOpen = false">Batal</Button>
                <Button variant="destructive" @click="executeDelete">Hapus Data</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
