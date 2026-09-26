<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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

interface JurusanItem {
    id: string;
    kode: string;
    nama: string;
    bidang_keahlian: string | null;
    program_keahlian: string | null;
    is_aktif: boolean;
}

const props = defineProps<{
    jurusan: JurusanItem[];
    isApplicable: boolean;
    jenjang: string;
    canManage: boolean;
}>();

const isCreateOpen = ref(false);
const editingJurusan = ref<JurusanItem | null>(null);

const createForm = useForm({
    kode: '',
    nama: '',
    bidang_keahlian: '',
    program_keahlian: '',
    is_aktif: true,
});

const editForm = useForm({
    kode: '',
    nama: '',
    bidang_keahlian: '',
    program_keahlian: '',
    is_aktif: true,
});

function submitCreate() {
    createForm.post('/jurusan', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
}

function openEdit(item: JurusanItem) {
    editingJurusan.value = item;
    editForm.kode = item.kode;
    editForm.nama = item.nama;
    editForm.bidang_keahlian = item.bidang_keahlian ?? '';
    editForm.program_keahlian = item.program_keahlian ?? '';
    editForm.is_aktif = item.is_aktif;
}

function submitEdit() {
    if (!editingJurusan.value) return;
    editForm.put(`/jurusan/${editingJurusan.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingJurusan.value = null;
        },
    });
}

function deleteJurusan(id: string) {
    if (confirm('Hapus jurusan ini?')) {
        useForm({}).delete(`/jurusan/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Jurusan / Program Keahlian" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Jurusan / Program Keahlian
                </h1>
                <p class="text-sm text-muted-foreground">
                    Pengelolaan konsentrasi jurusan atau paket keahlian siswa.
                </p>
            </div>

            <div v-if="isApplicable && canManage">
                <Dialog :open="isCreateOpen" @update:open="(val: boolean) => (isCreateOpen = val)">
                    <DialogTrigger as-child>
                        <Button>+ Tambah Jurusan</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Tambah Jurusan Baru</DialogTitle>
                            <DialogDescription>
                                Masukkan kode dan spesifikasi program keahlian.
                            </DialogDescription>
                        </DialogHeader>

                        <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                            <div class="grid grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label for="new-kode">Kode</Label>
                                    <Input
                                        id="new-kode"
                                        v-model="createForm.kode"
                                        placeholder="RPL"
                                        required
                                    />
                                    <span v-if="createForm.errors.kode" class="text-xs text-destructive">
                                        {{ createForm.errors.kode }}
                                    </span>
                                </div>
                                <div class="col-span-2 space-y-2">
                                    <Label for="new-nama">Nama Jurusan</Label>
                                    <Input
                                        id="new-nama"
                                        v-model="createForm.nama"
                                        placeholder="Rekayasa Perangkat Lunak"
                                        required
                                    />
                                    <span v-if="createForm.errors.nama" class="text-xs text-destructive">
                                        {{ createForm.errors.nama }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="new-bidang">Bidang Keahlian</Label>
                                <Input
                                    id="new-bidang"
                                    v-model="createForm.bidang_keahlian"
                                    placeholder="Teknologi Informasi"
                                />
                                <span v-if="createForm.errors.bidang_keahlian" class="text-xs text-destructive">
                                    {{ createForm.errors.bidang_keahlian }}
                                </span>
                            </div>

                            <div class="space-y-2">
                                <Label for="new-program">Program Keahlian</Label>
                                <Input
                                    id="new-program"
                                    v-model="createForm.program_keahlian"
                                    placeholder="Pengembangan Perangkat Lunak & Gim"
                                />
                                <span v-if="createForm.errors.program_keahlian" class="text-xs text-destructive">
                                    {{ createForm.errors.program_keahlian }}
                                </span>
                            </div>

                            <div class="flex items-center space-x-2 pt-2">
                                <Checkbox
                                    id="new-aktif"
                                    :checked="createForm.is_aktif"
                                    @update:checked="(val: boolean) => (createForm.is_aktif = val)"
                                />
                                <Label for="new-aktif" class="text-sm font-normal cursor-pointer">
                                    Jurusan aktif
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
                                    Simpan Jurusan
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Not Applicable Banner for SD / SMP -->
        <Card v-if="!isApplicable" class="border-muted bg-muted/30">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <span class="text-2xl">ℹ️</span>
                    <div>
                        <CardTitle class="text-base">
                            Jenjang {{ jenjang }} Tidak Menggunakan Jurusan
                        </CardTitle>
                        <CardDescription>
                            Sesuai struktur kurikulum nasional, peminatan / konsentrasi jurusan atau kompetensi keahlian hanya diterapkan pada jenjang SMA, SMK, atau setara.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <!-- Table Jurusan -->
        <Card v-else>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-24">Kode</TableHead>
                            <TableHead>Nama Jurusan</TableHead>
                            <TableHead>Bidang Keahlian</TableHead>
                            <TableHead>Program Keahlian</TableHead>
                            <TableHead class="w-24">Status</TableHead>
                            <TableHead v-if="canManage" class="w-28 text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="jurusan.length === 0">
                            <TableCell :colspan="canManage ? 6 : 5" class="py-8 text-center text-muted-foreground">
                                Belum ada jurusan yang terdaftar.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="j in jurusan" :key="j.id">
                            <TableCell class="font-mono font-medium">
                                {{ j.kode }}
                            </TableCell>
                            <TableCell class="font-medium text-foreground">
                                {{ j.nama }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ j.bidang_keahlian || '-' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ j.program_keahlian || '-' }}
                            </TableCell>
                            <TableCell>
                                <Badge v-if="j.is_aktif" variant="secondary" class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-0">
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
                                        @click="openEdit(j)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10"
                                        @click="deleteJurusan(j.id)"
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
        <Dialog :open="!!editingJurusan" @update:open="(val: boolean) => { if (!val) editingJurusan = null; }">
            <DialogContent v-if="editingJurusan">
                <DialogHeader>
                    <DialogTitle>Edit Jurusan</DialogTitle>
                    <DialogDescription>
                        Perbarui rincian jurusan {{ editingJurusan.kode }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-kode">Kode</Label>
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
                            <Label for="edit-nama">Nama Jurusan</Label>
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

                    <div class="space-y-2">
                        <Label for="edit-bidang">Bidang Keahlian</Label>
                        <Input
                            id="edit-bidang"
                            v-model="editForm.bidang_keahlian"
                        />
                        <span v-if="editForm.errors.bidang_keahlian" class="text-xs text-destructive">
                            {{ editForm.errors.bidang_keahlian }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-program">Program Keahlian</Label>
                        <Input
                            id="edit-program"
                            v-model="editForm.program_keahlian"
                        />
                        <span v-if="editForm.errors.program_keahlian" class="text-xs text-destructive">
                            {{ editForm.errors.program_keahlian }}
                        </span>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="edit-aktif"
                            :checked="editForm.is_aktif"
                            @update:checked="(val: boolean) => (editForm.is_aktif = val)"
                        />
                        <Label for="edit-aktif" class="text-sm font-normal cursor-pointer">
                            Jurusan aktif
                        </Label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingJurusan = null"
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
