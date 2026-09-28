<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

interface JurusanOption {
    id: string;
    kode: string;
    nama: string;
}

interface MapelItem {
    id: string;
    kode: string;
    nama: string;
    kelompok: string;
    tingkat: number | null;
    jurusan_id: string | null;
    jurusan?: JurusanOption | null;
    bobot_beban_kognitif: 'ringan' | 'sedang' | 'berat';
    butuh_ruang_kategori: string | null;
    is_aktif: boolean;
}

const props = defineProps<{
    mataPelajaran: MapelItem[];
    jurusanList: JurusanOption[];
    kelompokOptions: string[];
    bobotOptions: string[];
    ruangKategoriOptions: string[];
    filters: {
        kelompok: string;
        bobot: string;
    };
    canManage: boolean;
}>();

const isCreateOpen = ref(false);
const editingMapel = ref<MapelItem | null>(null);

const createForm = useForm({
    kode: '',
    nama: '',
    kelompok: 'umum_a',
    tingkat: '',
    jurusan_id: '',
    bobot_beban_kognitif: 'sedang',
    butuh_ruang_kategori: '',
    is_aktif: true,
});

const editForm = useForm({
    kode: '',
    nama: '',
    kelompok: 'umum_a',
    tingkat: '',
    jurusan_id: '',
    bobot_beban_kognitif: 'sedang',
    butuh_ruang_kategori: '',
    is_aktif: true,
});

