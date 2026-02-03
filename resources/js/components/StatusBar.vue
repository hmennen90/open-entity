<script setup>
import { computed } from 'vue';
import { useEntityStore } from '../stores/entity';
import ThemeToggle from './ThemeToggle.vue';

const entityStore = useEntityStore();

const uptimeFormatted = computed(() => {
    if (!entityStore.uptime) return 'N/A';

    const hours = Math.floor(entityStore.uptime / 3600);
    const minutes = Math.floor((entityStore.uptime % 3600) / 60);

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
});

const moodState = computed(() => entityStore.currentMood.state || 'neutral');
const moodEnergy = computed(() => {
    const energy = entityStore.currentMood.energy || 0.5;
    return Math.round(energy * 100);
});
</script>

<template>
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4 transition-colors duration-200">
        <div class="flex items-center justify-between">
            <!-- Left: Status Info -->
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Status:</span>
                    <span
                        class="status-indicator"
                        :class="entityStore.isAwake ? 'status-awake' : 'status-sleeping'"
                    ></span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ entityStore.status }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Uptime:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ uptimeFormatted }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Mood:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ moodState }}</span>
                    <span class="text-xl">{{ entityStore.moodEmoji }}</span>
                </div>
            </div>

            <!-- Right: Energy Bar + Theme Toggle -->
            <div class="flex items-center gap-6">
                <!-- Energy Bar -->
                <div class="flex items-center gap-3">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Energy:</span>
                    <div class="w-32 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-entity-600 to-entity-400 transition-all duration-500"
                            :style="{ width: moodEnergy + '%' }"
                        ></div>
                    </div>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ moodEnergy }}%</span>
                </div>

                <!-- Theme Toggle -->
                <ThemeToggle />
            </div>
        </div>
    </header>
</template>
