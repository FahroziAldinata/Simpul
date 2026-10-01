<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { AlertCircle, CheckCircle, Plus, Pencil, Trash2, Shield } from '@lucide/vue'

interface JenisIzin {
    id: string
    sekolah_id: string | null
    nama: string
    kode: string | null
    butuh_lampiran: boolean
    butuh_persetujuan: boolean
    mengurangi_kuota_cuti: boolean
    urutan_approval: string[]
    is_aktif: boolean
}

const props = defineProps<{ jenisIzin: JenisIzin[] }>()

const dialogOpen = ref(false)
const editTarget = ref<JenisIzin | null>(null)

const form = useForm({
    nama: '',
    kode: '',
    butuh_lampiran: false,
    butuh_persetujuan: true,
    mengurangi_kuota_cuti: false,
    urutan_approval: ['kepsek'] as string[],
    is_aktif: true,
})

function bukaTambah() {
    editTarget.value = null
    form.reset()
    form.urutan_approval = ['kepsek']
    dialogOpen.value = true
}

function bukaEdit(item: JenisIzin) {
    if (!item.sekolah_id) return // Global tidak bisa diedit
    editTarget.value = item
    form.nama = item.nama
    form.kode = item.kode ?? ''
    form.butuh_lampiran = item.butuh_lampiran
    form.butuh_persetujuan = item.butuh_persetujuan
    form.mengurangi_kuota_cuti = item.mengurangi_kuota_cuti
    form.urutan_approval = [...item.urutan_approval]
    form.is_aktif = item.is_aktif
    dialogOpen.value = true
}

function simpan() {
    if (editTarget.value) {
        form.put(route('izin.jenis.update', editTarget.value.id), {
            onSuccess: () => { dialogOpen.value = false },
        })
    } else {
        form.post(route('izin.jenis.store'), {
            onSuccess: () => { dialogOpen.value = false; form.reset() },
        })
    }
}

function hapus(id: string) {
    if (!confirm('Hapus jenis izin ini?')) return
    router.delete(route('izin.jenis.destroy', id))
}

const approvalOptions = [
    { value: 'kepsek', label: 'Kepala Sekolah' },
    { value: 'super_admin', label: 'Super Admin' },
    { value: 'waka_kurikulum', label: 'Waka Kurikulum' },
]

function toggleApproval(role: string) {
    const idx = form.urutan_approval.indexOf(role)
    if (idx >= 0) {
        form.urutan_approval.splice(idx, 1)
    } else {
        form.urutan_approval.push(role)
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Jenis Izin" />

        <div class="p-6 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Jenis Izin</h1>
                    <p class="text-muted-foreground text-sm">Konfigurasi jenis izin/cuti/sakit/dinas</p>
                </div>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button id="btn-tambah-jenis-izin" @click="bukaTambah">
                            <Plus class="w-4 h-4 mr-2" />
                            Tambah Jenis
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{{ editTarget ? 'Edit' : 'Tambah' }} Jenis Izin</DialogTitle>
                        </DialogHeader>
                        <form @submit.prevent="simpan" class="space-y-4 mt-2">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <Label for="nama-jenis">Nama</Label>
                                    <Input id="nama-jenis" v-model="form.nama" placeholder="Cuti Tahunan" required />
                                    <p v-if="form.errors.nama" class="text-destructive text-xs">{{ form.errors.nama }}</p>
                                </div>
                                <div class="space-y-1">
                                    <Label for="kode-jenis">Kode</Label>
                                    <Input id="kode-jenis" v-model="form.kode" placeholder="cuti" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Wajib Lampiran</Label>
                                    <Switch id="sw-lampiran" v-model="form.butuh_lampiran" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <Label>Butuh Persetujuan</Label>
                                    <Switch id="sw-persetujuan" v-model="form.butuh_persetujuan" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <Label>Kurangi Kuota Cuti</Label>
                                    <Switch id="sw-kuota" v-model="form.mengurangi_kuota_cuti" />
                                </div>
                            </div>

                            <div v-if="form.butuh_persetujuan" class="space-y-2">
                                <Label>Urutan Persetujuan</Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="opt in approvalOptions"
                                        :key="opt.value"
                                        type="button"
                                        @click="toggleApproval(opt.value)"
                                        :class="[
                                            'px-3 py-1 rounded-full text-xs border transition-colors',
                                            form.urutan_approval.includes(opt.value)
                                                ? 'bg-primary text-primary-foreground border-primary'
                                                : 'bg-background border-border text-muted-foreground'
                                        ]"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <p class="text-xs text-muted-foreground">Urutan: {{ form.urutan_approval.join(' → ') || 'Belum dipilih' }}</p>
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="dialogOpen = false">Batal</Button>
                                <Button type="submit" :disabled="form.processing" id="btn-simpan-jenis">Simpan</Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Kode</TableHead>
                                <TableHead class="text-center">Lampiran</TableHead>
                                <TableHead class="text-center">Persetujuan</TableHead>
                                <TableHead class="text-center">Kurangi Kuota</TableHead>
                                <TableHead>Alur Approval</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in jenisIzin" :key="item.id">
                                <TableCell class="font-medium">
                                    {{ item.nama }}
                                    <span v-if="!item.sekolah_id" class="ml-1">
                                        <Shield class="w-3 h-3 inline text-muted-foreground" title="Default global" />
                                    </span>
                                </TableCell>
                                <TableCell class="font-mono text-xs">{{ item.kode ?? '—' }}</TableCell>
                                <TableCell class="text-center">
                                    <CheckCircle v-if="item.butuh_lampiran" class="w-4 h-4 text-green-500 mx-auto" />
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <CheckCircle v-if="item.butuh_persetujuan" class="w-4 h-4 text-blue-500 mx-auto" />
                                    <span v-else class="text-muted-foreground">Auto</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <CheckCircle v-if="item.mengurangi_kuota_cuti" class="w-4 h-4 text-orange-500 mx-auto" />
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ item.urutan_approval.length ? item.urutan_approval.join(' → ') : '—' }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="item.is_aktif ? 'default' : 'secondary'">
                                        {{ item.is_aktif ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div v-if="item.sekolah_id" class="flex gap-1 justify-end">
                                        <Button size="icon" variant="ghost" @click="bukaEdit(item)" :id="`btn-edit-${item.id}`">
                                            <Pencil class="w-4 h-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" class="text-destructive" @click="hapus(item.id)" :id="`btn-hapus-${item.id}`">
                                            <Trash2 class="w-4 h-4" />
                                        </Button>
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
