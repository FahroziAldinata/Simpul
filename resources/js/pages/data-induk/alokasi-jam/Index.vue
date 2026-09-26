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

interface RombelOption {
    id: string;
    nama: string;
    tingkat: number;
}

interface MapelOption {
    id: string;
    kode: string;
    nama: string;
    kelompok: string;
    bobot_beban_kognitif: string;
}

interface GuruOption {
    id: string;
    nama: string;
    nip: string | null;
}

interface AlokasiItem {
    id: string;
    rombel_id: string;
    mata_pelajaran_id: string;
    guru_id: string | null;
    jam_per_minggu: number;
    mata_pelajaran: MapelOption;
    guru?: GuruOption | null;
}

interface SemesterInfo {
    id: string;
    nama: string;
    tahun_ajaran?: {
        nama: string;
    };
}

const props = defineProps<{
    currentSemester: SemesterInfo | null;
    rombelList: RombelOption[];
    selectedRombel: RombelOption | null;
    alokasiList: AlokasiItem[];
    totalAlokasi: number;
    totalSlot: number;
    mapelList: MapelOption[];
    guruList: GuruOption[];
    canManage: boolean;
}>();

const isCreateOpen = ref(false);
const editingAlokasi = ref<AlokasiItem | null>(null);

const createForm = useForm({
    rombel_id: props.selectedRombel?.id ?? '',
    mata_pelajaran_id: '',
    guru_id: '',
    jam_per_minggu: 2,
});

const editForm = useForm({
    guru_id: '',
    jam_per_minggu: 2,
});

function onRombelChange(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    router.get(
        '/alokasi-jam',
        val ? { rombel_id: val } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function submitCreate() {
    createForm.rombel_id = props.selectedRombel?.id ?? '';
    createForm.post('/alokasi-jam', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset('mata_pelajaran_id', 'guru_id');
            createForm.jam_per_minggu = 2;
        },
    });
}

function openEdit(item: AlokasiItem) {
    editingAlokasi.value = item;
    editForm.guru_id = item.guru_id ?? '';
    editForm.jam_per_minggu = item.jam_per_minggu;
}

