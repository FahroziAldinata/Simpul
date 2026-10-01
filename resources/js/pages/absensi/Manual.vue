<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { UserCheck, AlertTriangle } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface PegawaiSimple {
    id: string;
    nama: string;
    jenis: string;
}

defineProps<{
    pegawaiList: PegawaiSimple[];
    batasHari: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Absensi', href: '#' },
    { title: 'Absen Manual', href: '/absensi/manual' },
];

const today = new Date().toISOString().split('T')[0];

const form = useForm({
    pegawai_id: '',
    tanggal: today,
    jenis: 'masuk',
    alasan_manual: '',
});

function submitForm() {
    form.post('/absensi/manual', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('alasan_manual');
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pencatatan Absensi Manual" />

        <div class="space-y-6 p-6 max-w-2xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Absen Manual Operator</h1>
                <p class="text-sm text-muted-foreground">
                    Catat kehadiran pegawai yang mengalami kendala teknis (kamera/HP rusak).
                </p>
            </div>

            <Alert variant="destructive" class="border-amber-500/50 bg-amber-500/10 text-amber-900 dark:text-amber-200">
                <AlertTriangle class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                <AlertTitle>Perhatian Pengawasan & Audit Log</AlertTitle>
                <AlertDescription class="text-xs">
                    Setiap pencatatan manual wajib menyertakan alasan yang jelas dan akan tercatat permanen di audit log
                    beserta identitas operator pencatat. Pencatatan dibatasi maksimal {{ batasHari }} hari ke belakang.
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle>Formulir Absensi Manual</CardTitle>
                    <CardDescription>Isi detail absensi dan alasan kendala teknis</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="pegawai">Pegawai</Label>
                            <select
                                id="pegawai"
                                v-model="form.pegawai_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="" disabled>-- Pilih Pegawai --</option>
                                <option v-for="p in pegawaiList" :key="p.id" :value="p.id">
                                    {{ p.nama }} ({{ p.jenis }})
                                </option>
                            </select>
                            <p v-if="form.errors.pegawai_id" class="text-xs text-destructive">{{ form.errors.pegawai_id }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="tanggal">Tanggal Presensi</Label>
                                <Input
                                    id="tanggal"
                                    type="date"
                                    v-model="form.tanggal"
                                    :max="today"
                                    required
                                />
                                <p v-if="form.errors.tanggal" class="text-xs text-destructive">{{ form.errors.tanggal }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="jenis">Jenis Presensi</Label>
                                <select
                                    id="jenis"
                                    v-model="form.jenis"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="masuk">Masuk</option>
                                    <option value="pulang">Pulang</option>
                                </select>
                                <p v-if="form.errors.jenis" class="text-xs text-destructive">{{ form.errors.jenis }}</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="alasan">Alasan Pencatatan Manual</Label>
                            <textarea
                                id="alasan"
                                v-model="form.alasan_manual"
                                required
                                rows="3"
                                placeholder="Contoh: Kamera smartphone pegawai bermasalah saat scan di lobby utama."
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">Minimal 5 karakter. Wajib menjelaskan kendala yang terjadi.</p>
                            <p v-if="form.errors.alasan_manual" class="text-xs text-destructive">{{ form.errors.alasan_manual }}</p>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                <UserCheck class="mr-2 h-4 w-4" />
                                Simpan Absensi Manual
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
