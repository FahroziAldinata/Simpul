<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { store as storeHariLibur, destroy as destroyHariLibur } from '@/routes/kalender/hari-libur';
import { update as updateJamKerja } from '@/routes/kalender/jam-kerja';

interface HariLiburItem {
    id: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    keterangan: string;
    jenis: string;
}

interface JamKerjaItem {
    hari: number;
    jam_masuk: string | null;
    jam_pulang: string | null;
    is_libur: boolean;
    jumlah_jam_pelajaran: number;
}

const props = defineProps<{
    hariLibur: HariLiburItem[];
    jamKerja: JamKerjaItem[];
    canManage: boolean;
    totalSlotMingguan: number;
}>();

const dayNames = [
    '',
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
    'Minggu',
];

const jamKerjaForm = useForm({
    hari: props.jamKerja.map((j) => ({
        hari: j.hari,
        jam_masuk: j.jam_masuk ?? '07:00',
        jam_pulang: j.jam_pulang ?? '15:00',
        is_libur: Boolean(j.is_libur),
        jumlah_jam_pelajaran: j.jumlah_jam_pelajaran ?? 8,
    })),
});

const liburForm = useForm({
    tanggal_mulai: '',
    tanggal_selesai: '',
    keterangan: '',
    jenis: 'nasional',
});

function submitJamKerja() {
    jamKerjaForm.post(updateJamKerja.url(), {
        preserveScroll: true,
    });
}

function submitHariLibur() {
    liburForm.post(storeHariLibur.url(), {
        preserveScroll: true,
        onSuccess: () => liburForm.reset(),
    });
}

function deleteHariLibur(id: string) {
    if (confirm('Hapus hari libur ini?')) {
        useForm({}).delete(destroyHariLibur.url(id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Kalender Akademik & Jam Operasional" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-foreground">
                Kalender Akademik & Jam Operasional
            </h1>
            <p class="text-sm text-muted-foreground">
                Pengaturan hari operasional sekolah, beban slot mingguan, dan
                agenda hari libur.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Jam Kerja Operasional -->
            <div class="lg:col-span-2">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Jam Operasional Sekolah</CardTitle>
                                <CardDescription>
                                    Menentukan total slot alokasi mapel per
                                    minggu.
                                </CardDescription>
                            </div>
                            <Badge variant="secondary" class="font-mono">
                                Total Slot: {{ totalSlotMingguan }} JP / Minggu
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitJamKerja" class="space-y-4">
                            <div class="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-24">Hari</TableHead>
                                            <TableHead class="w-24">Status</TableHead>
                                            <TableHead class="w-32">Masuk</TableHead>
                                            <TableHead class="w-32">Pulang</TableHead>
                                            <TableHead class="w-28 text-right">
                                                Slot (JP)
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(item, idx) in jamKerjaForm.hari"
                                            :key="item.hari"
                                        >
                                            <TableCell class="font-medium">
                                                {{ dayNames[item.hari] }}
                                            </TableCell>
                                            <TableCell>
                                                <input
                                                    v-model="item.is_libur"
                                                    type="checkbox"
                                                    :disabled="!canManage"
                                                    class="size-4 rounded border-input"
                                                />
                                                <span class="ml-1 text-xs">
                                                    {{ item.is_libur ? 'Libur' : 'Masuk' }}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.jam_masuk"
                                                    type="time"
                                                    :disabled="!canManage || item.is_libur"
                                                    class="h-8 text-xs"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.jam_pulang"
                                                    type="time"
                                                    :disabled="!canManage || item.is_libur"
                                                    class="h-8 text-xs"
                                                />
                                            </TableCell>
                                            <TableCell class="text-right">
                                                <Input
                                                    v-model.number="item.jumlah_jam_pelajaran"
                                                    type="number"
                                                    min="0"
                                                    max="15"
                                                    :disabled="!canManage || item.is_libur"
                                                    class="h-8 w-20 text-right text-xs"
                                                />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <div v-if="canManage" class="flex justify-end">
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="jamKerjaForm.processing"
                                >
                                    {{
                                        jamKerjaForm.processing
                                            ? 'Menyimpan...'
                                            : 'Simpan Jam Kerja'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Form Tambah Hari Libur -->
            <div>
                <Card>
                    <CardHeader>
                        <CardTitle>Tambah Hari Libur</CardTitle>
                        <CardDescription>
                            Agenda libur nasional atau kegiatan khusus sekolah.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            v-if="canManage"
                            @submit.prevent="submitHariLibur"
                            class="space-y-3"
                        >
                            <div class="space-y-1">
                                <Label for="tgl_mulai">Tanggal Mulai</Label>
                                <Input
                                    id="tgl_mulai"
                                    v-model="liburForm.tanggal_mulai"
                                    type="date"
                                    required
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="tgl_selesai">Tanggal Selesai</Label>
                                <Input
                                    id="tgl_selesai"
                                    v-model="liburForm.tanggal_selesai"
                                    type="date"
                                    required
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="ket">Keterangan</Label>
                                <Input
                                    id="ket"
                                    v-model="liburForm.keterangan"
                                    placeholder="Contoh: Idul Fitri 1447 H"
                                    required
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="jenis">Kategori Libur</Label>
                                <select
                                    id="jenis"
                                    v-model="liburForm.jenis"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-xs focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="nasional">Libur Nasional</option>
                                    <option value="sekolah">Libur Sekolah</option>
                                    <option value="ujian">Ujian</option>
                                    <option value="kegiatan">Kegiatan Sekolah</option>
                                </select>
                            </div>
                            <Button
                                type="submit"
                                class="w-full"
                                size="sm"
                                :disabled="liburForm.processing"
                            >
                                {{
                                    liburForm.processing
                                        ? 'Menambahkan...'
                                        : 'Tambah Libur'
                                }}
                            </Button>
                        </form>
                        <p v-else class="text-sm text-muted-foreground">
                            Hanya Operator dan Super Admin yang dapat menambah
                            hari libur.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Daftar Hari Libur -->
        <Card>
            <CardHeader>
                <CardTitle>Daftar Hari Libur Terjadwal</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-36">Mulai</TableHead>
                                <TableHead class="w-36">Selesai</TableHead>
                                <TableHead class="w-32">Kategori</TableHead>
                                <TableHead>Keterangan</TableHead>
                                <TableHead v-if="canManage" class="w-20 text-right">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="item in hariLibur"
                                :key="item.id"
                            >
                                <TableCell class="font-mono text-xs">
                                    {{ item.tanggal_mulai }}
                                </TableCell>
                                <TableCell class="font-mono text-xs">
                                    {{ item.tanggal_selesai }}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="capitalize">
                                        {{ item.jenis }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ item.keterangan }}</TableCell>
                                <TableCell v-if="canManage" class="text-right">
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        @click="deleteHariLibur(item.id)"
                                    >
                                        Hapus
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="hariLibur.length === 0">
                                <TableCell
                                    :colspan="canManage ? 5 : 4"
                                    class="py-6 text-center text-sm text-muted-foreground"
                                >
                                    Belum ada agenda hari libur.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
