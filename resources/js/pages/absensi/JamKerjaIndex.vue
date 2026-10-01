<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { Clock, Plus, Trash2, Edit2 } from '@lucide/vue';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface JamKerjaItem {
    id: string;
    sekolah_id: string;
    kelompok: string;
    hari: number;
    jam_masuk: string | null;
    jam_pulang: string | null;
    toleransi_menit: number;
    is_libur: boolean;
    jumlah_jam_pelajaran: number;
}

defineProps<{
    jamKerja: JamKerjaItem[];
    kelompokList: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Absensi', href: '#' },
    { title: 'Jam Kerja', href: '/absensi/jam-kerja' },
];

const hariNames: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Minggu',
};

const isDialogOpen = ref(false);

const form = useForm({
    kelompok: 'umum',
    hari: 1,
    jam_masuk: '07:00',
    jam_pulang: '15:00',
    toleransi_menit: 15,
    is_libur: false,
    jumlah_jam_pelajaran: 8,
});

function bukaModalTambah(kelompok = 'umum') {
    form.reset();
    form.kelompok = kelompok;
    form.hari = 1;
    form.jam_masuk = '07:00';
    form.jam_pulang = '15:00';
    form.toleransi_menit = 15;
    form.is_libur = false;
    form.jumlah_jam_pelajaran = 8;
    isDialogOpen.value = true;
}

function editItem(item: JamKerjaItem) {
    form.kelompok = item.kelompok;
    form.hari = item.hari;
    form.jam_masuk = item.jam_masuk ? item.jam_masuk.substring(0, 5) : '07:00';
    form.jam_pulang = item.jam_pulang ? item.jam_pulang.substring(0, 5) : '15:00';
    form.toleransi_menit = item.toleransi_menit ?? 15;
    form.is_libur = !!item.is_libur;
    form.jumlah_jam_pelajaran = item.jumlah_jam_pelajaran ?? 8;
    isDialogOpen.value = true;
}

function submitForm() {
    form.post('/absensi/jam-kerja', {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            form.reset();
        },
    });
}

function hapusItem(id: string) {
    if (confirm('Yakin ingin menghapus jadwal jam kerja ini?')) {
        router.delete(`/absensi/jam-kerja/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pengaturan Jam Kerja Pegawai" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Jam Kerja Pegawai</h1>
                    <p class="text-sm text-muted-foreground">
                        Kelola jadwal jam kerja per kelompok pegawai dan batas toleransi keterlambatan.
                    </p>
                </div>
                <Button @click="bukaModalTambah()">
                    <Plus class="mr-2 h-4 w-4" />
                    Atur Jam Kerja
                </Button>
            </div>

            <!-- Card per kelompok -->
            <div v-if="kelompokList.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <Clock class="mx-auto h-10 w-10 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-base font-semibold">Belum Ada Jam Kerja</h3>
                <p class="mt-1 text-sm text-muted-foreground">Tambahkan pengaturan jam kerja untuk kelompok 'umum' atau kelompok spesifik.</p>
                <Button class="mt-4" @click="bukaModalTambah()">Atur Sekarang</Button>
            </div>

            <div v-else class="space-y-6">
                <Card v-for="kelompok in kelompokList" :key="kelompok">
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle class="capitalize">Kelompok: {{ kelompok }}</CardTitle>
                            <CardDescription>Jadwal operasional dan toleransi presensi</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" @click="bukaModalTambah(kelompok)">
                            <Plus class="mr-1 h-3.5 w-3.5" /> Tambah Hari
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b text-muted-foreground">
                                        <th class="pb-2 font-medium">Hari</th>
                                        <th class="pb-2 font-medium">Jam Masuk</th>
                                        <th class="pb-2 font-medium">Jam Pulang</th>
                                        <th class="pb-2 font-medium">Toleransi</th>
                                        <th class="pb-2 font-medium">Status</th>
                                        <th class="pb-2 text-right font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="item in jamKerja.filter(j => j.kelompok === kelompok)" :key="item.id" class="hover:bg-muted/50">
                                        <td class="py-2.5 font-medium">{{ hariNames[item.hari] ?? `Hari ${item.hari}` }}</td>
                                        <td class="py-2.5">{{ item.is_libur ? '-' : (item.jam_masuk?.substring(0, 5) ?? '-') }}</td>
                                        <td class="py-2.5">{{ item.is_libur ? '-' : (item.jam_pulang?.substring(0, 5) ?? '-') }}</td>
                                        <td class="py-2.5">
                                            <span v-if="!item.is_libur">{{ item.toleransi_menit }} menit</span>
                                            <span v-else class="text-muted-foreground">-</span>
                                        </td>
                                        <td class="py-2.5">
                                            <Badge :variant="item.is_libur ? 'destructive' : 'secondary'">
                                                {{ item.is_libur ? 'Libur' : 'Aktif' }}
                                            </Badge>
                                        </td>
                                        <td class="py-2.5 text-right space-x-1">
                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="editItem(item)">
                                                <Edit2 class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="hapusItem(item.id)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Dialog Modal Form -->
            <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Atur Jam Kerja</DialogTitle>
                        <DialogDescription>
                            Tentukan jam masuk, jam pulang, dan toleransi keterlambatan.
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="kelompok">Kelompok Pegawai</Label>
                                <Input id="kelompok" v-model="form.kelompok" placeholder="umum, guru, staf" required />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hari">Hari</Label>
                                <select id="hari" v-model.number="form.hari" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option v-for="(name, num) in hariNames" :key="num" :value="Number(num)">{{ name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input id="is_libur" type="checkbox" v-model="form.is_libur" class="h-4 w-4 rounded border-input" />
                            <Label for="is_libur" class="cursor-pointer">Tandai sebagai hari libur</Label>
                        </div>

                        <div v-if="!form.is_libur" class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="jam_masuk">Jam Masuk (H:i)</Label>
                                <Input id="jam_masuk" type="time" v-model="form.jam_masuk" required />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="jam_pulang">Jam Pulang (H:i)</Label>
                                <Input id="jam_pulang" type="time" v-model="form.jam_pulang" required />
                            </div>
                        </div>

                        <div v-if="!form.is_libur" class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="toleransi_menit">Toleransi (menit)</Label>
                                <Input id="toleransi_menit" type="number" min="0" max="120" v-model.number="form.toleransi_menit" required />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="jumlah_jam_pelajaran">Jumlah JP</Label>
                                <Input id="jumlah_jam_pelajaran" type="number" min="0" max="16" v-model.number="form.jumlah_jam_pelajaran" required />
                            </div>
                        </div>

                        <DialogFooter class="pt-2">
                            <Button type="button" variant="outline" @click="isDialogOpen = false">Batal</Button>
                            <Button type="submit" :disabled="form.processing">Simpan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
