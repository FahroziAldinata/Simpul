<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Clock, RefreshCw, Wifi } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// Props dari TampilanQrController::tampil()
const props = defineProps<{
    titikAbsen: { id: string; nama: string };
    payload: string;
    sisaDetik: number;
    windowSeconds: number;
    tokenUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Absensi', href: '/absensi/titik' },
            { title: 'Tampilan QR', href: '#' },
        ],
    },
});

// ─── State ───────────────────────────────────────────────────────────────────
const currentPayload  = ref(props.payload);
const sisaDetik       = ref(props.sisaDetik);
const isRefreshing    = ref(false);
const lastRefresh     = ref<Date>(new Date());
const errorMsg        = ref<string | null>(null);

// QR canvas reference untuk menggambar QR code
const qrCanvas = ref<HTMLCanvasElement | null>(null);

// ─── Client-side timer (Keputusan #1: tanpa Reverb) ──────────────────────────
// JS hitung sendiri kapan window berganti, fetch token baru tepat saat berganti.
let timerInterval: ReturnType<typeof setInterval> | null = null;
let refreshTimeout: ReturnType<typeof setTimeout> | null = null;

function mulaiTimer(): void {
    // Update countdown tiap detik
    timerInterval = setInterval(() => {
        sisaDetik.value -= 1;
        if (sisaDetik.value <= 0) {
            // Nol — window baru dimulai, fetch token baru
            sisaDetik.value = props.windowSeconds;
            fetchTokenBaru();
        }
    }, 1000);
}

async function fetchTokenBaru(): Promise<void> {
    if (isRefreshing.value) return;
    isRefreshing.value = true;
    errorMsg.value = null;

    try {
        const res = await fetch(props.tokenUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data: { payload: string; sisa_detik: number; window_seconds: number } = await res.json();
        currentPayload.value = data.payload;
        sisaDetik.value = data.sisa_detik;
        lastRefresh.value = new Date();

        // Render ulang QR
        await renderQr(data.payload);
    } catch {
        errorMsg.value = 'Gagal memuat QR terbaru. Coba refresh halaman.';
    } finally {
        isRefreshing.value = false;
    }
}

// ─── QR Rendering menggunakan Canvas + qrcode library ────────────────────────
async function renderQr(payload: string): Promise<void> {
    if (!qrCanvas.value) return;

    try {
        const QRCode = (await import('qrcode')).default;
        await QRCode.toCanvas(qrCanvas.value, payload, {
            width: 320,
            margin: 2,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch {
        errorMsg.value = 'Library QR code belum tersedia.';
    }
}

// ─── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(async () => {
    await renderQr(props.payload);
    mulaiTimer();
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (refreshTimeout) clearTimeout(refreshTimeout);
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
const countdownPersen = () => Math.round((sisaDetik.value / props.windowSeconds) * 100);
const waktuRefresh = () =>
    lastRefresh.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
</script>

<template>
    <Head :title="`QR Absensi — ${titikAbsen.nama}`" />

    <div class="flex min-h-[calc(100vh-4rem)] flex-col items-center justify-center bg-slate-950 p-6">
        <!-- Header -->
        <div class="mb-8 text-center">
            <p class="text-sm font-medium tracking-widest text-slate-400 uppercase">Titik Absen</p>
            <h1 class="mt-1 text-3xl font-bold text-white">{{ titikAbsen.nama }}</h1>
            <p class="mt-2 text-slate-500">Scan QR ini untuk mencatat kehadiran</p>
        </div>

        <!-- QR Card -->
        <Card class="w-full max-w-sm border-slate-800 bg-slate-900 shadow-2xl">
            <CardHeader class="pb-2 text-center">
                <CardTitle class="text-slate-300">
                    <span class="flex items-center justify-center gap-2 text-sm font-medium">
                        <Clock class="h-4 w-4" />
                        Berlaku {{ sisaDetik }} detik lagi
                    </span>
                </CardTitle>

                <!-- Progress bar countdown -->
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-800">
                    <div
                        class="h-full rounded-full transition-all duration-1000"
                        :class="sisaDetik > 10 ? 'bg-emerald-500' : 'bg-amber-500'"
                        :style="{ width: `${countdownPersen()}%` }"
                    />
                </div>
            </CardHeader>

            <CardContent class="flex flex-col items-center pb-6">
                <!-- Error state -->
                <div v-if="errorMsg" class="mb-4 w-full rounded-lg border border-red-800 bg-red-950 p-3">
                    <div class="flex items-center gap-2 text-sm text-red-300">
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        {{ errorMsg }}
                    </div>
                </div>

                <!-- QR Canvas -->
                <div
                    class="relative rounded-xl bg-white p-4 shadow-lg"
                    :class="{ 'opacity-50': isRefreshing }"
                >
                    <canvas ref="qrCanvas" class="block" />
                    <div
                        v-if="isRefreshing"
                        class="absolute inset-0 flex items-center justify-center rounded-xl bg-white/80"
                    >
                        <RefreshCw class="h-8 w-8 animate-spin text-slate-600" />
                    </div>
                </div>

                <!-- Status badges -->
                <div class="mt-4 flex gap-2">
                    <Badge variant="outline" class="border-slate-700 text-slate-400 text-xs">
                        <Wifi class="mr-1 h-3 w-3" />
                        Diperbarui: {{ waktuRefresh() }}
                    </Badge>
                </div>

                <!-- Peringatan keamanan -->
                <p class="mt-4 text-center text-xs text-slate-600">
                    QR berrotasi setiap {{ windowSeconds }} detik. Screenshot QR lama tidak bisa dipakai.
                </p>
            </CardContent>
        </Card>

        <!-- Tombol kembali ke pilih titik -->
        <div class="mt-6 flex gap-3">
            <Button
                variant="ghost"
                class="text-slate-500 hover:text-slate-300"
                @click="router.get('/absensi/qr')"
            >
                ← Ganti Titik Absen
            </Button>
            <Button
                variant="ghost"
                class="text-slate-500 hover:text-slate-300"
                :disabled="isRefreshing"
                @click="fetchTokenBaru()"
            >
                <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                Refresh Manual
            </Button>
        </div>
    </div>
</template>
