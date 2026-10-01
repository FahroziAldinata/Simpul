<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Camera, CheckCircle, AlertCircle, Loader2, MapPin } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface PegawaiProps {
    id: string;
    nama: string;
}

interface QrScannerInstance {
    render: (onSuccess: (text: string) => void, onError: (err: string) => void) => void;
    clear: () => Promise<void>;
}

defineProps<{
    pegawai: PegawaiProps | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Absensi', href: '#' },
            { title: 'Scan QR', href: '#' },
        ],
    },
});

// ─── State ───────────────────────────────────────────────────────────────────
type ScanState = 'idle' | 'scanning' | 'submitting' | 'success' | 'error';

const state         = ref<ScanState>('idle');
const errorMsg      = ref<string | null>(null);
const successMsg    = ref<string | null>(null);
const scannerEl     = ref<HTMLDivElement | null>(null);
const gpsPosition   = ref<GeolocationPosition | null>(null);
const gpsError      = ref<string | null>(null);
let html5QrScanner: QrScannerInstance | null = null;

// ─── GPS Permission ───────────────────────────────────────────────────────────
function mintaIzinGps(): void {
    if (!navigator.geolocation) {
        gpsError.value = 'Perangkat tidak mendukung GPS.';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        (pos) => { gpsPosition.value = pos; gpsError.value = null; },
        () => { gpsError.value = 'Izin GPS ditolak. Lokasi tidak akan dicatat.'; },
        { enableHighAccuracy: true, timeout: 10000 },
    );
}

// ─── QR Scanner ───────────────────────────────────────────────────────────────
async function mulaiScan(): Promise<void> {
    state.value = 'scanning';
    errorMsg.value = null;

    try {
        // Dynamic import html5-qrcode (sudah ada di PRD 9.1 / package.json)
        const { Html5QrcodeScanner } = await import('html5-qrcode');

        html5QrScanner = new Html5QrcodeScanner(
            'qr-reader',
            { fps: 10, qrbox: { width: 250, height: 250 } },
            false,
        );

        html5QrScanner.render(
            (decodedText: string) => kirimAbsensi(decodedText),
            (error: string) => {
                // Scan error biasa (tidak menemukan QR di frame) — abaikan
                if (!error.includes('No MultiFormat Readers')) {
                    console.debug('QR scan error:', error);
                }
            },
        );
    } catch {
        state.value = 'error';
        errorMsg.value = 'Gagal memuat scanner. Pastikan library html5-qrcode terinstall.';
    }
}

function hentikanScan(): void {
    if (html5QrScanner) {
        html5QrScanner.clear().catch(() => {});
        html5QrScanner = null;
    }
    state.value = 'idle';
}

// ─── Submit absensi ke server (Minggu 8: fetch biasa, bukan antrean offline) ──
async function kirimAbsensi(payloadQr: string): Promise<void> {
    // Stop scanner segera agar tidak scan berulang
    if (html5QrScanner) {
        await html5QrScanner.clear().catch(() => {});
        html5QrScanner = null;
    }

    state.value = 'submitting';

    const body = {
        payload_qr:      payloadQr,
        jenis:           'masuk', // Default masuk — TODO: tambahkan pilihan masuk/pulang di UI
        latitude:        gpsPosition.value?.coords.latitude ?? null,
        longitude:       gpsPosition.value?.coords.longitude ?? null,
        waktu_perangkat: new Date().toISOString(),
        // client_uuid: disiapkan Minggu 8, dipakai aktif Minggu 10 (offline sync)
        client_uuid: null,
    };

    try {
        const res = await fetch('/absensi/scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                // CSRF token dari meta tag
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        });

        const data = await res.json();

        if (res.ok) {
            state.value = 'success';
            let msg = 'Absensi berhasil dicatat.';
            if (data.status === 'terlambat') {
                msg += ` Terlambat ${data.menit_terlambat} menit.`;
            }
            if (data.lokasi_mencurigakan) {
                msg += ' ⚠️ Lokasi di luar area sekolah — akan ditinjau admin.';
            }
            successMsg.value = msg;
        } else {
            state.value = 'error';
            // Pesan dari server (validasi error, QR kedaluwarsa, sudah absen, dll)
            errorMsg.value = data.message ?? data.errors?.payload_qr?.[0] ?? 'Gagal mencatat absensi.';
        }
    } catch {
        state.value = 'error';
        // PENTING: Minggu 8 tidak ada antrean offline — tampilkan error bukan "menunggu sinkron"
        errorMsg.value = 'Tidak bisa terhubung ke server. Hubungi Operator untuk absen manual.';
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function reset(): void {
    state.value = 'idle';
    errorMsg.value = null;
    successMsg.value = null;
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    mintaIzinGps();
});