function applyFilter(kelompok = props.filters.kelompok, bobot = props.filters.bobot) {
    router.get(
        '/mata-pelajaran',
        {
            kelompok,
            bobot,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function onKelompokFilter(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    applyFilter(val, props.filters.bobot);
}

function onBobotFilter(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    applyFilter(props.filters.kelompok, val);
}

function submitCreate() {
    createForm.post('/mata-pelajaran', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
}

function openEdit(item: MapelItem) {
    editingMapel.value = item;
    editForm.kode = item.kode;
    editForm.nama = item.nama;
    editForm.kelompok = item.kelompok;
    editForm.tingkat = item.tingkat ? String(item.tingkat) : '';
    editForm.jurusan_id = item.jurusan_id ?? '';
    editForm.bobot_beban_kognitif = item.bobot_beban_kognitif;
    editForm.butuh_ruang_kategori = item.butuh_ruang_kategori ?? '';
    editForm.is_aktif = item.is_aktif;
}

function submitEdit() {
    if (!editingMapel.value) return;
    editForm.put(`/mata-pelajaran/${editingMapel.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingMapel.value = null;
        },
    });
}

function deleteMapel(id: string) {
    if (confirm('Hapus mata pelajaran ini?')) {
        useForm({}).delete(`/mata-pelajaran/${id}`, {
            preserveScroll: true,
        });
    }
}

function getBobotBadgeClass(bobot: string) {
    switch (bobot) {
        case 'berat':
            return 'bg-red-500/10 text-red-600 dark:text-red-400 border-0';
        case 'ringan':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-0';
        default:
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-0';
    }
}
</script>

<template>
    <Head title="Mata Pelajaran" />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Mata Pelajaran
                </h1>
                <p class="text-sm text-muted-foreground">
                    Daftar kurikulum mata pelajaran, kelompok, dan bobot kognitif penjadwalan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Filter Kelompok -->
                <select
                    id="filter-kelompok"
                    :value="filters.kelompok"
                    class="focus-visible:outline-xs h-9 rounded-md border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                    @change="onKelompokFilter"
                >
                    <option value="">Semua Kelompok</option>
                    <option v-for="k in kelompokOptions" :key="k" :value="k">
                        {{ k.replace('_', ' ').toUpperCase() }}
                    </option>
                </select>

                <!-- Filter Bobot -->
                <select
                    id="filter-bobot"
                    :value="filters.bobot"
                    class="focus-visible:outline-xs h-9 rounded-md border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                    @change="onBobotFilter"
                >
                    <option value="">Semua Bobot</option>
                    <option v-for="b in bobotOptions" :key="b" :value="b">
                        Bobot: {{ b.toUpperCase() }}
                    </option>
                </select>

                <div v-if="canManage">
                    <Dialog :open="isCreateOpen" @update:open="(val: boolean) => (isCreateOpen = val)">
                        <DialogTrigger as-child>
                            <Button>+ Tambah Mapel</Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Tambah Mata Pelajaran</DialogTitle>
                                <DialogDescription>
                                    Tentukan rincian kurikulum dan parameter beban kognitif mapel.
                                </DialogDescription>
                            </DialogHeader>

                            <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="space-y-2">
                                        <Label for="new-kode">Kode Mapel</Label>
                                        <Input
                                            id="new-kode"
                                            v-model="createForm.kode"
                                            placeholder="MTK"
                                            required
                                        />
                                        <span v-if="createForm.errors.kode" class="text-xs text-destructive">
                                            {{ createForm.errors.kode }}
                                        </span>
                                    </div>
                                    <div class="col-span-2 space-y-2">
                                        <Label for="new-nama">Nama Mata Pelajaran</Label>
                                        <Input
                                            id="new-nama"
                                            v-model="createForm.nama"
                                            placeholder="Matematika"
                                            required
                                        />
                                        <span v-if="createForm.errors.nama" class="text-xs text-destructive">
                                            {{ createForm.errors.nama }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="new-kelompok">Kelompok Mapel</Label>
                                        <select
                                            id="new-kelompok"
                                            v-model="createForm.kelompok"
                                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                            required
                                        >
                                            <option v-for="k in kelompokOptions" :key="k" :value="k">
                                                {{ k.replace('_', ' ') }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="new-bobot">Beban Kognitif</Label>
                                        <select
                                            id="new-bobot"
                                            v-model="createForm.bobot_beban_kognitif"
                                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                            required
                                        >
                                            <option v-for="b in bobotOptions" :key="b" :value="b">
                                                {{ b }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="new-tingkat">Tingkat / Kelas</Label>
                                        <Input
                                            id="new-tingkat"
                                            type="number"
                                            min="1"
                                            max="12"
                                            v-model="createForm.tingkat"
                                            placeholder="Semua (opsional)"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="new-jurusan">Spesifik Jurusan</Label>
                                        <select
                                            id="new-jurusan"
                                            v-model="createForm.jurusan_id"
                                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                        >
                                            <option value="">Umum (Semua Jurusan)</option>
                                            <option v-for="j in jurusanList" :key="j.id" :value="j.id">
                                                {{ j.kode }} - {{ j.nama }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="new-ruang">Kebutuhan Ruang Khusus</Label>
                                    <select
                                        id="new-ruang"
                                        v-model="createForm.butuh_ruang_kategori"
                                        class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="">Ruang Kelas Biasa (Tidak Butuh Ruang Khusus)</option>
                                        <option v-for="r in ruangKategoriOptions" :key="r" :value="r">
                                            {{ r }}
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
                                        Mapel aktif diajarkan
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
                                        Simpan Mapel
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>

        <!-- Table Mata Pelajaran -->
        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-24">Kode</TableHead>
                            <TableHead>Nama Mapel</TableHead>
                            <TableHead class="w-32">Kelompok</TableHead>
                            <TableHead class="w-28 text-center">Tingkat</TableHead>
                            <TableHead class="w-32">Jurusan</TableHead>
                            <TableHead class="w-28 text-center">Beban Kognitif</TableHead>
                            <TableHead class="w-32">Ruang Khusus</TableHead>
                            <TableHead class="w-24">Status</TableHead>
                            <TableHead v-if="canManage" class="w-28 text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="mataPelajaran.length === 0">
                            <TableCell :colspan="canManage ? 9 : 8" class="py-8 text-center text-muted-foreground">
                                Belum ada mata pelajaran terdaftar.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="m in mataPelajaran" :key="m.id">
                            <TableCell class="font-mono font-medium">
                                {{ m.kode }}
                            </TableCell>
                            <TableCell class="font-medium text-foreground">
                                {{ m.nama }}
                            </TableCell>
                            <TableCell class="capitalize text-muted-foreground text-xs">
                                {{ m.kelompok.replace('_', ' ') }}
                            </TableCell>
                            <TableCell class="text-center font-mono text-xs">
                                {{ m.tingkat ? `Kelas ${m.tingkat}` : 'Semua' }}
                            </TableCell>
                            <TableCell class="text-xs">
                                {{ m.jurusan ? m.jurusan.kode : 'Semua' }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="secondary" :class="getBobotBadgeClass(m.bobot_beban_kognitif)">
                                    {{ m.bobot_beban_kognitif }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-xs capitalize text-muted-foreground">
                                {{ m.butuh_ruang_kategori || 'Kelas Biasa' }}
                            </TableCell>
                            <TableCell>
                                <Badge v-if="m.is_aktif" variant="secondary" class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-0">
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
                                        @click="openEdit(m)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10"
                                        @click="deleteMapel(m.id)"
                                    >
                                        Hapus
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Edit Modal Dialog -->
        <Dialog :open="!!editingMapel" @update:open="(val: boolean) => { if (!val) editingMapel = null; }">
            <DialogContent v-if="editingMapel" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit Mata Pelajaran</DialogTitle>
                    <DialogDescription>
                        Perbarui rincian mata pelajaran {{ editingMapel.kode }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-kode">Kode Mapel</Label>
                            <Input
                                id="edit-kode"
                                v-model="editForm.kode"
                                required
                            />
                            <span v-if="editForm.errors.kode" class="text-xs text-destructive">
                                {{ editForm.errors.kode }}
                            </span>
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label for="edit-nama">Nama Mata Pelajaran</Label>
                            <Input
                                id="edit-nama"
                                v-model="editForm.nama"
                                required
                            />
                            <span v-if="editForm.errors.nama" class="text-xs text-destructive">
                                {{ editForm.errors.nama }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-kelompok">Kelompok Mapel</Label>
                            <select
                                id="edit-kelompok"
                                v-model="editForm.kelompok"
                                class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                required
                            >
                                <option v-for="k in kelompokOptions" :key="k" :value="k">
                                    {{ k.replace('_', ' ') }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="edit-bobot">Beban Kognitif</Label>
                            <select
                                id="edit-bobot"
                                v-model="editForm.bobot_beban_kognitif"
                                class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                required
                            >
                                <option v-for="b in bobotOptions" :key="b" :value="b">
                                    {{ b }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-tingkat">Tingkat / Kelas</Label>
                            <Input
                                id="edit-tingkat"
                                type="number"
                                min="1"
                                max="12"
                                v-model="editForm.tingkat"
                                placeholder="Semua (opsional)"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="edit-jurusan">Spesifik Jurusan</Label>
                            <select
                                id="edit-jurusan"
                                v-model="editForm.jurusan_id"
                                class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Umum (Semua Jurusan)</option>
                                <option v-for="j in jurusanList" :key="j.id" :value="j.id">
                                    {{ j.kode }} - {{ j.nama }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-ruang">Kebutuhan Ruang Khusus</Label>
                        <select
                            id="edit-ruang"
                            v-model="editForm.butuh_ruang_kategori"
                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Ruang Kelas Biasa (Tidak Butuh Ruang Khusus)</option>
                            <option v-for="r in ruangKategoriOptions" :key="r" :value="r">
                                {{ r }}
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
                            Mapel aktif diajarkan
                        </Label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingMapel = null"
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
