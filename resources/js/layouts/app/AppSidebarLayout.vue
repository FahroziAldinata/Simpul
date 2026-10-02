<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import TopbarOfflineIndicator from '@/components/offline/TopbarOfflineIndicator.vue';
import ReadOnlyBanner from '@/components/ReadOnlyBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <TopbarOfflineIndicator />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <ReadOnlyBanner />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
