<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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

import { update as updateProfilSekolah } from '@/routes/profil-sekolah';

interface SekolahData {
    id: string;
    npsn: string;
    nama: string;
    jenjang: string;
    status: string;
    alamat: string | null;
    latitude: number | null;
    longitude: number | null;
    radius_absen_meter: number;
    logo_path: string | null;
    logo_url: string | null;
    kepala_sekolah: string | null;
    akreditasi: string | null;
}

const props = defineProps<{
    sekolah: SekolahData;
    canUpdate: boolean;
}>();

const logoPreview = ref<string | null>(props.sekolah.logo_url);

const form = useForm({
    nama: props.sekolah.nama,
    alamat: props.sekolah.alamat ?? '',
    latitude: props.sekolah.latitude ?? '',
    longitude: props.sekolah.longitude ?? '',
    radius_absen_meter: props.sekolah.radius_absen_meter ?? 150,
    kepala_sekolah: props.sekolah.kepala_sekolah ?? '',
    akreditasi: props.sekolah.akreditasi ?? 'Belum',
    logo: null as File | null,
});

function onLogoSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function submit() {
    form.post(updateProfilSekolah.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Profil Sekolah" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-foreground">
                Profil Sekolah
            </h1>
            <p class="text-sm text-muted-foreground">
                Informasi identitas lembaga, kepala sekolah, dan titik koordinat
                presensi.
            </p>
        </div>

        <form @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Data Pokok Lembaga</CardTitle>
                    <CardDescription>
                        NPSN dan Jenjang terkunci oleh sistem.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="npsn">NPSN</Label>
                            <Input
                                id="npsn"
                                :value="sekolah.npsn"
                                disabled
                                class="bg-muted font-mono"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="jenjang">Jenjang & Status</Label>
                            <Input
                                id="jenjang"
                                :value="`${sekolah.jenjang.toUpperCase()} (${sekolah.status.toUpperCase()})`"
                                disabled
                                class="bg-muted font-semibold"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="nama">Nama Resmi Sekolah</Label>
                        <Input
                            id="nama"
                            v-model="form.nama"
                            :disabled="!canUpdate"
                            required
                        />
                        <p v-if="form.errors.nama" class="text-xs text-red-500">
                            {{ form.errors.nama }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="alamat">Alamat Lengkap</Label>
                        <Input
                            id="alamat"
                            v-model="form.alamat"
                            :disabled="!canUpdate"
                            placeholder="Jl. Raya No. 123..."
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="kepala_sekolah">
                                Nama Kepala Sekolah
                            </Label>
                            <Input
                                id="kepala_sekolah"
                                v-model="form.kepala_sekolah"
                                :disabled="!canUpdate"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="akreditasi">Akreditasi</Label>
                            <select
                                id="akreditasi"
                                v-model="form.akreditasi"
                                :disabled="!canUpdate"
                                class="focus-visible:outline-xs h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="A">A (Unggul)</option>
                                <option value="B">B (Baik)</option>
                                <option value="C">C (Cukup)</option>
                                <option value="Belum">
                                    Belum Terakreditasi
                                </option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Logo Sekolah</CardTitle>
                        <CardDescription>
                            Tersimpan di MinIO S3 terenkripsi.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-20 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/40"
                            >
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt="Logo Sekolah"
                                    class="size-full object-contain"
                                />
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    No Logo
                                </span>
                            </div>
                            <div v-if="canUpdate" class="space-y-1.5">
                                <Label for="logo-file">Pilih Gambar</Label>
                                <Input
                                    id="logo-file"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="text-xs"
                                    @change="onLogoSelect"
                                />
                                <p
                                    v-if="form.errors.logo"
                                    class="text-xs text-red-500"
                                >
                                    {{ form.errors.logo }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Titik Lokasi & Radius Absensi</CardTitle>
                        <CardDescription>
                            Koordinat GPS sekolah untuk validasi presensi
                            mobile.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="lat">Latitude</Label>
                                <Input
                                    id="lat"
                                    v-model="form.latitude"
                                    type="number"
                                    step="any"
                                    :disabled="!canUpdate"
                                    placeholder="-6.2088"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="lng">Longitude</Label>
                                <Input
                                    id="lng"
                                    v-model="form.longitude"
                                    type="number"
                                    step="any"
                                    :disabled="!canUpdate"
                                    placeholder="106.8456"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="radius">Radius Absensi (meter)</Label>
                            <Input
                                id="radius"
                                v-model.number="form.radius_absen_meter"
                                type="number"
                                min="10"
                                max="5000"
                                :disabled="!canUpdate"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="canUpdate" class="mt-6 flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </Button>
            </div>
        </form>
    </div>
</template>