onBeforeUnmount(() => {
    if (html5QrScanner) {
        html5QrScanner.clear().catch(() => {});
    }
});
</script>

<template>
    <Head title="Scan Absensi QR" />

    <div class="flex min-h-[calc(100vh-4rem)] flex-col items-center justify-center bg-slate-950 p-4">
        <!-- Header -->
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-white">Absensi Kehadiran</h1>
            <p class="mt-1 text-sm text-slate-400">
                <span v-if="pegawai">Halo, {{ pegawai.nama }}</span>
                <span v-else>Scan QR di layar kantor</span>
            </p>
        </div>

        <!-- GPS status -->
        <div v-if="gpsError" class="mb-4 flex items-center gap-2 rounded-lg bg-amber-950/50 px-4 py-2 text-sm text-amber-300">
            <MapPin class="h-4 w-4" />
            {{ gpsError }}
        </div>

        <!-- Card utama -->
        <Card class="w-full max-w-sm border-slate-800 bg-slate-900">
            <CardHeader class="text-center">
                <CardTitle class="text-slate-200">Scan QR Absensi</CardTitle>
                <CardDescription class="text-slate-500">
                    Arahkan kamera ke QR di layar kantor
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col items-center gap-4 pb-6">
                <!-- IDLE state -->
                <template v-if="state === 'idle'">
                    <div class="flex h-64 w-full items-center justify-center rounded-xl border-2 border-dashed border-slate-700">
                        <div class="text-center">
                            <Camera class="mx-auto h-12 w-12 text-slate-600" />
                            <p class="mt-2 text-sm text-slate-500">Kamera belum aktif</p>
                        </div>
                    </div>
                    <Button class="w-full" @click="mulaiScan()">
                        <Camera class="mr-2 h-4 w-4" />
                        Mulai Scan
                    </Button>
                </template>

                <!-- SCANNING state -->
                <template v-if="state === 'scanning'">
                    <div id="qr-reader" ref="scannerEl" class="w-full overflow-hidden rounded-xl" />
                    <Button variant="outline" class="w-full border-slate-700 text-slate-300" @click="hentikanScan()">
                        Batalkan
                    </Button>
                </template>

                <!-- SUBMITTING state -->
                <template v-if="state === 'submitting'">
                    <div class="flex h-64 w-full items-center justify-center">
                        <div class="text-center">
                            <Loader2 class="mx-auto h-12 w-12 animate-spin text-blue-400" />
                            <p class="mt-3 text-slate-400">Mencatat kehadiran…</p>
                        </div>
                    </div>
                </template>

                <!-- SUCCESS state -->
                <template v-if="state === 'success'">
                    <div class="flex h-64 w-full flex-col items-center justify-center gap-4">
                        <CheckCircle class="h-16 w-16 text-emerald-400" />
                        <p class="text-center text-slate-200">{{ successMsg }}</p>
                    </div>
                    <Button class="w-full" @click="reset()">
                        Selesai
                    </Button>
                </template>

                <!-- ERROR state -->
                <template v-if="state === 'error'">
                    <div class="flex h-64 w-full flex-col items-center justify-center gap-4">
                        <AlertCircle class="h-16 w-16 text-red-400" />
                        <p class="text-center text-sm text-slate-300">{{ errorMsg }}</p>
                    </div>
                    <div class="flex w-full gap-2">
                        <Button variant="outline" class="flex-1 border-slate-700" @click="reset()">
                            Coba Lagi
                        </Button>
                    </div>
                </template>
            </CardContent>
        </Card>

        <!-- Catatan: tidak ada offline queue di Minggu 8 -->
        <p class="mt-4 text-center text-xs text-slate-700">
            Tidak ada koneksi? Hubungi Operator untuk absen manual.
        </p>
    </div>
</template>
