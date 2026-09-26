<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

interface SemesterOption {
    id: string;
    label: string;
    is_aktif: boolean;
}

interface PeriodeProps {
    selected_semester_id: string | null;
    semester_nama: string | null;
    tahun_ajaran_nama: string | null;
    is_aktif: boolean;
    daftar_semester: SemesterOption[];
}

const page = usePage();
const periode = computed(() => (page.props.periode ?? null) as PeriodeProps | null);

const isReadOnly = computed(() => {
    return periode.value && periode.value.is_aktif === false;
});

const activeSemester = computed(() => {
    return periode.value?.daftar_semester.find((s) => s.is_aktif) ?? null;
});

function switchToActive() {
    if (activeSemester.value) {
        router.post(
            '/periode-aktif',
            { semester_id: activeSemester.value.id },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <div
        v-if="isReadOnly"
        class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-500/30 bg-amber-500/15 px-6 py-2.5 text-xs text-amber-900 dark:text-amber-200"
    >
        <div class="flex items-center gap-2">
            <span class="inline-block text-base">⚠️</span>
            <span>
                <strong>Mode Arsip (Read-Only):</strong>
                Anda sedang melihat data periode
                <strong>TA {{ periode?.tahun_ajaran_nama }} - Semester {{ periode?.semester_nama }}</strong>
                yang berstatus non-aktif. Operasi penambahan dan pengubahan data dinonaktifkan.
            </span>
        </div>

        <Button
            v-if="activeSemester"
            size="sm"
            variant="outline"
            class="h-7 border-amber-500/40 bg-background/80 text-xs hover:bg-background"
            @click="switchToActive"
        >
            Kembali ke Periode Aktif
        </Button>
    </div>
</template>
