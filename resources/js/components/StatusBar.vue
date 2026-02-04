<script setup>
import { computed } from 'vue';
import { useEntityStore } from '../stores/entity';
import { useSettingsStore } from '../stores/settings';
import ThemeToggle from './ThemeToggle.vue';
import LanguageToggle from './LanguageToggle.vue';

const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

const t = (key, params = {}) => settingsStore.t(key, params);

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

const energyState = computed(() => entityStore.energy?.state || 'normal');
const hoursAwake = computed(() => {
    const hours = entityStore.energy?.hours_awake || 0;
    return hours.toFixed(1);
});

const energyBarColor = computed(() => {
    const level = entityStore.energy?.level || 0.5;
    if (level >= 0.7) return 'from-green-500 to-green-400';
    if (level >= 0.5) return 'from-entity-600 to-entity-400';
    if (level >= 0.3) return 'from-yellow-500 to-yellow-400';
    return 'from-red-500 to-red-400';
});

const needsSleep = computed(() => entityStore.energy?.needs_sleep || false);
</script>

<template>
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4 transition-colors duration-200">
        <div class="flex items-center justify-between">
            <!-- Left: Status Info -->
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">{{ t('status') }}:</span>
                    <span
                        class="status-indicator"
                        :class="entityStore.isAwake ? 'status-awake' : 'status-sleeping'"
                    ></span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ entityStore.isAwake ? t('awake') : t('sleeping') }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">{{ t('uptime') }}:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ uptimeFormatted }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">{{ t('mood') }}:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ moodState }}</span>
                    <span class="text-xl">{{ entityStore.moodEmoji }}</span>
                </div>
            </div>

            <!-- Right: Energy Bar + Toggles -->
            <div class="flex items-center gap-4">
                <!-- Energy Bar -->
                <div class="flex items-center gap-3">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">{{ t('energy') }}:</span>
                    <div class="w-32 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r transition-all duration-500"
                            :class="energyBarColor"
                            :style="{ width: moodEnergy + '%' }"
                        ></div>
                    </div>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ moodEnergy }}%</span>
                    <span
                        v-if="needsSleep"
                        class="text-xs text-red-500 dark:text-red-400 animate-pulse"
                        :title="'Awake for ' + hoursAwake + ' hours'"
                    >
                        💤 needs rest
                    </span>
                    <span
                        v-else
                        class="text-xs text-gray-400 dark:text-gray-500"
                        :title="energyState"
                    >
                        ({{ hoursAwake }}h)
                    </span>
                </div>

                <!-- Language Toggle -->
                <LanguageToggle />

                <!-- Theme Toggle -->
                <ThemeToggle />
            </div>
        </div>
    </header>
</template>
