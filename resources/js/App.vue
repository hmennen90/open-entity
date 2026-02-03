<script setup>
import { onMounted } from 'vue';
import { useEntityStore } from './stores/entity';
import { useSettingsStore } from './stores/settings';
import Sidebar from './components/Sidebar.vue';
import StatusBar from './components/StatusBar.vue';
import NotificationToast from './components/NotificationToast.vue';

const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

onMounted(() => {
    // Initialize theme
    settingsStore.init();

    // Fetch entity status and subscribe to updates
    entityStore.fetchStatus();
    entityStore.subscribeToUpdates();
});
</script>

<template>
    <div class="h-screen overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex transition-colors duration-200">
        <!-- Notification Toasts -->
        <NotificationToast />

        <!-- Sidebar Navigation -->
        <Sidebar />

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0">
            <!-- Status Bar -->
            <StatusBar />

            <!-- Router View -->
            <div class="flex-1 min-h-0 overflow-hidden">
                <router-view v-slot="{ Component }">
                    <transition name="fade" mode="out-in">
                        <component :is="Component" class="h-full" />
                    </transition>
                </router-view>
            </div>
        </main>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
