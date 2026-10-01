<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { QrCode, ArrowRight } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

interface TitikAktifItem {
    id: string;
    nama: string;
}

defineProps<{
    titikAktif: TitikAktifItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Absensi', href: '#' },
    { title: 'Pilih Titik QR', href: '/absensi/qr' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pilih Layar QR Absensi" />

        <div class="space-y-6 p-6 max-w-4xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Pilih Titik Absen</h1>
                <p class="text-sm text-muted-foreground">
                    Pilih lokasi titik absen yang ingin ditampilkan pada layar kantor ini.
                </p>
            </div>

            <div v-if="titikAktif.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <QrCode class="mx-auto h-10 w-10 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-base font-semibold">Tidak Ada Titik Absen Aktif</h3>
                <p class="mt-1 text-sm text-muted-foreground">Silakan aktifkan atau buat titik absen terlebih dahulu di menu pengaturan titik absen.</p>
                <Link href="/absensi/titik" class="mt-4 inline-block">
                    <Button>Kelola Titik Absen</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <Card v-for="titik in titikAktif" :key="titik.id" class="hover:border-primary/50 transition-colors">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center justify-between text-lg">
                            <span>{{ titik.nama }}</span>
                            <QrCode class="h-5 w-5 text-muted-foreground" />
                        </CardTitle>
                        <CardDescription>Buka layar display QR dinamis untuk titik ini</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <Link :href="`/absensi/qr/${titik.id}`">
                            <Button class="w-full">
                                Buka Tampilan QR
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
