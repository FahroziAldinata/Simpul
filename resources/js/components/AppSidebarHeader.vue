<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { update as updateSekolahAktif } from '@/routes/sekolah-aktif';
import type { BreadcrumbItem } from '@/types';

interface SekolahItem {
    id: string;
    nama: string;
    npsn: string;
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

        <!-- School switcher for Super Admin -->
        <div v-if="daftarSekolah.length > 0" class="flex items-center gap-2">
            <label
                for="topbar-sekolah-select"
                class="hidden text-xs font-medium text-muted-foreground sm:inline"
            >
                Sekolah Aktif:
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
    </header>
</template>
