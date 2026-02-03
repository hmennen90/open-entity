<script setup>
import { computed } from 'vue';

const props = defineProps({
    thought: {
        type: Object,
        required: true,
    },
});

const typeColor = computed(() => {
    return {
        observation: 'border-blue-500',
        reflection: 'border-purple-500',
        curiosity: 'border-yellow-500',
        emotion: 'border-red-500',
        decision: 'border-green-500',
    }[props.thought.type] || 'border-gray-500';
});

const typeLabel = computed(() => {
    return {
        observation: 'Observation',
        reflection: 'Reflection',
        curiosity: 'Curiosity',
        emotion: 'Emotion',
        decision: 'Decision',
    }[props.thought.type] || 'Thought';
});

const typeIcon = computed(() => {
    return {
        observation: '👀',
        reflection: '💭',
        curiosity: '🤔',
        emotion: '💗',
        decision: '💡',
    }[props.thought.type] || '💬';
});

const timeAgo = computed(() => {
    const date = new Date(props.thought.created_at);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return date.toLocaleDateString();
});
</script>

<template>
    <div
        class="thought-bubble border-l-4 transition-all hover:shadow-xl"
        :class="typeColor"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <span class="text-lg">{{ typeIcon }}</span>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    {{ typeLabel }}
                </span>
            </div>
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ timeAgo }}</span>
        </div>

        <!-- Content -->
        <p class="text-gray-700 dark:text-gray-200">{{ thought.content }}</p>

        <!-- Footer (if action taken) -->
        <div v-if="thought.led_to_action" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Action: {{ thought.action_taken }}</span>
            </div>
        </div>

        <!-- Intensity Bar -->
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Intensity:</span>
            <div class="flex-1 progress-bar h-1">
                <div
                    class="progress-bar-fill"
                    :style="{ width: (thought.intensity * 100) + '%' }"
                ></div>
            </div>
        </div>
    </div>
</template>
