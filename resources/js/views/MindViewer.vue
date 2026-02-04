<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useEntityStore } from '../stores/entity';
import { useSettingsStore } from '../stores/settings';
import ThoughtCard from '../components/ThoughtCard.vue';
import axios from 'axios';

const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

const t = (key, params = {}) => settingsStore.t(key, params);

// Computed to ensure name has a fallback
const entityName = computed(() => entityStore.name || 'Entity');

const thoughts = ref([]);
const isLoading = ref(true);
const filter = ref('all');

const filters = computed(() => [
    { value: 'all', label: t('all') },
    { value: 'observation', label: t('observations') },
    { value: 'reflection', label: t('reflections') },
    { value: 'curiosity', label: t('curiosities') },
    { value: 'emotion', label: t('emotions') },
    { value: 'decision', label: t('decisions') },
]);

async function fetchThoughts() {
    isLoading.value = true;
    try {
        const params = filter.value !== 'all' ? { type: filter.value } : {};
        const response = await axios.get('/api/v1/mind/thoughts', { params });
        thoughts.value = response.data.thoughts;
    } catch (error) {
        console.error('Failed to fetch thoughts:', error);
    } finally {
        isLoading.value = false;
    }
}

function addThought(thought) {
    if (filter.value === 'all' || filter.value === thought.type) {
        thoughts.value.unshift(thought);
    }
}

onMounted(() => {
    fetchThoughts();

    // Subscribe to real-time thought updates
    if (window.Echo) {
        window.Echo.channel('entity.mind')
            .listen('.thought.occurred', (data) => {
                addThought(data);
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('entity.mind');
    }
});
</script>

<template>
    <div class="p-8 bg-gray-50 dark:bg-gray-950 h-full overflow-y-auto transition-colors duration-200">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold mb-2 flex items-center gap-3 text-gray-900 dark:text-gray-100">
                        <svg class="w-8 h-8 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        {{ t('mindViewer') }}
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ t('watchThoughts', { name: entityName }) }}
                    </p>
                </div>

                <!-- Live Indicator -->
                <div class="flex items-center gap-2 px-4 py-2 bg-green-100 dark:bg-green-500/20 rounded-full">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-sm text-green-700 dark:text-green-400 font-medium">{{ t('live') }}</span>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="tab-list mb-6">
                <button
                    v-for="f in filters"
                    :key="f.value"
                    @click="filter = f.value; fetchThoughts()"
                    class="tab-button"
                    :class="{ active: filter === f.value }"
                >
                    {{ f.label }}
                </button>
            </div>

            <!-- Thoughts Stream -->
            <div class="space-y-4">
                <!-- Loading State -->
                <div v-if="isLoading" class="text-center py-12">
                    <div class="animate-spin w-8 h-8 border-4 border-entity-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-gray-500 dark:text-gray-400">{{ t('loadingThoughts') }}</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="thoughts.length === 0" class="empty-state">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    <h3 class="empty-state-title">{{ t('noThoughts') }}</h3>
                    <p class="empty-state-description">
                        {{ entityStore.isAwake ? t('waitingThought') : t('wakeEntity') }}
                    </p>
                </div>

                <!-- Thoughts List -->
                <TransitionGroup name="list" tag="div" class="space-y-4">
                    <ThoughtCard
                        v-for="thought in thoughts"
                        :key="thought.id"
                        :thought="thought"
                    />
                </TransitionGroup>
            </div>
        </div>
    </div>
</template>

<style scoped>
.list-enter-active {
    transition: all 0.3s ease-out;
}

.list-enter-from {
    opacity: 0;
    transform: translateY(-20px);
}

.list-leave-active {
    transition: all 0.3s ease-in;
}

.list-leave-to {
    opacity: 0;
}
</style>
