<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { CheckCircle2, XCircle, Clock, User, Calendar } from '@lucide/vue'

interface Persetujuan {
    id: string
    urutan: number
    approver_role: string
    status: string
    catatan: string | null
    pengajuan: {
        id: string
        tanggal_mulai: string
        tanggal_selesai: string
        alasan: string
        status: string
        pegawai: { id: string; nama: string; jenis: string } | null
        jenis_izin: { id: string; nama: string; kode: string | null } | null
        persetujuan: { urutan: number; approver_role: string; status: string }[]
    }
}

const props = defineProps<{ langkah: Persetujuan[] }>()

const dialogId = ref<string | null>(null)
const form = useForm({ keputusan: 'disetujui', catatan: '' })

function buka(id: string) {
    dialogId.value = id
    form.keputusan = 'disetujui'
    form.catatan = ''
}

function putuskan() {
    if (!dialogId.value) return
    form.post(route('izin.inbox.putuskan', dialogId.value), {
        onSuccess: () => { dialogId.value = null },
    })
}

function formatTanggal(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function jumlahHari(mulai: string, selesai: string) {
    const m = new Date(mulai), s = new Date(selesai)
    return Math.round((s.getTime() - m.getTime()) / 86400000) + 1
}

const langkahAktif = props.langkah.find(l => l.id === dialogId.value)
</script>

<template>
    <AppLayout>
        <Head title="Inbox Persetujuan" />

        <div class="p-6 space-y-6">
            <div>
                <h1 class="text-2xl font-bold">Inbox Persetujuan</h1>
                <p class="text-muted-foreground text-sm">
                    {{ langkah.length }} pengajuan menunggu keputusan Anda
                </p>
            </div>

            <div v-if="langkah.length === 0" class="text-center py-16 text-muted-foreground">
                <CheckCircle2 class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p>Tidak ada pengajuan yang menunggu keputusan Anda.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="l in langkah" :key="l.id" class="hover:shadow-md transition-shadow">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ l.pengajuan.jenis_izin?.nama ?? 'Izin' }}</p>
                                <p class="text-sm text-muted-foreground flex items-center gap-1">
                                    <User class="w-3 h-3" />
                                    {{ l.pengajuan.pegawai?.nama ?? '—' }}
                                    <span class="capitalize text-xs">({{ l.pengajuan.pegawai?.jenis }})</span>
                                </p>
                            </div>
                            <Badge variant="secondary" class="shrink-0">Langkah {{ l.urutan }}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center gap-2 text-sm">
                            <Calendar class="w-4 h-4 text-muted-foreground" />
                            <span>{{ formatTanggal(l.pengajuan.tanggal_mulai) }}</span>
                            <span class="text-muted-foreground">–</span>
                            <span>{{ formatTanggal(l.pengajuan.tanggal_selesai) }}</span>
                            <Badge variant="outline" class="text-xs">{{ jumlahHari(l.pengajuan.tanggal_mulai, l.pengajuan.tanggal_selesai) }} hari</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground line-clamp-2">{{ l.pengajuan.alasan }}</p>

                        <!-- Progress langkah -->
                        <div class="flex items-center gap-1.5">
                            <div
                                v-for="step in l.pengajuan.persetujuan"
                                :key="step.urutan"
                                :title="`${step.approver_role}: ${step.status}`"
                                :class="[
                                    'h-1.5 flex-1 rounded-full',
                                    step.status === 'disetujui' ? 'bg-green-500' :
                                    step.status === 'ditolak' ? 'bg-red-500' :
                                    step.urutan === l.urutan ? 'bg-amber-400 animate-pulse' :
                                    'bg-muted'
                                ]"
                            />
                        </div>

                        <Button
                            :id="`btn-putuskan-${l.id}`"
                            class="w-full"
                            @click="buka(l.id)"
                        >
                            Beri Keputusan
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Dialog Putuskan -->
        <Dialog :open="dialogId !== null" @update:open="(v) => { if (!v) dialogId = null }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Keputusan Persetujuan</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="putuskan" class="space-y-4 mt-2">
                    <RadioGroup v-model="form.keputusan" class="gap-3">
                        <div class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer" :class="form.keputusan === 'disetujui' ? 'border-green-500 bg-green-50' : ''">
                            <RadioGroupItem id="radio-setuju" value="disetujui" />
                            <Label for="radio-setuju" class="flex items-center gap-2 cursor-pointer">
                                <CheckCircle2 class="w-5 h-5 text-green-600" />
                                Setuju
                            </Label>
                        </div>
                        <div class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer" :class="form.keputusan === 'ditolak' ? 'border-red-500 bg-red-50' : ''">
                            <RadioGroupItem id="radio-tolak" value="ditolak" />
                            <Label for="radio-tolak" class="flex items-center gap-2 cursor-pointer">
                                <XCircle class="w-5 h-5 text-red-600" />
                                Tolak
                            </Label>
                        </div>
                    </RadioGroup>

                    <div class="space-y-1">
                        <Label for="catatan-keputusan">
                            Catatan
                            <span v-if="form.keputusan === 'ditolak'" class="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="catatan-keputusan"
                            v-model="form.catatan"
                            :required="form.keputusan === 'ditolak'"
                            placeholder="Tambahkan catatan (wajib jika menolak)..."
                            rows="3"
                        />
                        <p v-if="form.errors.catatan" class="text-destructive text-xs">{{ form.errors.catatan }}</p>
                    </div>

                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="dialogId = null" class="flex-1">Batal</Button>
                        <Button
                            type="submit"
                            :variant="form.keputusan === 'ditolak' ? 'destructive' : 'default'"
                            :disabled="form.processing"
                            class="flex-1"
                            id="btn-konfirmasi-keputusan"
                        >
                            {{ form.processing ? 'Menyimpan...' : (form.keputusan === 'disetujui' ? 'Setujui' : 'Tolak') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
