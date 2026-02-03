<script setup>
import { useEntityStore } from '../stores/entity';
import { useRouter } from 'vue-router';

const entityStore = useEntityStore();
const router = useRouter();

function handleNotificationClick(notification) {
    if (notification.type === 'question') {
        // Navigate to chat
        router.push('/chat');
    }
    entityStore.dismissNotification(notification.id);
}
</script>

<template>
    <div class="fixed top-4 right-4 z-50 space-y-2 max-w-md">
        <TransitionGroup name="toast">
            <div
                v-for="notification in entityStore.notifications"
                :key="notification.id"
                class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-4 cursor-pointer hover:shadow-xl transition-shadow"
                @click="handleNotificationClick(notification)"
            >
                <!-- Header -->
                <div class="flex items-start gap-3">
                    <!-- Icon -->
                    <div
                        v-if="notification.type === 'question'"
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"
                    >
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ notification.title }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                            {{ notification.message }}
                        </p>
                        <p class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                            Click to open chat
                        </p>
                    </div>

                    <!-- Close button -->
                    <button
                        class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        @click.stop="entityStore.dismissNotification(notification.id)"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active {
    animation: toast-in 0.3s ease-out;
}

.toast-leave-active {
    animation: toast-out 0.2s ease-in;
}

@keyframes toast-in {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes toast-out {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}
</style>
