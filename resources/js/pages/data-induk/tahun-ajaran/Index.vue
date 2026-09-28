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

interface SemesterItem {
    id: string;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    is_aktif: boolean;
}

interface TahunAjaranItem {
    id: string;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    is_aktif: boolean;
    semester: SemesterItem[];
}

defineProps<{
    tahunAjaran: TahunAjaranItem[];
    canManage: boolean;
}>();

const isCreateOpen = ref(false);
const editingTahunAjaran = ref<TahunAjaranItem | null>(null);

const createForm = useForm({
    nama: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    is_aktif: false,
});

const editForm = useForm({
    nama: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    is_aktif: false,
});

function submitCreate() {
    createForm.post('/tahun-ajaran', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
}

function openEdit(item: TahunAjaranItem) {
    editingTahunAjaran.value = item;
    editForm.nama = item.nama;
    editForm.tanggal_mulai = item.tanggal_mulai ? item.tanggal_mulai.substring(0, 10) : '';
    editForm.tanggal_selesai = item.tanggal_selesai ? item.tanggal_selesai.substring(0, 10) : '';
    editForm.is_aktif = item.is_aktif;
}

function submitEdit() {
    if (!editingTahunAjaran.value) return;
    editForm.put(`/tahun-ajaran/${editingTahunAjaran.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingTahunAjaran.value = null;
        },
    });
}

function activateSemester(semesterId: string) {
    if (confirm('Aktifkan semester ini sebagai periode operasional saat ini?')) {
        useForm({}).post(`/semester/${semesterId}/aktifkan`, {
            preserveScroll: true,
        });
    }
}

function deleteTahunAjaran(id: string) {
    if (confirm('Hapus tahun ajaran ini beserta semesternya?')) {
        useForm({}).delete(`/tahun-ajaran/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Tahun Ajaran & Semester" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Tahun Ajaran & Semester
                </h1>
                <p class="text-sm text-muted-foreground">
                    Pengelolaan kalender tahun ajaran dan periode aktif semester sekolah.
                </p>
            </div>
            <div v-if="canManage">
                <Dialog :open="isCreateOpen" @update:open="(val: boolean) => (isCreateOpen = val)">
                    <DialogTrigger as-child>
                        <Button>+ Tambah Tahun Ajaran</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Tambah Tahun Ajaran Baru</DialogTitle>
                            <DialogDescription>
                                Semester Ganjil & Genap akan otomatis dibuat dan dibagi rentang tanggalnya.
                            </DialogDescription>
                        </DialogHeader>

                        <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                            <div class="space-y-2">
                                <Label for="new-nama">Nama Tahun Ajaran</Label>
                                <Input
                                    id="new-nama"
                                    v-model="createForm.nama"
                                    placeholder="Contoh: 2026/2027"
                                    required
                                />
                                <span v-if="createForm.errors.nama" class="text-xs text-destructive">
                                    {{ createForm.errors.nama }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="new-mulai">Tanggal Mulai</Label>
                                    <Input
                                        id="new-mulai"
                                        type="date"
                                        v-model="createForm.tanggal_mulai"
                                        required
                                    />
                                    <span v-if="createForm.errors.tanggal_mulai" class="text-xs text-destructive">
                                        {{ createForm.errors.tanggal_mulai }}
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    <Label for="new-selesai">Tanggal Selesai</Label>
                                    <Input
                                        id="new-selesai"
                                        type="date"
                                        v-model="createForm.tanggal_selesai"
                                        required
                                    />
                                    <span v-if="createForm.errors.tanggal_selesai" class="text-xs text-destructive">
                                        {{ createForm.errors.tanggal_selesai }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 pt-2">
                                <Checkbox
                                    id="new-aktif"
                                    :checked="createForm.is_aktif"
                                    @update:checked="(val: boolean) => (createForm.is_aktif = val)"
                                />
                                <Label for="new-aktif" class="text-sm font-normal cursor-pointer">
                                    Langsung aktifkan tahun ajaran ini
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
                                    Simpan & Generate Semester
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- List Tahun Ajaran -->
        <div v-if="tahunAjaran.length === 0" class="rounded-lg border border-dashed p-8 text-center">
            <p class="text-muted-foreground">Belum ada tahun ajaran terdaftar. Silakan tambahkan tahun ajaran baru.</p>
        </div>

        <div v-else class="space-y-4">
            <Card
                v-for="ta in tahunAjaran"
                :key="ta.id"
                :class="[
                    'transition-colors',
                    ta.is_aktif ? 'border-primary shadow-xs' : 'border-border',
                ]"
            >
                <CardHeader>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <CardTitle class="text-xl font-bold">
                                    Tahun Ajaran {{ ta.nama }}
                                </CardTitle>
                                <Badge v-if="ta.is_aktif" class="bg-primary text-primary-foreground">
                                    Aktif
                                </Badge>
                                <Badge v-else variant="outline" class="text-muted-foreground">
                                    Non-Aktif
                                </Badge>
                            </div>
                            <CardDescription>
                                Rentang Waktu: {{ ta.tanggal_mulai }} s/d {{ ta.tanggal_selesai }}
                            </CardDescription>
                        </div>

                        <div v-if="canManage" class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEdit(ta)"
                            >
                                Edit
                            </Button>
                            <Button
                                v-if="!ta.is_aktif"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10"
                                @click="deleteTahunAjaran(ta.id)"
                            >
                                Hapus
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="space-y-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Semester
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="sem in ta.semester"
                                :key="sem.id"
                                :class="[
                                    'p-4 rounded-md border flex items-center justify-between',
                                    sem.is_aktif
                                        ? 'bg-primary/5 border-primary/50'
                                        : 'bg-card border-border',
                                ]"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-foreground">
                                            Semester {{ sem.nama }}
                                        </span>
                                        <Badge
                                            v-if="sem.is_aktif"
                                            variant="secondary"
                                            class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-0"
                                        >
                                            Aktif
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ sem.tanggal_mulai }} s/d {{ sem.tanggal_selesai }}
                                    </p>
                                </div>

                                <div v-if="canManage">
                                    <Button
                                        v-if="!sem.is_aktif"
                                        size="sm"
                                        variant="outline"
                                        @click="activateSemester(sem.id)"
                                    >
                                        Aktifkan
                                    </Button>
                                    <span v-else class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        Sedang Berjalan
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Modal Dialog -->
        <Dialog :open="!!editingTahunAjaran" @update:open="(val: boolean) => { if (!val) editingTahunAjaran = null; }">
            <DialogContent v-if="editingTahunAjaran">
                <DialogHeader>
                    <DialogTitle>Edit Tahun Ajaran</DialogTitle>
                    <DialogDescription>
                        Perbarui detail tahun ajaran {{ editingTahunAjaran.nama }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="space-y-2">
                        <Label for="edit-nama">Nama Tahun Ajaran</Label>
                        <Input
                            id="edit-nama"
                            v-model="editForm.nama"
                            required
                        />
                        <span v-if="editForm.errors.nama" class="text-xs text-destructive">
                            {{ editForm.errors.nama }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-mulai">Tanggal Mulai</Label>
                            <Input
                                id="edit-mulai"
                                type="date"
                                v-model="editForm.tanggal_mulai"
                                required
                            />
                            <span v-if="editForm.errors.tanggal_mulai" class="text-xs text-destructive">
                                {{ editForm.errors.tanggal_mulai }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-selesai">Tanggal Selesai</Label>
                            <Input
                                id="edit-selesai"
                                type="date"
                                v-model="editForm.tanggal_selesai"
                                required
                            />
                            <span v-if="editForm.errors.tanggal_selesai" class="text-xs text-destructive">
                                {{ editForm.errors.tanggal_selesai }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="edit-aktif"
                            :checked="editForm.is_aktif"
                            @update:checked="(val: boolean) => (editForm.is_aktif = val)"
                        />
                        <Label for="edit-aktif" class="text-sm font-normal cursor-pointer">
                            Aktifkan tahun ajaran ini
                        </Label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingTahunAjaran = null"
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
