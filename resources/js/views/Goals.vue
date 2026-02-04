<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const goals = ref([]);
const isLoading = ref(true);
const filter = ref('active');

const filters = [
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
    { value: 'all', label: 'All' },
];

const filteredGoals = computed(() => {
    if (filter.value === 'all') return goals.value;
    return goals.value.filter(g => g.status === filter.value);
});

async function fetchGoals() {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/v1/goals');
        goals.value = response.data.goals;
    } catch (error) {
        console.error('Failed to fetch goals:', error);
    } finally {
        isLoading.value = false;
    }
}

function getTypeIcon(type) {
    return {
        curiosity: '🤔',
        social: '👥',
        learning: '📚',
        creative: '🎨',
        'self-improvement': '🌱',
    }[type] || '🎯';
}

function getStatusClass(status) {
    return {
        active: 'badge-success',
        paused: 'badge-warning',
        completed: 'badge-primary',
        abandoned: 'badge-gray',
    }[status] || 'badge-gray';
}

onMounted(fetchGoals);
</script>

<template>
    <div class="p-8 bg-gray-50 dark:bg-gray-950 h-full overflow-y-auto transition-colors duration-200">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2 flex items-center gap-3 text-gray-900 dark:text-gray-100">
                    <svg class="w-8 h-8 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Goals & Aspirations
                </h1>
                <p class="text-gray-500 dark:text-gray-400">
                    Self-defined goals that drive curiosity and growth.
                </p>
            </div>

            <!-- Filter Tabs -->
            <div class="tab-list mb-6">
                <button
                    v-for="f in filters"
                    :key="f.value"
                    @click="filter = f.value"
                    class="tab-button"
                    :class="{ active: filter === f.value }"
                >
                    {{ f.label }}
                </button>
            </div>

            <!-- Goals List -->
            <div class="space-y-4">
                <!-- Loading -->
                <div v-if="isLoading" class="text-center py-12">
                    <div class="animate-spin w-8 h-8 border-4 border-entity-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-gray-500 dark:text-gray-400">Loading goals...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="filteredGoals.length === 0" class="empty-state">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <span class="text-4xl">🎯</span>
                    </div>
                    <h3 class="empty-state-title">No {{ filter }} goals</h3>
                    <p class="empty-state-description">Goals are self-defined based on curiosity and interests.</p>
                </div>

                <!-- Goal Cards -->
                <div
                    v-for="goal in filteredGoals"
                    :key="goal.id"
                    class="card hover:shadow-xl transition-all"
                >
                    <div class="card-body">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{{ getTypeIcon(goal.type) }}</span>
                                <div>
                                    <h3 class="font-semibold text-lg text-gray-900 dark:text-gray-100">{{ goal.title }}</h3>
                                    <span :class="['badge', getStatusClass(goal.status)]">
                                        {{ goal.status }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-entity-600 dark:text-entity-500">
                                    {{ Math.round(goal.progress * 100) }}%
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Progress</div>
                            </div>
                        </div>

                        <!-- Description -->
                        <p v-if="goal.description" class="text-gray-600 dark:text-gray-300 mb-4">
                            {{ goal.description }}
                        </p>

                        <!-- Motivation -->
                        <div v-if="goal.motivation" class="bg-gray-100 dark:bg-gray-900/50 rounded-lg p-3 mb-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Motivation</div>
                            <p class="text-gray-600 dark:text-gray-400 italic">"{{ goal.motivation }}"</p>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="progress-bar">
                                <div
                                    class="progress-bar-fill"
                                    :style="{ width: (goal.progress * 100) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span class="capitalize">{{ goal.type }}</span>
                            <span>Priority: {{ Math.round(goal.priority * 100) }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
