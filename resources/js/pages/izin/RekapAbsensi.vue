<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { FileSpreadsheet, FileText, Clock } from '@lucide/vue'

interface Sel {
    label: string   // H, T, S, I, C, D, A
    warna: string   // Tailwind classes
    menit: number
}

interface BarisRekap {
    pegawai_id: string
    nama: string
    nip: string | null
    jenis: string
    sel: Record<string, Sel | null>
    ringkasan: { H: number; T: number; S: number; I: number; C: number; D: number; A: number; total_menit: number }
}

interface Rekap {
    bulan: number; tahun: number; hari_dalam_bulan: number; baris: BarisRekap[]; durasi_ms: number
}

interface KuotaCuti {
    id: string; pegawai_id: string; kuota_hari: number; terpakai: number
    pegawai: { id: string; nama: string } | null
}

const props = defineProps<{
    rekap: Rekap
    kuotaCuti: KuotaCuti[]
    bulan: number
    tahun: number
    durasiMs: number
}>()

const tanggalList = computed(() => {
    const list = []
    for (let d = 1; d <= props.rekap.hari_dalam_bulan; d++) {
        const tanggal = new Date(props.tahun, props.bulan - 1, d)
        list.push({
            hari: d,
            str: tanggal.toISOString().slice(0, 10),
            dayOfWeek: tanggal.getDay(), // 0=Min, 6=Sab
        })
    }
    return list
}  )

const namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

function navBulan(arah: number) {
    let b = props.bulan + arah
    let t = props.tahun
    if (b < 1) { b = 12; t-- }
    if (b > 12) { b = 1; t++ }
    window.location.href = route('izin.rekap.index') + `?bulan=${b}&tahun=${t}`
}

function eksporExcel() {
    window.location.href = route('izin.ekspor.excel') + `?bulan=${props.bulan}&tahun=${props.tahun}`
}
function eksporPdf() {
    window.location.href = route('izin.ekspor.pdf') + `?bulan=${props.bulan}&tahun=${props.tahun}`
}

const kuotaMap = computed(() => {
    const m: Record<string, KuotaCuti> = {}
    for (const k of props.kuotaCuti) m[k.pegawai_id] = k
    return m
})

// Legend kode status (aturan desain C1: warna + huruf)
const legend = [
    { label: 'H', kelas: 'bg-green-100 text-green-800', nama: 'Hadir' },
    { label: 'T', kelas: 'bg-yellow-100 text-yellow-800', nama: 'Terlambat' },
    { label: 'P', kelas: 'bg-orange-100 text-orange-800', nama: 'Pulang Cepat' },
    { label: 'S', kelas: 'bg-purple-100 text-purple-800', nama: 'Sakit' },
    { label: 'I', kelas: 'bg-blue-100 text-blue-800', nama: 'Izin' },
    { label: 'C', kelas: 'bg-cyan-100 text-cyan-800', nama: 'Cuti' },
    { label: 'D', kelas: 'bg-indigo-100 text-indigo-800', nama: 'Dinas' },
    { label: 'A', kelas: 'bg-red-100 text-red-800', nama: 'Alfa' },
]
</script>

