<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Calendar,
    Check,
    Copy,
    CreditCard,
    KeyRound,
    Loader2,
    MoreHorizontal,
    Pencil,
    Phone,
    Plus,
    Printer,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { DataTable } from '@/components/DataTable';
import type { DataTableColumn, DataTablePagination } from '@/components/DataTable/types';
import PegawaiFormDialog from '@/components/Pegawai/PegawaiFormDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import type { PegawaiItem } from '@/types/pegawai';

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
    pegawai: PaginatedData<PegawaiItem>;
    currentSemester?: { id: string; nama: string } | null;
    filters: {
        search?: string;
        jenis?: string;
        status_kepegawaian?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kepegawaian',
                href: '/pegawai',
            },
            {
                title: 'Data Pegawai',
                href: '/pegawai',
            },
        ],
    },
});

// Filters & sorting state
const search = ref(props.filters.search || '');
const jenis = ref(props.filters.jenis || '');
const statusKepegawaian = ref(props.filters.status_kepegawaian || '');
const sortBy = ref(props.filters.sort_by || 'nama');
const sortOrder = ref<'asc' | 'desc'>((props.filters.sort_order as 'asc' | 'desc') || 'asc');
const perPage = ref(props.filters.per_page || 25);

const columnDefinitions: DataTableColumn[] = [
    { key: 'nama', label: 'Nama Pegawai', sortable: true, sticky: true },
    { key: 'jenis', label: 'Jenis / Peran', sortable: true },
    { key: 'status_kepegawaian', label: 'Status', sortable: true },
    { key: 'beban_mengajar', label: 'Beban Mengajar', sortable: true },
    { key: 'hari_tidak_mengajar', label: 'Hari Bebas', sortable: false },
    { key: 'kontak', label: 'Kontak', sortable: false },
    { key: 'actions', label: '', sortable: false, class: 'w-[60px] text-right' },
];

const paginationData = computed<DataTablePagination>(() => ({
    currentPage: props.pegawai.current_page,
    lastPage: props.pegawai.last_page,
    perPage: props.pegawai.per_page,
    total: props.pegawai.total,
    from: props.pegawai.from,
    to: props.pegawai.to,
}));

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function applyQuery() {
    router.get(
        '/pegawai',
        {
            search: search.value || undefined,
            jenis: jenis.value || undefined,
            status_kepegawaian: statusKepegawaian.value || undefined,
            sort_by: sortBy.value,
            sort_order: sortOrder.value,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true }
    );
}

function handleSearchInput() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyQuery();
    }, 350);
}

function handleSort(key: string) {
    if (sortBy.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortOrder.value = 'asc';
    }
    applyQuery();
}

function handlePageChange(page: number) {
    router.get(
        '/pegawai',
        {
            search: search.value || undefined,
            jenis: jenis.value || undefined,
            status_kepegawaian: statusKepegawaian.value || undefined,
            sort_by: sortBy.value,
            sort_order: sortOrder.value,
            per_page: perPage.value,
            page,
        },
        { preserveState: true, preserveScroll: true }
    );
}

function handlePerPageChange(newPerPage: number) {
    perPage.value = newPerPage;
    applyQuery();
}

// Dialog States
const formDialogOpen = ref(false);
const editingPegawai = ref<PegawaiItem | null>(null);

// One-time password modal state (T-06.04)
const initialAccountData = ref<{ nama: string; email: string; password: string } | null>(null);
const initialPasswordDialogOpen = ref(false);
const passwordCopied = ref(false);

// Delete confirmation state
const deleteDialogOpen = ref(false);
const deletingPegawai = ref<PegawaiItem | null>(null);
const isDeleting = ref(false);

function openCreateDialog() {
    editingPegawai.value = null;
    formDialogOpen.value = true;
}

function openEditDialog(item: PegawaiItem) {
    editingPegawai.value = item;
    formDialogOpen.value = true;
}

function handleFormSuccess(accountData?: { nama: string; email: string; password: string } | null) {
    router.reload();

    if (accountData) {
        initialAccountData.value = accountData;
        passwordCopied.value = false;
        initialPasswordDialogOpen.value = true;
    }
}

