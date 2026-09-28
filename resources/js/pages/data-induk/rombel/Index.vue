<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface WaliKelasOption {
    id: string;
    nama: string;
    nip: string | null;
    nuptk: string | null;
}

interface JurusanOption {
    id: string;
    kode: string;
    nama: string;
}

interface RuangOption {
    id: string;
    kode: string;
    nama: string;
    kategori: string;
}

interface SemesterInfo {
    id: string;
    nama: string;
    tahun_ajaran?: {
        nama: string;
    };
}

interface RombelItem {
    id: string;
    nama: string;
    tingkat: number;
    kuota: number;
    jurusan_id: string | null;
    jurusan?: JurusanOption | null;
    wali_kelas_id: string | null;
    wali_kelas?: WaliKelasOption | null;
    ruang_id: string | null;
    ruang?: RuangOption | null;
    is_aktif: boolean;
}

defineProps<{
    rombel: RombelItem[];
    currentSemester: SemesterInfo | null;
    waliKelasList: WaliKelasOption[];
    jurusanList: JurusanOption[];
    ruangList: RuangOption[];
    filters: {
        tingkat: string;
    };
    canManage: boolean;
}>();

const isCreateOpen = ref(false);
const editingRombel = ref<RombelItem | null>(null);

const createForm = useForm({
    nama: '',
    tingkat: 10,
    kuota: 36,
    jurusan_id: '',
    wali_kelas_id: '',
    ruang_id: '',
    is_aktif: true,
});

const editForm = useForm({
    nama: '',
    tingkat: 10,
    kuota: 36,
    jurusan_id: '',
    wali_kelas_id: '',
    ruang_id: '',
    is_aktif: true,
});

