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

interface RuangItem {
    id: string;
    kode: string;
    nama: string;
    kategori: string;
    kapasitas: number;
    lokasi: string | null;
    is_aktif: boolean;
}

defineProps<{
    ruang: RuangItem[];
    kategoriOptions: string[];
    filters: {
        kategori: string;
    };
    canManage: boolean;
}>();

const isCreateOpen = ref(false);
const editingRuang = ref<RuangItem | null>(null);

const createForm = useForm({
    kode: '',
    nama: '',
    kategori: 'kelas',
    kapasitas: 36,
    lokasi: '',
    is_aktif: true,
});

const editForm = useForm({
    kode: '',
    nama: '',
    kategori: 'kelas',
    kapasitas: 36,
    lokasi: '',
    is_aktif: true,
});

function onKategoriFilterChange(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    router.get(
        '/ruang',
        val ? { kategori: val } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function submitCreate() {
    createForm.post('/ruang', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
}

function openEdit(item: RuangItem) {
    editingRuang.value = item;
    editForm.kode = item.kode;
    editForm.nama = item.nama;
    editForm.kategori = item.kategori;
    editForm.kapasitas = item.kapasitas;
    editForm.lokasi = item.lokasi ?? '';
    editForm.is_aktif = item.is_aktif;
}

function submitEdit() {
    if (!editingRuang.value) return;
    editForm.put(`/ruang/${editingRuang.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingRuang.value = null;
        },
    });
}

function deleteRuang(id: string) {
    if (confirm('Hapus ruang / fasilitas ini?')) {
        useForm({}).delete(`/ruang/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Ruang & Fasilitas" />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Ruang & Fasilitas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Pengelolaan ruang kelas, laboratorium, dan sarana prasarana sekolah.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Filter Kategori -->
                <div class="flex items-center gap-2">
                    <label for="filter-kategori" class="text-xs font-medium text-muted-foreground">
                        Kategori:
                    </label>
                    <select
                        id="filter-kategori"
                        :value="filters.kategori"
                        class="focus-visible:outline-xs h-9 rounded-md border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                        @change="onKategoriFilterChange"
                    >
                        <option value="">Semua Kategori</option>
                        <option v-for="kat in kategoriOptions" :key="kat" :value="kat">
                            {{ kat.toUpperCase() }}
                        </option>
                    </select>
                </div>

                <div v-if="canManage">
                    <Dialog :open="isCreateOpen" @update:open="(val: boolean) => (isCreateOpen = val)">
                        <DialogTrigger as-child>
                            <Button>+ Tambah Ruang</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Tambah Ruang Baru</DialogTitle>
                                <DialogDescription>
                                    Input data ruang beserta kapasitas dan peruntukannya.
                                </DialogDescription>
                            </DialogHeader>

                            <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="space-y-2">
                                        <Label for="new-kode">Kode Ruang</Label>
                                        <Input
                                            id="new-kode"
                                            v-model="createForm.kode"
                                            placeholder="R.101"
                                            required
                                        />
                                        <span v-if="createForm.errors.kode" class="text-xs text-destructive">
                                            {{ createForm.errors.kode }}
                                        </span>
                                    </div>
                                    <div class="col-span-2 space-y-2">
                                        <Label for="new-nama">Nama Ruang</Label>
                                        <Input
                                            id="new-nama"
                                            v-model="createForm.nama"
                                            placeholder="Ruang Kelas X-A"
                                            required
                                        />
                                        <span v-if="createForm.errors.nama" class="text-xs text-destructive">
                                            {{ createForm.errors.nama }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="new-kategori">Kategori</Label>
                                        <select
                                            id="new-kategori"
                                            v-model="createForm.kategori"
                                            class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                            required
                                        >
                                            <option v-for="kat in kategoriOptions" :key="kat" :value="kat">
                                                {{ kat }}
                                            </option>
                                        </select>
                                        <span v-if="createForm.errors.kategori" class="text-xs text-destructive">
                                            {{ createForm.errors.kategori }}
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="new-kapasitas">Kapasitas (Siswa)</Label>
                                        <Input
                                            id="new-kapasitas"
                                            type="number"
                                            min="1"
                                            max="500"
                                            v-model.number="createForm.kapasitas"
                                            required
                                        />
                                        <span v-if="createForm.errors.kapasitas" class="text-xs text-destructive">
                                            {{ createForm.errors.kapasitas }}
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="new-lokasi">Lokasi / Gedung</Label>
                                    <Input
                                        id="new-lokasi"
                                        v-model="createForm.lokasi"
                                        placeholder="Gedung A Lantai 2"
                                    />
                                    <span v-if="createForm.errors.lokasi" class="text-xs text-destructive">
                                        {{ createForm.errors.lokasi }}
                                    </span>
                                </div>

                                <div class="flex items-center space-x-2 pt-2">
                                    <Checkbox
                                        id="new-aktif"
                                        :checked="createForm.is_aktif"
                                        @update:checked="(val: boolean) => (createForm.is_aktif = val)"
                                    />
                                    <Label for="new-aktif" class="text-sm font-normal cursor-pointer">
                                        Ruang aktif dan siap digunakan
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
                                        Simpan Ruang
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>

        <!-- Table Ruangan -->
        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-24">Kode</TableHead>
                            <TableHead>Nama Ruang</TableHead>
                            <TableHead class="w-32">Kategori</TableHead>
                            <TableHead class="w-28 text-center">Kapasitas</TableHead>
                            <TableHead>Lokasi</TableHead>
                            <TableHead class="w-24">Status</TableHead>
                            <TableHead v-if="canManage" class="w-28 text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="ruang.length === 0">
                            <TableCell :colspan="canManage ? 7 : 6" class="py-8 text-center text-muted-foreground">
                                Belum ada ruangan terdaftar.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="r in ruang" :key="r.id">
                            <TableCell class="font-mono font-medium">
                                {{ r.kode }}
                            </TableCell>
                            <TableCell class="font-medium text-foreground">
                                {{ r.nama }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" class="capitalize">
                                    {{ r.kategori }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center font-mono">
                                {{ r.kapasitas }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ r.lokasi || '-' }}
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
                                        @click="deleteRuang(r.id)"
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
        <Dialog :open="!!editingRuang" @update:open="(val: boolean) => { if (!val) editingRuang = null; }">
            <DialogContent v-if="editingRuang">
                <DialogHeader>
                    <DialogTitle>Edit Ruang</DialogTitle>
                    <DialogDescription>
                        Perbarui rincian ruang {{ editingRuang.kode }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-kode">Kode Ruang</Label>
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
                            <Label for="edit-nama">Nama Ruang</Label>
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
                            <Label for="edit-kategori">Kategori</Label>
                            <select
                                id="edit-kategori"
                                v-model="editForm.kategori"
                                class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                required
                            >
                                <option v-for="kat in kategoriOptions" :key="kat" :value="kat">
                                    {{ kat }}
                                </option>
                            </select>
                            <span v-if="editForm.errors.kategori" class="text-xs text-destructive">
                                {{ editForm.errors.kategori }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-kapasitas">Kapasitas (Siswa)</Label>
                            <Input
                                id="edit-kapasitas"
                                type="number"
                                min="1"
                                max="500"
                                v-model.number="editForm.kapasitas"
                                required
                            />
                            <span v-if="editForm.errors.kapasitas" class="text-xs text-destructive">
                                {{ editForm.errors.kapasitas }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-lokasi">Lokasi / Gedung</Label>
                        <Input
                            id="edit-lokasi"
                            v-model="editForm.lokasi"
                        />
                        <span v-if="editForm.errors.lokasi" class="text-xs text-destructive">
                            {{ editForm.errors.lokasi }}
                        </span>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="edit-aktif"
                            :checked="editForm.is_aktif"
                            @update:checked="(val: boolean) => (editForm.is_aktif = val)"
                        />
                        <Label for="edit-aktif" class="text-sm font-normal cursor-pointer">
                            Ruang aktif dan siap digunakan
                        </Label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingRuang = null"
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