function submitEdit() {
    if (!editingAlokasi.value) return;
    editForm.put(`/alokasi-jam/${editingAlokasi.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingAlokasi.value = null;
        },
    });
}

function deleteAlokasi(id: string) {
    if (confirm('Hapus alokasi mapel ini dari rombel?')) {
        useForm({}).delete(`/alokasi-jam/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Alokasi Jam Mapel per Rombel" />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Alokasi Jam Pelajaran
                </h1>
                <p class="text-sm text-muted-foreground">
                    Distribusi beban jam mengajar mata pelajaran dan guru per rombongan belajar.
                </p>
            </div>

            <!-- Rombel Selector -->
            <div v-if="rombelList.length > 0" class="flex items-center gap-2">
                <label for="rombel-select" class="text-xs font-medium text-muted-foreground">
                    Pilih Rombel:
                </label>
                <select
                    id="rombel-select"
                    :value="selectedRombel?.id"
                    class="focus-visible:outline-xs h-9 rounded-md border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                    @change="onRombelChange"
                >
                    <option v-for="r in rombelList" :key="r.id" :value="r.id">
                        {{ r.nama }} (Kelas {{ r.tingkat }})
                    </option>
                </select>
            </div>
        </div>

        <!-- No Semester or Rombel Warning -->
        <Card v-if="!currentSemester || rombelList.length === 0" class="border-amber-500/40 bg-amber-500/10">
            <CardHeader>
                <CardTitle class="text-base text-amber-900 dark:text-amber-200">
                    {{ !currentSemester ? 'Tidak Ada Semester Aktif' : 'Belum Ada Rombongan Belajar' }}
                </CardTitle>
                <CardDescription class="text-amber-800 dark:text-amber-300">
                    {{ !currentSemester
                        ? 'Silakan aktifkan semester terlebih dahulu sebelum mengalokasikan jam pelajaran.'
                        : 'Silakan buat data Rombel terlebih dahulu pada menu Rombel sebelum mengatur alokasi mapel.'
                    }}
                </CardDescription>
            </CardHeader>
        </Card>

        <div v-else-if="selectedRombel" class="space-y-6">
            <!-- Summary Card Capacity Meter -->
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <CardTitle class="text-lg">
                                Beban Jam Rombel {{ selectedRombel.nama }}
                            </CardTitle>
                            <CardDescription>
                                Batas operasional sekolah: {{ totalSlot }} JP / minggu
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                v-if="totalAlokasi === totalSlot"
                                class="bg-emerald-600 text-white font-medium"
                            >
                                Lengkap ({{ totalAlokasi }} / {{ totalSlot }} JP)
                            </Badge>
                            <Badge
                                v-else-if="totalAlokasi < totalSlot"
                                variant="secondary"
                                class="bg-amber-500/15 text-amber-800 dark:text-amber-300 border border-amber-500/30"
                            >
                                Sisa {{ totalSlot - totalAlokasi }} JP Belum Dialokasikan ({{ totalAlokasi }} / {{ totalSlot }} JP)
                            </Badge>
                            <Badge
                                v-else
                                variant="destructive"
                            >
                                Melebihi Batas ({{ totalAlokasi }} / {{ totalSlot }} JP)
                            </Badge>

                            <div v-if="canManage">
                                <Dialog :open="isCreateOpen" @update:open="(val: boolean) => (isCreateOpen = val)">
                                    <DialogTrigger as-child>
                                        <Button size="sm">+ Tambah Mapel</Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>Alokasi Mapel Baru</DialogTitle>
                                            <DialogDescription>
                                                Pilih mata pelajaran, pengajar, dan alokasi JP untuk {{ selectedRombel.nama }}.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                                            <div class="space-y-2">
                                                <Label for="new-mapel">Mata Pelajaran</Label>
                                                <select
                                                    id="new-mapel"
                                                    v-model="createForm.mata_pelajaran_id"
                                                    class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                                    required
                                                >
                                                    <option value="">Pilih Mata Pelajaran</option>
                                                    <option v-for="m in mapelList" :key="m.id" :value="m.id">
                                                        {{ m.kode }} - {{ m.nama }} ({{ m.bobot_beban_kognitif }})
                                                    </option>
                                                </select>
                                                <span v-if="createForm.errors.mata_pelajaran_id" class="text-xs text-destructive">
                                                    {{ createForm.errors.mata_pelajaran_id }}
                                                </span>
                                            </div>

                                            <div class="space-y-2">
                                                <Label for="new-guru">Guru Pengajar</Label>
                                                <select
                                                    id="new-guru"
                                                    v-model="createForm.guru_id"
                                                    class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                                                >
                                                    <option value="">Belum Ditugaskan</option>
                                                    <option v-for="g in guruList" :key="g.id" :value="g.id">
                                                        {{ g.nama }} {{ g.nip ? `(NIP: ${g.nip})` : '' }}
                                                    </option>
                                                </select>
                                                <span v-if="createForm.errors.guru_id" class="text-xs text-destructive">
                                                    {{ createForm.errors.guru_id }}
                                                </span>
                                            </div>

                                            <div class="space-y-2">
                                                <Label for="new-jp">Jam Pelajaran per Minggu (JP)</Label>
                                                <Input
                                                    id="new-jp"
                                                    type="number"
                                                    min="1"
                                                    max="20"
                                                    v-model.number="createForm.jam_per_minggu"
                                                    required
                                                />
                                                <span v-if="createForm.errors.jam_per_minggu" class="text-xs text-destructive">
                                                    {{ createForm.errors.jam_per_minggu }}
                                                </span>
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
                                                    Alokasikan
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-24">Kode</TableHead>
                                <TableHead>Mata Pelajaran</TableHead>
                                <TableHead class="w-32">Kelompok</TableHead>
                                <TableHead class="w-28 text-center">Beban</TableHead>
                                <TableHead>Guru Pengajar</TableHead>
                                <TableHead class="w-28 text-center">Alokasi (JP)</TableHead>
                                <TableHead v-if="canManage" class="w-28 text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="alokasiList.length === 0">
                                <TableCell :colspan="canManage ? 7 : 6" class="py-8 text-center text-muted-foreground">
                                    Belum ada alokasi mata pelajaran untuk rombel ini.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="a in alokasiList" :key="a.id">
                                <TableCell class="font-mono font-medium">
                                    {{ a.mata_pelajaran.kode }}
                                </TableCell>
                                <TableCell class="font-medium text-foreground">
                                    {{ a.mata_pelajaran.nama }}
                                </TableCell>
                                <TableCell class="text-xs capitalize text-muted-foreground">
                                    {{ a.mata_pelajaran.kelompok.replace('_', ' ') }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge variant="outline" class="capitalize">
                                        {{ a.mata_pelajaran.bobot_beban_kognitif }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ a.guru ? a.guru.nama : '-' }}
                                </TableCell>
                                <TableCell class="text-center font-mono font-semibold">
                                    {{ a.jam_per_minggu }} JP
                                </TableCell>
                                <TableCell v-if="canManage" class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openEdit(a)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10"
                                            @click="deleteAlokasi(a.id)"
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
            <Dialog :open="!!editingAlokasi" @update:open="(val: boolean) => { if (!val) editingAlokasi = null; }">
                <DialogContent v-if="editingAlokasi" class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Alokasi Mapel</DialogTitle>
                        <DialogDescription>
                            {{ editingAlokasi.mata_pelajaran.nama }} ({{ editingAlokasi.mata_pelajaran.kode }})
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                        <div class="space-y-2">
                            <Label for="edit-guru">Guru Pengajar</Label>
                            <select
                                id="edit-guru"
                                v-model="editForm.guru_id"
                                class="focus-visible:outline-xs flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Belum Ditugaskan</option>
                                <option v-for="g in guruList" :key="g.id" :value="g.id">
                                    {{ g.nama }} {{ g.nip ? `(NIP: ${g.nip})` : '' }}
                                </option>
                            </select>
                            <span v-if="editForm.errors.guru_id" class="text-xs text-destructive">
                                {{ editForm.errors.guru_id }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <Label for="edit-jp">Jam Pelajaran per Minggu (JP)</Label>
                            <Input
                                id="edit-jp"
                                type="number"
                                min="1"
                                max="20"
                                v-model.number="editForm.jam_per_minggu"
                                required
                            />
                            <span v-if="editForm.errors.jam_per_minggu" class="text-xs text-destructive">
                                {{ editForm.errors.jam_per_minggu }}
                            </span>
                        </div>

                        <DialogFooter class="pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="editingAlokasi = null"
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
    </div>
</template>
