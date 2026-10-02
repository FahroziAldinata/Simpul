<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Calendar,
    CalendarDays,
    ClipboardCheck,
    Clock,
    DoorOpen,
    FolderGit2,
    GraduationCap,
    Layers,
    LayoutGrid,
    ScrollText,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import type { NavItem } from '@/types';

interface SimpulAuth {
    roles?: string[];
    permissions?: string[];
}

const page = usePage();

function can(permission: string): boolean {
    const auth = (page.props.auth ?? {}) as SimpulAuth;
    if (auth.roles?.includes('super_admin')) return true;
    return auth.permissions?.includes(permission) ?? false;
}

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (can('pegawai.view')) {
        items.push({
            title: 'Kepegawaian',
            href: '/pegawai',
            icon: Users,
        });
    }

    if (can('siswa.view')) {
        items.push({
            title: 'Kesiswaan',
            href: '/siswa',
            icon: GraduationCap,
        });
    }

    if (can('jadwal.view')) {
        items.push({
            title: 'Jadwal',
            href: '/jadwal',
            icon: Calendar,
        });
    }

    if (can('absensi.view')) {
        items.push({
            title: 'Presensi',
            href: '#',
            icon: ClipboardCheck,
        });
    }

    if (can('audit_log.view')) {
        items.push({
            title: 'Audit Log',
            href: auditLogsIndex(),
            icon: ScrollText,
        });
    }

    return items;
});

const dataIndukNavItems = computed<NavItem[]>(() => {
    if (!can('data_induk.view')) {
        return [];
    }

    return [
        {
            title: 'Profil Sekolah',
            href: '/profil-sekolah',
            icon: Building2,
        },
        {
            title: 'Tahun Ajaran',
            href: '/tahun-ajaran',
            icon: CalendarDays,
        },
        {
            title: 'Kalender & Jam Kerja',
            href: '/kalender',
            icon: Calendar,
        },
        {
            title: 'Jurusan / Keahlian',
            href: '/jurusan',
            icon: Layers,
        },
        {
            title: 'Ruang & Fasilitas',
            href: '/ruang',
            icon: DoorOpen,
        },
        {
            title: 'Mata Pelajaran',
            href: '/mata-pelajaran',
            icon: BookOpen,
        },
        {
            title: 'Rombel',
            href: '/rombel',
            icon: Users,
        },
        {
            title: 'Alokasi Jam',
            href: '/alokasi-jam',
            icon: Clock,
        },
    ];
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="dataIndukNavItems.length > 0"
                label="Data Induk"
                :items="dataIndukNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
