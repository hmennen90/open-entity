<script setup>
import { onMounted } from 'vue';
import { useEntityStore } from '../stores/entity';
import ThoughtCard from '../components/ThoughtCard.vue';

const entityStore = useEntityStore();

onMounted(() => {
    entityStore.fetchState();
});
</script>

<template>
    <div class="p-8 bg-gray-50 dark:bg-gray-950 min-h-full transition-colors duration-200">
        <div class="max-w-6xl mx-auto">
            <!-- Welcome Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2 text-gray-900 dark:text-gray-100">Welcome to OpenEntity</h1>
                <p class="text-gray-500 dark:text-gray-400">
                    Observe {{ entityStore.name }}'s consciousness in real-time.
                </p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Status Card -->
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Status</p>
                                <p class="text-2xl font-bold capitalize text-gray-900 dark:text-gray-100">{{ entityStore.status }}</p>
                            </div>
                            <div
                                class="w-12 h-12 rounded-full flex items-center justify-center"
                                :class="entityStore.isAwake ? 'bg-green-100 dark:bg-green-500/20' : 'bg-yellow-100 dark:bg-yellow-500/20'"
                            >
                                <span
                                    class="status-indicator w-4 h-4"
                                    :class="entityStore.isAwake ? 'status-awake' : 'status-sleeping'"
                                ></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mood Card -->
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Current Mood</p>
                                <p class="text-2xl font-bold capitalize text-gray-900 dark:text-gray-100">
                                    {{ entityStore.currentMood.state || 'Neutral' }}
                                </p>
                            </div>
                            <div class="text-4xl">{{ entityStore.moodEmoji }}</div>
                        </div>
                    </div>
                </div>

                <!-- Active Goals Card -->
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Active Goals</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ entityStore.activeGoals.length }}</p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-entity-100 dark:bg-entity-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-entity-600 dark:text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Thoughts -->
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold flex items-center gap-2 text-gray-900 dark:text-gray-100">
                        <svg class="w-5 h-5 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        Recent Thoughts
                    </h2>
                </div>
                <div class="card-body space-y-4">
                    <div v-if="entityStore.recentThoughts.length === 0" class="text-center py-8">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400">No thoughts yet. Wake up the entity to start thinking.</p>
                    </div>
                    <ThoughtCard
                        v-for="thought in entityStore.recentThoughts"
                        :key="thought.id"
                        :thought="thought"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
