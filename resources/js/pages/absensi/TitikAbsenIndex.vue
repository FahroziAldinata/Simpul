<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { MapPin, Plus, Trash2, Edit2, RefreshCw, QrCode } from '@lucide/vue';
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

interface TitikAbsenItem {
    id: string;
    nama: string;
    latitude: number | null;
    longitude: number | null;
    is_aktif: boolean;
}

defineProps<{
    titikAbsen: TitikAbsenItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Absensi', href: '#' },
    { title: 'Titik Absen', href: '/absensi/titik' },
];

const isDialogOpen = ref(false);
const editingId = ref<string | null>(null);

const form = useForm({
    nama: '',
    latitude: undefined as number | undefined,
    longitude: undefined as number | undefined,
    is_aktif: true,
});

function bukaModalTambah() {
    editingId.value = null;
    form.reset();
    form.latitude = undefined;
    form.longitude = undefined;
    form.is_aktif = true;
    isDialogOpen.value = true;
}

function editItem(item: TitikAbsenItem) {
    editingId.value = item.id;
    form.nama = item.nama;
    form.latitude = item.latitude ?? undefined;
    form.longitude = item.longitude ?? undefined;
    form.is_aktif = !!item.is_aktif;
    isDialogOpen.value = true;
}

function submitForm() {
    if (editingId.value) {
        form.put(`/absensi/titik/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                isDialogOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/absensi/titik', {
            preserveScroll: true,
            onSuccess: () => {
                isDialogOpen.value = false;
                form.reset();
            },
        });
    }
}

function hapusItem(id: string) {
    if (confirm('Yakin ingin menghapus titik absen ini?')) {
        router.delete(`/absensi/titik/${id}`, {
            preserveScroll: true,
        });
    }
}

function rotasiSecret(id: string) {
    if (confirm('Rotasi secret akan membuat semua QR code lama yang tersimpan langsung kedaluwarsa. Lanjutkan?')) {
        router.post(`/absensi/titik/${id}/rotasi-secret`, {}, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Manajemen Titik Absen" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Titik Absen</h1>
                    <p class="text-sm text-muted-foreground">
                        Kelola lokasi pemindaian QR code untuk kehadiran pegawai.
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <Link href="/absensi/qr">
                        <Button variant="outline">
                            <QrCode class="mr-2 h-4 w-4" />
                            Buka Layar QR
                        </Button>
                    </Link>
                    <Button @click="bukaModalTambah()">
                        <Plus class="mr-2 h-4 w-4" />
                        Tambah Titik
                    </Button>
                </div>
            </div>

            <!-- List titik absen -->
            <div v-if="titikAbsen.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <MapPin class="mx-auto h-10 w-10 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-base font-semibold">Belum Ada Titik Absen</h3>
                <p class="mt-1 text-sm text-muted-foreground">Tambahkan minimal 1 titik absen (misal: Gerbang Utama, Lobby Kantor).</p>
                <Button class="mt-4" @click="bukaModalTambah()">Tambah Sekarang</Button>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="titik in titikAbsen" :key="titik.id" class="flex flex-col justify-between">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <CardTitle class="text-lg font-bold">{{ titik.nama }}</CardTitle>
                            <Badge :variant="titik.is_aktif ? 'secondary' : 'outline'">
                                {{ titik.is_aktif ? 'Aktif' : 'Non-aktif' }}
                            </Badge>
                        </div>
                        <CardDescription>
                            <span v-if="titik.latitude && titik.longitude">
                                Koordinat: {{ titik.latitude }}, {{ titik.longitude }}
                            </span>
                            <span v-else class="text-muted-foreground">Koordinat belum diatur (pakai koordinat sekolah)</span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="flex flex-wrap gap-2 pt-2 border-t mt-4">
                            <Link :href="`/absensi/qr/${titik.id}`">
                                <Button size="sm" variant="default">
                                    <QrCode class="mr-1.5 h-3.5 w-3.5" /> Tampilkan QR
                                </Button>
                            </Link>
                            <Button size="sm" variant="outline" @click="rotasiSecret(titik.id)" title="Rotasi Secret">
                                <RefreshCw class="mr-1.5 h-3.5 w-3.5" /> Rotasi
                            </Button>
                            <Button size="sm" variant="ghost" class="h-8 w-8 p-0" @click="editItem(titik)">
                                <Edit2 class="h-3.5 w-3.5" />
                            </Button>
                            <Button size="sm" variant="ghost" class="h-8 w-8 p-0 text-destructive hover:text-destructive" @click="hapusItem(titik.id)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Dialog Modal Form -->
            <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ editingId ? 'Edit Titik Absen' : 'Tambah Titik Absen' }}</DialogTitle>
                        <DialogDescription>
                            Tentukan nama titik (misal Gerbang Utama) dan koordinat opsional.
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="nama">Nama Titik</Label>
                            <Input id="nama" v-model="form.nama" placeholder="Gerbang Utama, Lobby Guru" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="lat">Latitude (Opsional)</Label>
                                <Input id="lat" type="number" step="any" v-model.number="form.latitude" placeholder="-6.200000" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="lng">Longitude (Opsional)</Label>
                                <Input id="lng" type="number" step="any" v-model.number="form.longitude" placeholder="106.816666" />
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input id="is_aktif" type="checkbox" v-model="form.is_aktif" class="h-4 w-4 rounded border-input" />
                            <Label for="is_aktif" class="cursor-pointer">Aktifkan titik absen ini</Label>
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
