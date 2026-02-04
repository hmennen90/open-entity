<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useMemoryStore } from '../stores/memory';
import MemoryDetailModal from '../components/MemoryDetailModal.vue';

const memoryStore = useMemoryStore();
const searchInput = ref('');
const showModal = ref(false);

const tabs = [
    { value: 'all', label: 'All Memories', icon: 'collection' },
    { value: 'experience', label: 'Experiences', icon: 'star' },
    { value: 'conversation', label: 'Conversations', icon: 'chat' },
    { value: 'learned', label: 'Learned', icon: 'book' },
    { value: 'social', label: 'Social', icon: 'users' },
];

function getTypeClass(type) {
    const classes = {
        experience: 'memory-type-experience',
        conversation: 'memory-type-conversation',
        learned: 'memory-type-learned',
        social: 'memory-type-social',
        reflection: 'memory-type-reflection',
    };
    return classes[type] || 'memory-type-experience';
}

function getEmotionDisplay(valence) {
    if (valence > 0.3) return { text: 'Positive', class: 'text-green-600 dark:text-green-400', icon: '+' };
    if (valence < -0.3) return { text: 'Negative', class: 'text-red-600 dark:text-red-400', icon: '-' };
    return { text: 'Neutral', class: 'text-gray-600 dark:text-gray-400', icon: '~' };
}

function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('de-DE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function handleSearch() {
    memoryStore.searchMemories(searchInput.value);
}

function handleTabChange(tab) {
    memoryStore.setFilter(tab);
}

function openMemoryDetail(memory) {
    memoryStore.selectMemory(memory);
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    memoryStore.clearSelection();
}

function clearSearch() {
    searchInput.value = '';
    memoryStore.clearSearch();
}

// Debounced search
let searchTimeout = null;
watch(searchInput, (newValue) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        memoryStore.searchMemories(newValue);
    }, 300);
});

onMounted(() => {
    memoryStore.fetchMemories();
});
</script>

<template>
    <div class="p-8 bg-gray-50 dark:bg-gray-950 h-full overflow-y-auto transition-colors duration-200">
        <div class="max-w-5xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2 flex items-center gap-3 text-gray-900 dark:text-gray-100">
                    <svg class="w-8 h-8 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    Memory Browser
                </h1>
                <p class="text-gray-500 dark:text-gray-400">
                    Browse through stored experiences, conversations, and learned knowledge.
                </p>
            </div>

            <!-- Search & Stats -->
            <div class="card mb-6">
                <div class="card-body">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search Input -->
                        <div class="flex-1 relative">
                            <svg
                                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                v-model="searchInput"
                                type="text"
                                class="input pl-10 pr-10"
                                placeholder="Search memories by content, summary, or entity..."
                            />
                            <button
                                v-if="searchInput"
                                @click="clearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Stats -->
                        <div class="flex items-center gap-4 text-sm">
                            <div class="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 rounded-lg">
                                <span class="text-gray-500 dark:text-gray-400">Total:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ memoryStore.totalMemories }}</span>
                            </div>
                            <div class="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 rounded-lg">
                                <span class="text-gray-500 dark:text-gray-400">Showing:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ memoryStore.filteredMemories.length }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="tab-list mb-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    @click="handleTabChange(tab.value)"
                    class="tab-button"
                    :class="{ active: memoryStore.activeFilter === tab.value }"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Memory Grid -->
            <div>
                <!-- Loading State -->
                <div v-if="memoryStore.isLoading && memoryStore.memories.length === 0" class="grid gap-4 md:grid-cols-2">
                    <div v-for="n in 6" :key="n" class="skeleton-card"></div>
                </div>

                <!-- Empty State -->
                <div v-else-if="memoryStore.filteredMemories.length === 0" class="empty-state">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="empty-state-title">No memories found</h3>
                    <p class="empty-state-description">
                        {{ searchInput ? 'Try a different search term.' : 'Memories will appear here as they are created.' }}
                    </p>
                    <button
                        v-if="searchInput || memoryStore.activeFilter !== 'all'"
                        @click="clearSearch(); handleTabChange('all')"
                        class="mt-4 btn btn-secondary"
                    >
                        Clear Filters
                    </button>
                </div>

                <!-- Memory Cards -->
                <div v-else class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="memory in memoryStore.filteredMemories"
                        :key="memory.id"
                        @click="openMemoryDetail(memory)"
                        class="memory-card group"
                        :class="{ selected: memoryStore.selectedMemory?.id === memory.id }"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-3">
                            <span :class="['memory-type-badge', getTypeClass(memory.type)]">
                                {{ memory.type }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatDate(memory.created_at) }}
                            </span>
                        </div>

                        <!-- Content Preview -->
                        <p class="text-gray-700 dark:text-gray-300 mb-3 line-clamp-3">
                            {{ memory.summary || memory.content }}
                        </p>

                        <!-- Footer -->
                        <div class="flex items-center justify-between text-sm pt-3 border-t border-gray-200 dark:border-gray-700">
                            <!-- Related Entity -->
                            <div v-if="memory.related_entity" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">{{ memory.related_entity }}</span>
                            </div>
                            <div v-else></div>

                            <!-- Metrics -->
                            <div class="flex items-center gap-3">
                                <!-- Importance -->
                                <div class="flex items-center gap-1.5">
                                    <div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-entity-500 rounded-full"
                                            :style="{ width: (memory.importance * 100) + '%' }"
                                        ></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ Math.round(memory.importance * 100) }}%
                                    </span>
                                </div>

                                <!-- Emotional Valence -->
                                <span
                                    v-if="memory.emotional_valence !== undefined"
                                    :class="['text-xs font-medium', getEmotionDisplay(memory.emotional_valence).class]"
                                >
                                    {{ getEmotionDisplay(memory.emotional_valence).icon }}{{ Math.abs(Math.round(memory.emotional_valence * 100)) }}%
                                </span>
                            </div>
                        </div>

                        <!-- Hover indicator -->
                        <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-5 h-5 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Load More -->
                <div v-if="memoryStore.hasMorePages" class="mt-6 text-center">
                    <button
                        @click="memoryStore.loadMore"
                        :disabled="memoryStore.isLoading"
                        class="btn btn-secondary"
                    >
                        <span v-if="memoryStore.isLoading" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                        <span v-else>Load More</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Memory Detail Modal -->
        <MemoryDetailModal
            v-if="showModal && memoryStore.selectedMemory"
            :memory="memoryStore.selectedMemory"
            @close="closeModal"
        />
    </div>
</template>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.memory-card {
    position: relative;
}
</style>