<template>
    <AppLayout>
        <Head :title="`Rekap Absensi — ${namaBulan[bulan]} ${tahun}`" />

        <div class="p-6 space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Rekap Absensi</h1>
                    <p class="text-muted-foreground text-sm flex items-center gap-1">
                        <Clock class="w-3 h-3" />
                        Dihasilkan dalam {{ durasiMs }} ms
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="navBulan(-1)" id="btn-prev-bulan">‹</Button>
                    <span class="font-semibold min-w-36 text-center">{{ namaBulan[bulan] }} {{ tahun }}</span>
                    <Button variant="outline" size="sm" @click="navBulan(1)" id="btn-next-bulan">›</Button>

                    <Button variant="outline" size="sm" @click="eksporExcel" id="btn-ekspor-excel">
                        <FileSpreadsheet class="w-4 h-4 mr-1" /> Excel
                    </Button>
                    <Button variant="outline" size="sm" @click="eksporPdf" id="btn-ekspor-pdf">
                        <FileText class="w-4 h-4 mr-1" /> PDF
                    </Button>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="l in legend" :key="l.label"
                    :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium', l.kelas]"
                    :title="l.nama"
                >
                    {{ l.label }} — {{ l.nama }}
                </span>
            </div>

            <!-- Tabel Matriks -->
            <Card>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="text-xs whitespace-nowrap border-collapse w-full">
                        <thead>
                            <tr class="bg-muted/50">
                                <th class="border px-2 py-1.5 text-left sticky left-0 bg-muted/50 z-10 min-w-48">Pegawai</th>
                                <th
                                    v-for="tgl in tanggalList" :key="tgl.hari"
                                    :class="[
                                        'border px-1 py-1.5 w-8 text-center',
                                        tgl.dayOfWeek === 0 || tgl.dayOfWeek === 6 ? 'bg-slate-100 text-slate-500' : ''
                                    ]"
                                >
                                    {{ tgl.hari }}
                                </th>
                                <th class="border px-2 py-1.5 bg-green-50 text-green-800">H</th>
                                <th class="border px-2 py-1.5 bg-yellow-50 text-yellow-800">T</th>
                                <th class="border px-2 py-1.5 bg-purple-50 text-purple-800">S</th>
                                <th class="border px-2 py-1.5 bg-blue-50 text-blue-800">I</th>
                                <th class="border px-2 py-1.5 bg-cyan-50 text-cyan-800">C</th>
                                <th class="border px-2 py-1.5 bg-indigo-50 text-indigo-800">D</th>
                                <th class="border px-2 py-1.5 bg-red-50 text-red-800">A</th>
                                <th class="border px-2 py-1.5 text-center">Menit</th>
                                <th class="border px-2 py-1.5 text-center">Sisa Cuti</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rekap.baris.length === 0">
                                <td :colspan="rekap.hari_dalam_bulan + 10" class="text-center py-8 text-muted-foreground">
                                    Tidak ada data.
                                </td>
                            </tr>
                            <tr v-for="baris in rekap.baris" :key="baris.pegawai_id" class="hover:bg-muted/20">
                                <td class="border px-2 py-1 sticky left-0 bg-background z-10">
                                    <div class="font-medium">{{ baris.nama }}</div>
                                    <div class="text-muted-foreground font-mono text-[10px]">{{ baris.nip ?? '—' }}</div>
                                </td>
                                <td
                                    v-for="tgl in tanggalList" :key="tgl.str"
                                    class="border p-0 text-center w-8 h-8"
                                    :class="tgl.dayOfWeek === 0 || tgl.dayOfWeek === 6 ? 'bg-slate-50' : ''"
                                >
                                    <span
                                        v-if="baris.sel[tgl.str]"
                                        :class="['inline-flex items-center justify-center w-full h-full text-xs font-bold', baris.sel[tgl.str]!.warna]"
                                        :title="baris.sel[tgl.str]!.menit ? `${baris.sel[tgl.str]!.menit} menit terlambat` : undefined"
                                    >
                                        {{ baris.sel[tgl.str]!.label }}
                                    </span>
                                </td>
                                <!-- Ringkasan -->
                                <td class="border px-2 py-1 text-center font-medium text-green-700">{{ baris.ringkasan.H }}</td>
                                <td class="border px-2 py-1 text-center font-medium text-yellow-700">{{ baris.ringkasan.T }}</td>
                                <td class="border px-2 py-1 text-center font-medium text-purple-700">{{ baris.ringkasan.S }}</td>
                                <td class="border px-2 py-1 text-center font-medium text-blue-700">{{ baris.ringkasan.I }}</td>
                                <td class="border px-2 py-1 text-center font-medium text-cyan-700">{{ baris.ringkasan.C }}</td>
                                <td class="border px-2 py-1 text-center font-medium text-indigo-700">{{ baris.ringkasan.D }}</td>
                                <td class="border px-2 py-1 text-center font-medium text-red-700">{{ baris.ringkasan.A }}</td>
                                <td class="border px-2 py-1 text-center">{{ baris.ringkasan.total_menit }}</td>
                                <td class="border px-2 py-1 text-center">
                                    <span v-if="kuotaMap[baris.pegawai_id]">
                                        {{ kuotaMap[baris.pegawai_id].kuota_hari - kuotaMap[baris.pegawai_id].terpakai }}
                                        <span class="text-muted-foreground">/{{ kuotaMap[baris.pegawai_id].kuota_hari }}</span>
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
