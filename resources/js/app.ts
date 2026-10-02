import { createInertiaApp } from '@inertiajs/vue3';
import { registerSW } from 'virtual:pwa-register';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initClockSync } from '@/offline/clockSync';
import { initPollingFallback } from '@/offline/pollingFallback';
import { useOfflineQueue } from '@/offline/useOfflineQueue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Setup PWA Service Worker, Clock Drift Sync & Offline Queue Background Sync (T-10.01, T-10.07, T-10.09)
if (typeof window !== 'undefined') {
    initClockSync();
    initPollingFallback();

    if ('serviceWorker' in navigator) {
        registerSW({
            immediate: true,
            onRegisteredSW(_swUrl, _registration) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data?.type === 'TRIGGER_FLUSH') {
                        void useOfflineQueue().flush();
                    }
                });
            },
        });
    }
}
