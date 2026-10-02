<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Calendar,
    Users,
    UserCheck,
    DoorOpen,
    Plus,
    BarChart3,
    AlertCircle,
    Monitor,
} from '@lucide/vue';
import type {
    AlokasiMapelItem,
    GuruItem,
    JadwalItem,
    JamKerjaItem,
    MataPelajaranItem,
    RombelItem,
    RombelRingkasan,
    RuangItem,
    SemesterItem,
    ViewMode,
} from './types';
import ScheduleGrid from './Components/ScheduleGrid.vue';
import JadwalModal from './Components/JadwalModal.vue';
import RingkasanAlokasi from './Components/RingkasanAlokasi.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    semesters: SemesterItem[];
    selectedSemester?: SemesterItem | null;
    rombels: RombelItem[];
    gurus: GuruItem[];
    ruangs: RuangItem[];
    mapels: MataPelajaranItem[];
    jamKerja: JamKerjaItem[];
    alokasiList: AlokasiMapelItem[];
    jadwals: JadwalItem[];
    ringkasan: RombelRingkasan[];
    canManage: boolean;
}>();

// Sudut Pandang Tampilan (T-11.09)
const viewMode = ref<ViewMode>('rombel');
const selectedRombelId = ref<string>('');
const selectedGuruId = ref<string>('');
const selectedRuangId = ref<string>('');

// Panel Ringkasan H4
const showRingkasan = ref(false);

// Modal Tambah/Edit Jadwal
const modalOpen = ref(false);
const editingJadwal = ref<JadwalItem | null>(null);
const modalDefaultHari = ref(1);
const modalDefaultJamMulai = ref(1);

// Error banner jika ada penolakan dari server
const serverErrorMessage = ref<string | null>(null);