function onTingkatFilterChange(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    router.get(
        '/rombel',
        val ? { tingkat: val } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function submitCreate() {
    createForm.post('/rombel', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
}

function openEdit(item: RombelItem) {
    editingRombel.value = item;
    editForm.nama = item.nama;
    editForm.tingkat = item.tingkat;
    editForm.kuota = item.kuota;
    editForm.jurusan_id = item.jurusan_id ?? '';
    editForm.wali_kelas_id = item.wali_kelas_id ?? '';
    editForm.ruang_id = item.ruang_id ?? '';
    editForm.is_aktif = item.is_aktif;
}

function submitEdit() {
    if (!editingRombel.value) return;
    editForm.put(`/rombel/${editingRombel.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingRombel.value = null;
        },
    });
}

function deleteRombel(id: string) {
    if (confirm('Hapus rombongan belajar ini?')) {
        useForm({}).delete(`/rombel/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Rombongan Belajar (Rombel)" />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Rombongan Belajar (Rombel)
                </h1>
                <p class="text-sm text-muted-foreground">
                    Pengelolaan kelas, pembagian wali kelas, dan ruang basis per semester.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Filter Tingkat -->
                <div class="flex items-center gap-2">
                    <label for="filter-tingkat" class="text-xs font-medium text-muted-foreground">
                        Tingkat:
                    </label>
                    <select
                        id="filter-tingkat"
                        :value="filters.tingkat"
                        class="focus-visible:outline-xs h-9 rounded-md border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                        @change="onTingkatFilterChange"
                    >
                        <option value="">Semua Tingkat</option>
                        <option v-for="t in [1,2,3,4,5,6,7,8,9,10,11,12]" :key="t" :value="t">
                            Kelas {{ t }}
                        </option>
                    </select>
                </div>

                <div v-if="canManage && currentSemester">
                    <Dialog :open="isCreateOpen" @update:open="(val: boolean) => (isCreateOpen = val)">
                        <DialogTrigger as-child>
                            <Button>+ Tambah Rombel</Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Tambah Rombel Baru</DialogTitle>
                                <DialogDescription>
                                    Periode: TA {{ currentSemester.tahun_ajaran?.nama }} - Semester {{ currentSemester.nama }}
                                </DialogDescription>
                            </DialogHeader>

                            <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="col-span-2 space-y-2">
                                        <Label for="new-nama">Nama Rombel</Label>
                                        <Input
                                            id="new-nama"
                                            v-model="createForm.nama"
                                            placeholder="Contoh: X RPL 1"
                                            required
                                        />
                                        <span v-if="createForm.errors.nama" class="text-xs text-destructive">
                                            {{ createForm.errors.nama }}
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="new-tingkat">Tingkat</Label>
                                        <Input
                                            id="new-tingkat"
                                            type="number"
                                            min="1"
                                            max="12"
                                            v-model.number="createForm.tingkat"
                                            required
                                        />
                                        <span v-if="createForm.errors.tingkat" class="text-xs text-destructive">
                                            {{ createForm.errors.tingkat }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="new-kuota">Kuota Maksimal</Label>
                                        <Input
                                            id="new-kuota"
                                            type="number"
                                            min="1"
                                            max="100"
                                            v-model.number="createForm.kuota"
                                            required
                                        />
                                        <span v-if="createForm.errors.kuota" class="text-xs text-destructive">
                                            {{ createForm.errors.kuota }}
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="new-jurusan">Jurusan (Jika Ada)</Label>
                                        <select
                                            id="new-jurusan"
                                            v-model="createForm.jurusan_id"
                                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                        >
                                            <option value="">Non-Jurusan / Umum</option>
                                            <option v-for="j in jurusanList" :key="j.id" :value="j.id">
                                                {{ j.kode }} - {{ j.nama }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="new-wali">Wali Kelas</Label>
                                    <select
                                        id="new-wali"
                                        v-model="createForm.wali_kelas_id"
                                        class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="">Belum Ditentukan</option>
                                        <option v-for="w in waliKelasList" :key="w.id" :value="w.id">
                                            {{ w.nama }} {{ w.nip ? `(NIP: ${w.nip})` : '' }}
                                        </option>
                                    </select>
                                    <span v-if="createForm.errors.wali_kelas_id" class="text-xs text-destructive">
                                        {{ createForm.errors.wali_kelas_id }}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    <Label for="new-ruang">Ruang Basis (Home Room)</Label>
                                    <select
                                        id="new-ruang"
                                        v-model="createForm.ruang_id"
                                        class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="">Belum Ditentukan</option>
                                        <option v-for="r in ruangList" :key="r.id" :value="r.id">
                                            {{ r.kode }} - {{ r.nama }} ({{ r.kategori }})
                                        </option>
                                    </select>
                                </div>

                                <div class="flex items-center space-x-2 pt-2">
                                    <Checkbox
                                        id="new-aktif"
                                        :checked="createForm.is_aktif"
                                        @update:checked="(val: boolean) => (createForm.is_aktif = val)"
                                    />
                                    <Label for="new-aktif" class="text-sm font-normal cursor-pointer">
                                        Rombel aktif beroperasi
                                    </Label>
                                </div>

                                <DialogFooter class="pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="isCreateOpen = false"
                                    >
                                        Batal
                                    </Button>
                                    <Button type="submit" :disabled="createForm.processing">
                                        Simpan Rombel
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>

        <!-- No Semester Warning -->
        <Card v-if="!currentSemester" class="border-amber-500/40 bg-amber-500/10">
            <CardHeader>
                <CardTitle class="text-base text-amber-900 dark:text-amber-200">
                    Tidak Ada Semester Aktif
                </CardTitle>
                <CardDescription class="text-amber-800 dark:text-amber-300">
                    Silakan tambahkan atau aktifkan Tahun Ajaran & Semester terlebih dahulu di menu Tahun Ajaran sebelum mengelola Rombel.
                </CardDescription>
            </CardHeader>
        </Card>

        <!-- Table Rombel -->
        <Card v-else>
            <CardContent class="p-0">
                <TooltipProvider>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-24">Tingkat</TableHead>
                                <TableHead>Nama Rombel</TableHead>
                                <TableHead>Jurusan</TableHead>
                                <TableHead>Wali Kelas</TableHead>
                                <TableHead>Ruang Basis</TableHead>
                                <TableHead class="w-32 text-center">Siswa / Kuota</TableHead>
                                <TableHead class="w-24">Status</TableHead>
                                <TableHead v-if="canManage" class="w-28 text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="rombel.length === 0">
                                <TableCell :colspan="canManage ? 8 : 7" class="py-8 text-center text-muted-foreground">
                                    Belum ada rombongan belajar terdaftar pada semester ini.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="r in rombel" :key="r.id">
                                <TableCell class="font-mono font-medium">
                                    Kelas {{ r.tingkat }}
                                </TableCell>
                                <TableCell class="font-medium text-foreground">
                                    {{ r.nama }}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ r.jurusan ? r.jurusan.kode : '-' }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ r.wali_kelas ? r.wali_kelas.nama : '-' }}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ r.ruang ? `${r.ruang.kode} (${r.ruang.nama})` : '-' }}
                                </TableCell>
                                <TableCell class="text-center font-mono">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="cursor-help rounded px-1.5 py-0.5 hover:bg-muted underline decoration-dotted underline-offset-4">
                                                0 / {{ r.kuota }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p class="max-w-xs text-xs">
                                                Jumlah siswa rombel akan tersedia pada Minggu 5 (Data Siswa). Angka saat ini menunjukkan kuota maksimal.
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="r.is_aktif" variant="secondary" class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-0">
                                        Aktif
                                    </Badge>
                                    <Badge v-else variant="outline" class="text-muted-foreground">
                                        Non-Aktif
                                    </Badge>
                                </TableCell>
                                <TableCell v-if="canManage" class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openEdit(r)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10"
                                            @click="deleteRombel(r.id)"
                                        >
                                            Hapus
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </TooltipProvider>
            </CardContent>
        </Card>

        <!-- Edit Modal Dialog -->
        <Dialog :open="!!editingRombel" @update:open="(val: boolean) => { if (!val) editingRombel = null; }">
            <DialogContent v-if="editingRombel" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit Rombel</DialogTitle>
                    <DialogDescription>
                        Perbarui rincian rombongan belajar {{ editingRombel.nama }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2 space-y-2">
                            <Label for="edit-nama">Nama Rombel</Label>
                            <Input
                                id="edit-nama"
                                v-model="editForm.nama"
                                required
                            />
                            <span v-if="editForm.errors.nama" class="text-xs text-destructive">
                                {{ editForm.errors.nama }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-tingkat">Tingkat</Label>
                            <Input
                                id="edit-tingkat"
                                type="number"
                                min="1"
                                max="12"
                                v-model.number="editForm.tingkat"
                                required
                            />
                            <span v-if="editForm.errors.tingkat" class="text-xs text-destructive">
                                {{ editForm.errors.tingkat }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-kuota">Kuota Maksimal</Label>
                            <Input
                                id="edit-kuota"
                                type="number"
                                min="1"
                                max="100"
                                v-model.number="editForm.kuota"
                                required
                            />
                            <span v-if="editForm.errors.kuota" class="text-xs text-destructive">
                                {{ editForm.errors.kuota }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-jurusan">Jurusan (Jika Ada)</Label>
                            <select
                                id="edit-jurusan"
                                v-model="editForm.jurusan_id"
                                class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Non-Jurusan / Umum</option>
                                <option v-for="j in jurusanList" :key="j.id" :value="j.id">
                                    {{ j.kode }} - {{ j.nama }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-wali">Wali Kelas</Label>
                        <select
                            id="edit-wali"
                            v-model="editForm.wali_kelas_id"
                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Belum Ditentukan</option>
                            <option v-for="w in waliKelasList" :key="w.id" :value="w.id">
                                {{ w.nama }} {{ w.nip ? `(NIP: ${w.nip})` : '' }}
                            </option>
                        </select>
                        <span v-if="editForm.errors.wali_kelas_id" class="text-xs text-destructive">
                            {{ editForm.errors.wali_kelas_id }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-ruang">Ruang Basis (Home Room)</Label>
                        <select
                            id="edit-ruang"
                            v-model="editForm.ruang_id"
                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Belum Ditentukan</option>
                            <option v-for="r in ruangList" :key="r.id" :value="r.id">
                                {{ r.kode }} - {{ r.nama }} ({{ r.kategori }})
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="edit-aktif"
                            :checked="editForm.is_aktif"
                            @update:checked="(val: boolean) => (editForm.is_aktif = val)"
                        />
                        <Label for="edit-aktif" class="text-sm font-normal cursor-pointer">
                            Rombel aktif beroperasi
                        </Label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingRombel = null"
                        >
                            Batal
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            Simpan Perubahan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
