<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useMemoryStore } from '../stores/memory';

const props = defineProps({
    memory: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const memoryStore = useMemoryStore();
const relatedMemories = ref([]);
const loadingRelated = ref(false);

const memoryTypeClass = computed(() => {
    const classes = {
        experience: 'memory-type-experience',
        conversation: 'memory-type-conversation',
        learned: 'memory-type-learned',
        social: 'memory-type-social',
        reflection: 'memory-type-reflection',
    };
    return classes[props.memory.type] || 'memory-type-experience';
});

const importancePercent = computed(() => {
    return Math.round((props.memory.importance || 0) * 100);
});

const emotionalValence = computed(() => {
    const valence = props.memory.emotional_valence || 0;
    if (valence > 0.3) return { label: 'Positive', class: 'text-green-600 dark:text-green-400' };
    if (valence < -0.3) return { label: 'Negative', class: 'text-red-600 dark:text-red-400' };
    return { label: 'Neutral', class: 'text-gray-600 dark:text-gray-400' };
});

const formattedDate = computed(() => {
    if (!props.memory.created_at) return 'Unknown';
    return new Date(props.memory.created_at).toLocaleString('de-DE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

async function loadRelatedMemories() {
    if (!props.memory.id) return;

    loadingRelated.value = true;
    try {
        relatedMemories.value = await memoryStore.fetchRelatedMemories(props.memory.id);
    } catch (e) {
        console.error('Failed to load related memories:', e);
    } finally {
        loadingRelated.value = false;
    }
}

function handleBackdropClick(event) {
    if (event.target === event.currentTarget) {
        emit('close');
    }
}

function handleEscape(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
    loadRelatedMemories();
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <div
        class="modal-overlay"
        @click="handleBackdropClick"
    >
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header">
                <div class="flex items-center gap-3">
                    <span :class="['memory-type-badge', memoryTypeClass]">
                        {{ memory.type }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ formattedDate }}
                    </span>
                </div>
                <button
                    @click="$emit('close')"
                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body space-y-6">
                <!-- Summary -->
                <div v-if="memory.summary">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        Summary
                    </h3>
                    <p class="text-gray-700 dark:text-gray-300">
                        {{ memory.summary }}
                    </p>
                </div>

                <!-- Full Content -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        Content
                    </h3>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                            {{ memory.content }}
                        </p>
                    </div>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Importance -->
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                            Importance
                        </h4>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 progress-bar">
                                <div
                                    class="progress-bar-fill"
                                    :style="{ width: importancePercent + '%' }"
                                ></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ importancePercent }}%
                            </span>
                        </div>
                    </div>

                    <!-- Emotional Valence -->
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                            Emotional Valence
                        </h4>
                        <span :class="['text-lg font-medium', emotionalValence.class]">
                            {{ emotionalValence.label }}
                        </span>
                    </div>

                    <!-- Related Entity -->
                    <div v-if="memory.related_entity" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                            Related Entity
                        </h4>
                        <span class="text-gray-900 dark:text-gray-100">
                            {{ memory.related_entity }}
                        </span>
                    </div>

                    <!-- Context -->
                    <div v-if="memory.context" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                            Context
                        </h4>
                        <span class="text-gray-900 dark:text-gray-100">
                            {{ typeof memory.context === 'object' ? JSON.stringify(memory.context) : memory.context }}
                        </span>
                    </div>
                </div>

                <!-- Related Memories -->
                <div v-if="relatedMemories.length > 0 || loadingRelated">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Related Memories
                    </h3>

                    <!-- Loading State -->
                    <div v-if="loadingRelated" class="space-y-3">
                        <div v-for="n in 3" :key="n" class="skeleton-card h-20"></div>
                    </div>

                    <!-- Related List -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="related in relatedMemories"
                            :key="related.id"
                            class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 flex items-start gap-3"
                        >
                            <span :class="['memory-type-badge', `memory-type-${related.type}`]">
                                {{ related.type }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                    {{ related.summary || related.content }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button @click="$emit('close')" class="btn btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>
</template>