// Inisialisasi pilihan default saat data rombel/guru/ruang tersedia
watch(
    () => props.rombels,
    (newRombels) => {
        if (newRombels.length > 0 && !selectedRombelId.value) {
            selectedRombelId.value = newRombels[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => props.gurus,
    (newGurus) => {
        if (newGurus.length > 0 && !selectedGuruId.value) {
            selectedGuruId.value = newGurus[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => props.ruangs,
    (newRuangs) => {
        if (newRuangs.length > 0 && !selectedRuangId.value) {
            selectedRuangId.value = newRuangs[0].id;
        }
    },
    { immediate: true }
);

// Filter jadwal sesuai sudut pandang yang aktif (T-11.09)
const filteredJadwals = computed(() => {
    if (viewMode.value === 'rombel') {
        if (!selectedRombelId.value) return [];
        return props.jadwals.filter((j) => j.rombel_id === selectedRombelId.value);
    }
    if (viewMode.value === 'guru') {
        if (!selectedGuruId.value) return [];
        return props.jadwals.filter((j) => j.guru_id === selectedGuruId.value);
    }
    if (viewMode.value === 'ruang') {
        if (!selectedRuangId.value) return [];
        return props.jadwals.filter((j) => j.ruang_id === selectedRuangId.value);
    }
    return [];
});

function handleSemesterChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    router.get('/jadwal', { semester_id: target.value }, { preserveState: true });
}

function handleAddSlot(hari: number, jamMulai: number) {
    if (!props.canManage) return;
    editingJadwal.value = null;
    modalDefaultHari.value = hari;
    modalDefaultJamMulai.value = jamMulai;
    modalOpen.value = true;
}

function handleEditSlot(jadwal: JadwalItem) {
    if (!props.canManage) return;
    editingJadwal.value = jadwal;
    modalOpen.value = true;
}

function handleDeleteSlot(jadwal: JadwalItem) {
    if (!props.canManage) return;
    const namaMapel = jadwal.mata_pelajaran?.nama ?? 'jadwal ini';
    if (confirm(`Apakah Anda yakin ingin menghapus jadwal "${namaMapel}"?`)) {
        router.delete(`/jadwal/${jadwal.id}`, {
            preserveScroll: true,
            onError: (errors) => {
                serverErrorMessage.value = errors.conflict ?? 'Gagal menghapus jadwal.';
            },
        });
    }
}

function handleMoveSlot(payload: { id: string; hari: number; jam_mulai_ke: number; jam_selesai_ke: number }) {
    serverErrorMessage.value = null;

    router.patch(
        `/jadwal/${payload.id}/move`,
        {
            hari: payload.hari,
            jam_mulai_ke: payload.jam_mulai_ke,
            jam_selesai_ke: payload.jam_selesai_ke,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                serverErrorMessage.value = errors.conflict ?? 'Gagal memindahkan jadwal karena bentrok.';
            },
        }
    );
}

function openAddModal() {
    editingJadwal.value = null;
    modalDefaultHari.value = 1;
    modalDefaultJamMulai.value = 1;
    modalOpen.value = true;
}
</script>

<template>
    <Head title="Jadwal Pelajaran" />

    <div class="space-y-4 p-4 md:p-6 max-w-7xl mx-auto">
        <!-- Callout Petunjuk Layar Desktop (PRD 10.5) -->
        <div class="block lg:hidden rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-200">
            <div class="flex items-center gap-2 font-semibold">
                <Monitor class="h-4 w-4 shrink-0 text-amber-600" />
                <span>Penyusunan Jadwal Lebih Nyaman di Desktop</span>
            </div>
            <p class="mt-1 opacity-90">
                Grid jadwal dan fitur drag & drop dioptimalkan untuk layar lebar (komputer/laptop/tablet).
            </p>
        </div>

        <!-- Banner Error Jika Ada Penolakan Server (Misal bentrok tak terduga) -->
        <div
            v-if="serverErrorMessage"
            class="flex items-start justify-between gap-3 rounded-md border border-rose-500/40 bg-rose-500/10 p-4 text-xs text-rose-900 dark:text-rose-200"
        >
            <div class="flex items-start gap-2">
                <AlertCircle class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                <div>
                    <strong class="font-bold text-sm block">Gagal Menyimpan Jadwal</strong>
                    <span>{{ serverErrorMessage }}</span>
                </div>
            </div>
            <button
                @click="serverErrorMessage = null"
                class="text-rose-700 hover:text-rose-900 dark:text-rose-300 font-semibold"
            >
                Tutup
            </button>
        </div>

        <!-- Header Bar: Judul, Pemilih Semester, Toggle View & Action -->
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between border-b pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <Calendar class="h-6 w-6 text-primary" />
                    <h1 class="text-xl font-bold tracking-tight text-foreground">
                        Jadwal Pelajaran
                    </h1>
                </div>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Penyusunan jadwal kurikulum dengan penegakan kendala anti-bentrok berbasis database.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Pemilih Semester -->
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-muted-foreground font-medium hidden sm:inline">Semester:</span>
                    <select
                        :value="selectedSemester?.id"
                        @change="handleSemesterChange"
                        class="h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs font-semibold shadow-xs focus:ring-1 focus:ring-ring"
                    >
                        <option v-for="s in semesters" :key="s.id" :value="s.id">
                            {{ s.nama }} {{ s.tahun_ajaran ? `(${s.tahun_ajaran.tahun_mulai}/${s.tahun_ajaran.tahun_selesai})` : '' }}
                            {{ s.is_aktif ? '★ Aktif' : '' }}
                        </option>
                    </select>
                </div>

                <!-- Tombol Ringkasan Kurikulum (H4) -->
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 text-xs"
                    :class="[showRingkasan ? 'bg-primary/10 border-primary/50 text-primary' : '']"
                    @click="showRingkasan = !showRingkasan"
                >
                    <BarChart3 class="h-3.5 w-3.5" />
                    <span>Ringkasan Kurikulum (H4)</span>
                </Button>

                <!-- Tombol Tambah Manual -->
                <Button
                    v-if="canManage"
                    size="sm"
                    class="h-8 gap-1.5 text-xs shadow-xs"
                    @click="openAddModal"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>Tambah Jadwal</span>
                </Button>
            </div>
        </div>

        <!-- Panel Ringkasan Kelengkapan Kurikulum (H4) jika dibuka -->
        <RingkasanAlokasi
            v-if="showRingkasan"
            :ringkasan="ringkasan"
            :selected-rombel-id="viewMode === 'rombel' ? selectedRombelId : undefined"
            @select-rombel="selectedRombelId = $event; viewMode = 'rombel'"
        />

        <!-- Controls: Toggle Sudut Pandang (T-11.09) + Pemilih Target -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-muted/30 p-2.5 rounded-lg border">
            <!-- Toggle Buttons (Rombel / Guru / Ruang) -->
            <div class="inline-flex rounded-md border bg-background p-1 shadow-xs text-xs font-medium">
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1 rounded transition-colors"
                    :class="[
                        viewMode === 'rombel'
                            ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                    @click="viewMode = 'rombel'"
                >
                    <Users class="h-3.5 w-3.5" />
                    <span>Per Rombel</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1 rounded transition-colors"
                    :class="[
                        viewMode === 'guru'
                            ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                    @click="viewMode = 'guru'"
                >
                    <UserCheck class="h-3.5 w-3.5" />
                    <span>Per Guru</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1 rounded transition-colors"
                    :class="[
                        viewMode === 'ruang'
                            ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                    @click="viewMode = 'ruang'"
                >
                    <DoorOpen class="h-3.5 w-3.5" />
                    <span>Per Ruang</span>
                </button>
            </div>

            <!-- Target Entity Selector Sesuai Mode Aktif -->
            <div class="flex items-center gap-2">
                <span class="text-xs text-muted-foreground font-medium">
                    {{ viewMode === 'rombel' ? 'Pilih Rombel:' : viewMode === 'guru' ? 'Pilih Guru:' : 'Pilih Ruang:' }}
                </span>

                <!-- Dropdown Rombel -->
                <select
                    v-if="viewMode === 'rombel'"
                    v-model="selectedRombelId"
                    class="h-8 rounded-md border border-input bg-background px-3 py-1 text-xs font-semibold shadow-xs focus:ring-1 focus:ring-ring min-w-[180px]"
                >
                    <option v-for="r in rombels" :key="r.id" :value="r.id">
                        {{ r.nama }} (Tingkat {{ r.tingkat }})
                    </option>
                </select>

                <!-- Dropdown Guru -->
                <select
                    v-if="viewMode === 'guru'"
                    v-model="selectedGuruId"
                    class="h-8 rounded-md border border-input bg-background px-3 py-1 text-xs font-semibold shadow-xs focus:ring-1 focus:ring-ring min-w-[200px]"
                >
                    <option v-for="g in gurus" :key="g.id" :value="g.id">
                        {{ g.nama }}
                    </option>
                </select>

                <!-- Dropdown Ruang -->
                <select
                    v-if="viewMode === 'ruang'"
                    v-model="selectedRuangId"
                    class="h-8 rounded-md border border-input bg-background px-3 py-1 text-xs font-semibold shadow-xs focus:ring-1 focus:ring-ring min-w-[180px]"
                >
                    <option v-for="ru in ruangs" :key="ru.id" :value="ru.id">
                        {{ ru.nama }} ({{ ru.kategori }})
                    </option>
                </select>

                <Badge variant="outline" class="text-[11px] font-mono">
                    {{ filteredJadwals.length }} Slot
                </Badge>
            </div>
        </div>

        <!-- Schedule Grid Komponen Utama -->
        <ScheduleGrid
            :jadwals="filteredJadwals"
            :all-schedules="jadwals"
            :jam-kerja-list="jamKerja"
            :view-mode="viewMode"
            :can-manage="canManage"
            @add-slot="handleAddSlot"
            @move-slot="handleMoveSlot"
            @edit-slot="handleEditSlot"
            @delete-slot="handleDeleteSlot"
        />

        <!-- Modal Tambah/Edit Jadwal -->
        <JadwalModal
            v-if="selectedSemester"
            v-model:open="modalOpen"
            :semester-id="selectedSemester.id"
            :editing-jadwal="editingJadwal"
            :default-hari="modalDefaultHari"
            :default-jam-mulai="modalDefaultJamMulai"
            :default-rombel-id="selectedRombelId"
            :default-guru-id="selectedGuruId"
            :default-ruang-id="selectedRuangId"
            :rombels="rombels"
            :gurus="gurus"
            :ruangs="ruangs"
            :mapels="mapels"
            :jam-kerja-list="jamKerja"
            :all-schedules="jadwals"
        />
    </div>
</template>