function copyPassword() {
    if (!initialAccountData.value?.password) return;
    navigator.clipboard.writeText(initialAccountData.value.password);
    passwordCopied.value = true;
    setTimeout(() => {
        passwordCopied.value = false;
    }, 2500);
}

function openDeleteDialog(item: PegawaiItem) {
    deletingPegawai.value = item;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (!deletingPegawai.value) return;

    isDeleting.value = true;
    router.delete(`/pegawai/${deletingPegawai.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            deletingPegawai.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}

function getJenisLabel(type: string): string {
    switch (type) {
        case 'guru':
            return 'Guru';
        case 'tu':
            return 'Tata Usaha';
        case 'kepsek':
            return 'Kepala Sekolah';
        default:
            return type;
    }
}

function getJenisVariant(type: string): 'default' | 'secondary' | 'outline' {
    switch (type) {
        case 'guru':
            return 'default';
        case 'tu':
            return 'secondary';
        case 'kepsek':
            return 'outline';
        default:
            return 'outline';
    }
}

function getRoleLabel(role: string): string {
    switch (role) {
        case 'waka_kurikulum':
            return 'Waka Kurikulum';
        case 'wali_kelas':
            return 'Wali Kelas';
        case 'operator':
            return 'Operator';
        case 'kepsek':
            return 'Kepala Sekolah';
        case 'guru':
            return 'Guru';
        default:
            return role;
    }
}

function getStatusLabel(status: string): string {
    switch (status) {
        case 'pns':
            return 'PNS';
        case 'pppk':
            return 'PPPK';
        case 'gty':
            return 'GTY';
        case 'gtt':
            return 'GTT';
        case 'honorer':
            return 'Honorer';
        default:
            return status?.toUpperCase() || '-';
    }
}
</script>

<template>
    <Head title="Data Pegawai" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    <Users class="h-6 w-6 text-primary" />
                    Data Pegawai & Guru
                </h1>
                <p class="text-xs text-muted-foreground mt-1">
                    Kelola data identitas, akun sistem, beban mengajar, dan preferensi jadwal guru.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button v-if="canManage" variant="outline" size="sm" class="gap-1.5 text-xs shadow-xs" as-child>
                    <a href="/pegawai/kartu/cetak-massal" target="_blank" class="flex items-center">
                        <Printer class="h-4 w-4" />
                        Cetak Kartu Pegawai
                    </a>
                </Button>
                <Button v-if="canManage" size="sm" class="gap-1.5 text-xs shadow-xs" @click="openCreateDialog">
                    <Plus class="h-4 w-4" />
                    Tambah Pegawai
                </Button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between bg-card p-3 rounded-lg border shadow-xs">
            <div class="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Cari nama, NIP, atau NUPTK..."
                        class="pl-8 h-9 text-xs"
                        @input="handleSearchInput"
                    />
                </div>

                <Select v-model="jenis" @update:model-value="applyQuery">
                    <SelectTrigger class="w-full sm:w-[150px] h-9 text-xs">
                        <SelectValue placeholder="Semua Jenis" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Jenis</SelectItem>
                        <SelectItem value="guru">Guru</SelectItem>
                        <SelectItem value="tu">Tata Usaha</SelectItem>
                        <SelectItem value="kepsek">Kepala Sekolah</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="statusKepegawaian" @update:model-value="applyQuery">
                    <SelectTrigger class="w-full sm:w-[150px] h-9 text-xs">
                        <SelectValue placeholder="Semua Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Status</SelectItem>
                        <SelectItem value="pns">PNS</SelectItem>
                        <SelectItem value="pppk">PPPK</SelectItem>
                        <SelectItem value="gty">GTY</SelectItem>
                        <SelectItem value="gtt">GTT</SelectItem>
                        <SelectItem value="honorer">Honorer</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="currentSemester" class="text-xs text-muted-foreground flex items-center gap-1.5 self-end md:self-center">
                <Calendar class="h-3.5 w-3.5 text-primary" />
                <span>Semester Aktif: <strong class="text-foreground">{{ currentSemester.nama }}</strong></span>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-card rounded-lg border shadow-xs overflow-hidden">
            <DataTable
                :columns="columnDefinitions"
                :data="pegawai.data"
                :pagination="paginationData"
                :sort-by="sortBy"
                :sort-order="sortOrder"
                @sort="handleSort"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
            >
                <!-- Nama & Identity -->
                <template #cell-nama="{ row }: { row: PegawaiItem }">
                    <div class="flex flex-col py-1">
                        <div class="font-medium text-foreground text-xs flex items-center gap-1.5">
                            {{ row.nama }}
                            <Badge v-if="row.user?.must_change_password" variant="outline" class="text-[10px] py-0 px-1 border-amber-500 text-amber-600 bg-amber-50 dark:bg-amber-950/20">
                                Password Baru
                            </Badge>
                        </div>
                        <div class="text-[11px] text-muted-foreground flex items-center gap-2 mt-0.5">
                            <span v-if="row.nip" class="font-mono">NIP: {{ row.nip }}</span>
                            <span v-else-if="row.nuptk" class="font-mono">NUPTK: {{ row.nuptk }}</span>
                            <span v-else class="italic">Tanpa NIP/NUPTK</span>
                            <span v-if="row.email || row.user?.email" class="text-muted-foreground/60">•</span>
                            <span v-if="row.email || row.user?.email" class="truncate max-w-[180px]">{{ row.email || row.user?.email }}</span>
                        </div>
                    </div>
                </template>

                <!-- Jenis Pegawai & Peran Tambahan -->
                <template #cell-jenis="{ row }: { row: PegawaiItem }">
                    <div class="flex flex-wrap gap-1 items-center">
                        <Badge :variant="getJenisVariant(row.jenis)" class="text-[11px] capitalize font-medium">
                            {{ getJenisLabel(row.jenis) }}
                        </Badge>
                        <template v-if="row.roles && row.roles.length > 0">
                            <Badge
                                v-for="role in row.roles.filter((r) => r !== row.jenis && !(row.jenis === 'tu' && r === 'operator'))"
                                :key="role"
                                variant="outline"
                                class="text-[10px] py-0 px-1.5 border-primary/40 text-primary font-normal"
                            >
                                {{ getRoleLabel(role) }}
                            </Badge>
                        </template>
                    </div>
                </template>

                <!-- Status Kepegawaian -->
                <template #cell-status_kepegawaian="{ row }: { row: PegawaiItem }">
                    <span class="text-xs font-medium text-foreground/80">
                        {{ getStatusLabel(row.status_kepegawaian) }}
                    </span>
                </template>

                <!-- Beban Mengajar (T-06.05) -->
                <template #cell-beban_mengajar="{ row }: { row: PegawaiItem }">
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col">
                            <div class="text-xs font-semibold flex items-center gap-1">
                                <span
                                    :class="[
                                        (row.beban_mengajar_aktual ?? 0) > row.jam_maks_per_minggu
                                            ? 'text-destructive font-bold'
                                            : 'text-foreground',
                                    ]"
                                >
                                    {{ row.beban_mengajar_aktual ?? 0 }}
                                </span>
                                <span class="text-muted-foreground font-normal">/ {{ row.jam_maks_per_minggu }} JP</span>
                            </div>
                            <span v-if="(row.beban_mengajar_aktual ?? 0) > row.jam_maks_per_minggu" class="text-[10px] text-destructive flex items-center gap-0.5 mt-0.5">
                                <AlertTriangle class="h-3 w-3" />
                                Melebihi batas
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Hari Bebas / Tidak Mengajar -->
                <template #cell-hari_tidak_mengajar="{ row }: { row: PegawaiItem }">
                    <div v-if="row.hari_tidak_mengajar && row.hari_tidak_mengajar.length > 0" class="flex flex-wrap gap-1">
                        <Badge
                            v-for="hari in row.hari_tidak_mengajar"
                            :key="hari"
                            variant="secondary"
                            class="text-[10px] py-0 px-1.5 uppercase font-medium bg-muted/60"
                        >
                            {{ hari }}
                        </Badge>
                    </div>
                    <span v-else class="text-xs text-muted-foreground italic">-</span>
                </template>

                <!-- Kontak -->
                <template #cell-kontak="{ row }: { row: PegawaiItem }">
                    <div class="flex flex-col text-xs text-muted-foreground">
                        <span v-if="row.no_hp" class="flex items-center gap-1">
                            <Phone class="h-3 w-3" />
                            {{ row.no_hp }}
                        </span>
                        <span v-else class="italic">-</span>
                    </div>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }: { row: PegawaiItem }">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel class="text-xs">Aksi Pegawai</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-if="canManage" class="text-xs cursor-pointer" @click="openEditDialog(row)">
                                <Pencil class="h-3.5 w-3.5 mr-2" />
                                Ubah Data
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child class="text-xs cursor-pointer">
                                <a :href="`/pegawai/${row.id}/kartu`" target="_blank" class="flex items-center w-full">
                                    <CreditCard class="h-3.5 w-3.5 mr-2" />
                                    Cetak Kartu
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="canManage"
                                class="text-xs text-destructive focus:text-destructive cursor-pointer"
                                @click="openDeleteDialog(row)"
                            >
                                <Trash2 class="h-3.5 w-3.5 mr-2" />
                                Hapus Pegawai
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Create / Edit Dialog -->
    <PegawaiFormDialog
        v-model:open="formDialogOpen"
        :pegawai="editingPegawai"
        @success="handleFormSuccess"
    />

    <!-- One-Time Initial Password Modal (T-06.04) -->
    <Dialog :open="initialPasswordDialogOpen" @update:open="(val: boolean) => initialPasswordDialogOpen = val">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base font-semibold flex items-center gap-2 text-foreground">
                    <KeyRound class="h-5 w-5 text-amber-500" />
                    Kata Sandi Akun Baru
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Akun sistem berhasil dibuat untuk <strong>{{ initialAccountData?.nama }}</strong>.
                </DialogDescription>
            </DialogHeader>

            <Alert class="bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-200">
                <AlertTriangle class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                <AlertTitle class="text-xs font-semibold">Penting: Kata sandi hanya tampil sekali!</AlertTitle>
                <AlertDescription class="text-xs mt-1">
                    Salin dan berikan kredensial ini kepada pegawai bersangkutan. Pegawai diwajibkan mengganti kata sandi ini pada login pertama.
                </AlertDescription>
            </Alert>

            <div class="space-y-3 bg-muted/30 p-3.5 rounded-lg border text-xs">
                <div class="flex justify-between items-center py-1 border-b border-border/50">
                    <span class="text-muted-foreground">Email Login:</span>
                    <span class="font-mono font-medium text-foreground select-all">{{ initialAccountData?.email }}</span>
                </div>
                <div class="flex justify-between items-center py-1">
                    <span class="text-muted-foreground">Kata Sandi Awal:</span>
                    <div class="flex items-center gap-2">
                        <code class="px-2 py-1 rounded bg-background border font-mono font-bold text-sm tracking-wider select-all text-primary">
                            {{ initialAccountData?.password }}
                        </code>
                        <Button size="icon" variant="outline" class="h-8 w-8 shrink-0" @click="copyPassword">
                            <Check v-if="passwordCopied" class="h-4 w-4 text-emerald-600" />
                            <Copy v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter class="sm:justify-end">
                <Button size="sm" class="text-xs" @click="initialPasswordDialogOpen = false">
                    Saya Sudah Menyalin Kata Sandi
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="deleteDialogOpen" @update:open="(val: boolean) => deleteDialogOpen = val">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base font-semibold flex items-center gap-2 text-destructive">
                    <Trash2 class="h-5 w-5" />
                    Konfirmasi Hapus Pegawai
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Apakah Anda yakin ingin menghapus data pegawai <strong>{{ deletingPegawai?.nama }}</strong>?
                </DialogDescription>
            </DialogHeader>

            <p class="text-xs text-muted-foreground">
                Tindakan ini akan menghapus data kepegawaian dan akun sistem terkait. Riwayat yang tertaut mungkin ikut terpengaruh.
            </p>

            <DialogFooter class="sm:justify-end gap-2">
                <Button variant="outline" size="sm" class="text-xs" :disabled="isDeleting" @click="deleteDialogOpen = false">
                    Batal
                </Button>
                <Button variant="destructive" size="sm" class="text-xs" :disabled="isDeleting" @click="confirmDelete">
                    <Loader2 v-if="isDeleting" class="h-3.5 w-3.5 animate-spin mr-1.5" />
                    Hapus Pegawai
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
