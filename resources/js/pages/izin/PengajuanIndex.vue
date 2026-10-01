<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { FileText, Plus, Clock, CheckCircle2, XCircle, AlertCircle, Ban } from '@lucide/vue'

interface JenisIzin {
    id: string; nama: string; kode: string | null
    butuh_lampiran: boolean; butuh_persetujuan: boolean; mengurangi_kuota_cuti: boolean
}

interface Persetujuan {
    id: string; urutan: number; approver_role: string; status: string; catatan: string | null
}

interface Pengajuan {
    id: string; jenis_izin_id: string; tanggal_mulai: string; tanggal_selesai: string
    alasan: string; status: string; lampiran_path: string | null
    jenis_izin: JenisIzin | null; persetujuan: Persetujuan[]
}

const props = defineProps<{
    pengajuan: Pengajuan[]
    jenisIzin: JenisIzin[]
    pegawai: { id: string; nama: string } | null
}>()

const sheetOpen = ref(false)
const selectedJenis = ref<JenisIzin | null>(null)

const form = useForm({
    jenis_izin_id: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    lampiran: null as File | null,
})

function pilihJenis(id: unknown) {
    if (typeof id !== 'string') return
    selectedJenis.value = props.jenisIzin.find(j => j.id === id) ?? null
    form.jenis_izin_id = id
}

function handleFile(e: Event) {
    const input = e.target as HTMLInputElement
    form.lampiran = input.files?.[0] ?? null
}

function kirim() {
    form.post(route('izin.pengajuan.store'), {
        forceFormData: true,
        onSuccess: () => { sheetOpen.value = false; form.reset() },
    })
}

const statusBadge = (status: string) => ({
    menunggu: { variant: 'secondary' as const, label: 'Menunggu', icon: Clock },
    disetujui: { variant: 'default' as const, label: 'Disetujui', icon: CheckCircle2 },
    ditolak: { variant: 'destructive' as const, label: 'Ditolak', icon: XCircle },
    dibatalkan: { variant: 'outline' as const, label: 'Dibatalkan', icon: Ban },
    draft: { variant: 'secondary' as const, label: 'Draft', icon: AlertCircle },
}[status] ?? { variant: 'secondary' as const, label: status, icon: AlertCircle })

function formatTanggal(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
    <AppLayout>
        <Head title="Pengajuan Izin" />

        <div class="p-6 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Pengajuan Izin</h1>
                    <p class="text-muted-foreground text-sm">Halo, {{ pegawai?.nama ?? 'Pegawai' }}</p>
                </div>
                <Sheet v-model:open="sheetOpen">
                    <SheetTrigger as-child>
                        <Button id="btn-ajukan-izin">
                            <Plus class="w-4 h-4 mr-2" />
                            Ajukan Izin
                        </Button>
                    </SheetTrigger>
                    <SheetContent class="w-[460px]">
                        <SheetHeader>
                            <SheetTitle>Form Pengajuan Izin</SheetTitle>
                        </SheetHeader>

                        <form @submit.prevent="kirim" class="space-y-4 mt-4">
                            <!-- Jenis Izin -->
                            <div class="space-y-1">
                                <Label>Jenis Izin</Label>
                                <Select @update:modelValue="pilihJenis" required>
                                    <SelectTrigger id="sel-jenis-izin">
                                        <SelectValue placeholder="Pilih jenis izin..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="j in jenisIzin" :key="j.id" :value="j.id">
                                            {{ j.nama }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.jenis_izin_id" class="text-destructive text-xs">{{ form.errors.jenis_izin_id }}</p>
                            </div>

                            <!-- Info kuota jika cuti -->
                            <div v-if="selectedJenis?.mengurangi_kuota_cuti" class="bg-amber-50 border border-amber-200 rounded p-3 text-xs text-amber-700 flex gap-2 items-center">
                                <AlertCircle class="w-4 h-4 shrink-0" />
                                Pengajuan ini akan mengurangi kuota cuti tahunan Anda.
                            </div>

                            <!-- Rentang Tanggal -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <Label for="tgl-mulai">Tanggal Mulai</Label>
                                    <Input id="tgl-mulai" type="date" v-model="form.tanggal_mulai" required />
                                    <p v-if="form.errors.tanggal_mulai" class="text-destructive text-xs">{{ form.errors.tanggal_mulai }}</p>
                                </div>
                                <div class="space-y-1">
                                    <Label for="tgl-selesai">Tanggal Selesai</Label>
                                    <Input id="tgl-selesai" type="date" v-model="form.tanggal_selesai" :min="form.tanggal_mulai" required />
                                    <p v-if="form.errors.tanggal_selesai" class="text-destructive text-xs">{{ form.errors.tanggal_selesai }}</p>
                                </div>
                            </div>

                            <!-- Alasan -->
                            <div class="space-y-1">
                                <Label for="alasan-izin">Alasan</Label>
                                <Textarea id="alasan-izin" v-model="form.alasan" rows="3" placeholder="Jelaskan alasan pengajuan..." required />
                                <p v-if="form.errors.alasan" class="text-destructive text-xs">{{ form.errors.alasan }}</p>
                            </div>

                            <!-- Lampiran (hanya jika wajib atau opsional) -->
                            <div v-if="selectedJenis?.butuh_lampiran" class="space-y-1">
                                <Label for="lampiran-file">
                                    Lampiran
                                    <span class="text-destructive">*</span>
                                    <span class="text-xs text-muted-foreground ml-1">(PDF/JPG/PNG, maks 2MB)</span>
                                </Label>
                                <Input id="lampiran-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleFile" />
                                <p v-if="form.errors.lampiran" class="text-destructive text-xs">{{ form.errors.lampiran }}</p>
                            </div>

                            <div class="flex gap-2 pt-2">
                                <Button type="submit" :disabled="form.processing" id="btn-kirim-izin" class="flex-1">
                                    {{ form.processing ? 'Mengirim...' : 'Kirim Pengajuan' }}
                                </Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>
            </div>

            <!-- Daftar Pengajuan -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Riwayat Pengajuan</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Jenis</TableHead>
                                <TableHead>Periode</TableHead>
                                <TableHead>Alasan</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                                <TableHead>Progres Approval</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="pengajuan.length === 0">
                                <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                                    Belum ada pengajuan.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="p in pengajuan" :key="p.id">
                                <TableCell class="font-medium">{{ p.jenis_izin?.nama ?? '—' }}</TableCell>
                                <TableCell class="text-xs">
                                    {{ formatTanggal(p.tanggal_mulai) }}<br>
                                    <span class="text-muted-foreground">s/d {{ formatTanggal(p.tanggal_selesai) }}</span>
                                </TableCell>
                                <TableCell class="text-sm max-w-48 truncate">{{ p.alasan }}</TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="statusBadge(p.status).variant">
                                        {{ statusBadge(p.status).label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1">
                                        <div
                                            v-for="langkah in p.persetujuan"
                                            :key="langkah.id"
                                            :title="langkah.approver_role + ': ' + langkah.status"
                                            :class="[
                                                'w-3 h-3 rounded-full border',
                                                langkah.status === 'disetujui' ? 'bg-green-500 border-green-500' :
                                                langkah.status === 'ditolak' ? 'bg-red-500 border-red-500' :
                                                'bg-muted border-muted-foreground/30'
                                            ]"
                                        />
                                        <span v-if="!p.persetujuan.length" class="text-xs text-muted-foreground">Auto</span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
