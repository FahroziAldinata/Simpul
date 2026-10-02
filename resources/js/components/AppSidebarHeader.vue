<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AntreanOfflinePanel from '@/components/offline/AntreanOfflinePanel.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useOfflineQueue } from '@/offline/useOfflineQueue';
import { update as updateSekolahAktif } from '@/routes/sekolah-aktif';
import type { BreadcrumbItem } from '@/types';

interface SekolahItem {
    id: string;
    nama: string;
    npsn: string;
}

interface SemesterOption {
    id: string;
    label: string;
    is_aktif: boolean;
}

interface PeriodeData {
    selected_semester_id: string | null;
    semester_nama: string | null;
    tahun_ajaran_nama: string | null;
    is_aktif: boolean;
    daftar_semester: SemesterOption[];
}

interface PageAuth {
    sekolah_aktif_id?: string | null;
    daftar_sekolah?: SekolahItem[];
}

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const auth = computed(() => (page.props.auth ?? {}) as PageAuth);
const daftarSekolah = computed(() => auth.value.daftar_sekolah ?? []);
const sekolahAktifId = computed(() => auth.value.sekolah_aktif_id ?? '');

const periode = computed(() => (page.props.periode ?? null) as PeriodeData | null);
const daftarSemester = computed(() => periode.value?.daftar_semester ?? []);
const selectedSemesterId = computed(() => periode.value?.selected_semester_id ?? '');

const { antrean, jumlahPending } = useOfflineQueue();

function onSekolahChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    if (target.value && target.value !== sekolahAktifId.value) {
        router.post(
            updateSekolahAktif.url(),
            { sekolah_id: target.value },
            { preserveScroll: true },
        );
    }
}

function onPeriodeChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    if (target.value && target.value !== selectedSemesterId.value) {
        router.post(
            '/periode-aktif',
            { semester_id: target.value },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-3">
            <!-- School switcher for Super Admin -->
            <div v-if="daftarSekolah.length > 0" class="flex items-center gap-2">
                <label
                    for="topbar-sekolah-select"
                    class="hidden text-xs font-medium text-muted-foreground sm:inline"
                >
                    Sekolah:
                </label>
                <select
                    id="topbar-sekolah-select"
                    :value="sekolahAktifId"
                    class="focus-visible:outline-xs h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                    @change="onSekolahChange"
                >
                    <option
                        v-for="sekolah in daftarSekolah"
                        :key="sekolah.id"
                        :value="sekolah.id"
                    >
                        {{ sekolah.nama }} ({{ sekolah.npsn }})
                    </option>
                </select>
            </div>

            <!-- Periode / Semester switcher -->
            <div v-if="daftarSemester.length > 0" class="flex items-center gap-2">
                <label
                    for="topbar-periode-select"
                    class="hidden text-xs font-medium text-muted-foreground sm:inline"
                >
                    Periode:
                </label>
                <select
                    id="topbar-periode-select"
                    :value="selectedSemesterId"
                    class="focus-visible:outline-xs h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs text-foreground shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring"
                    @change="onPeriodeChange"
                >
                    <option
                        v-for="sem in daftarSemester"
                        :key="sem.id"
                        :value="sem.id"
                    >
                        {{ sem.label }}
                    </option>
                </select>
            </div>

            <!-- Antrean Offline Badge & Panel (T-10.04) -->
            <DropdownMenu v-if="antrean.length > 0">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="focus-visible:outline-xs inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-2.5 py-1 text-xs font-medium text-foreground shadow-xs transition-colors hover:bg-accent focus-visible:ring-1 focus-visible:ring-ring"
                        id="btn-antrean-offline-topbar"
                        :aria-label="`Antrean absensi offline: ${jumlahPending} item`"
                    >
                        <Clock class="h-3.5 w-3.5 text-amber-500 shrink-0" aria-hidden="true" />
                        <span>{{ jumlahPending > 0 ? `${jumlahPending} Antrean` : 'Antrean' }}</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-80 p-0">
                    <AntreanOfflinePanel />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
