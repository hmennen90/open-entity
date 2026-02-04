<script setup>
import { computed } from 'vue';
import { useSettingsStore } from '../stores/settings';

const settingsStore = useSettingsStore();
const t = (key, params = {}) => settingsStore.t(key, params);

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
    return t(props.thought.type) || t('thought');
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

    if (diff < 60) return t('justNow');
    if (diff < 3600) return t('minutesAgo', { n: Math.floor(diff / 60) });
    if (diff < 86400) return t('hoursAgo', { n: Math.floor(diff / 3600) });
    return date.toLocaleDateString();
});

const toolExecution = computed(() => props.thought.tool_execution || null);

const toolIcon = computed(() => {
    if (!toolExecution.value) return null;
    const tool = toolExecution.value.tool?.toLowerCase();
    return {
        web: '🌐',
        webtool: '🌐',
        filesystem: '📁',
        filesystemtool: '📁',
        bash: '💻',
        bashtool: '💻',
        artisan: '⚙️',
        artisantool: '⚙️',
        documentation: '📝',
        documentationtool: '📝',
    }[tool] || '🔧';
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

        <!-- Tool Execution Details -->
        <div v-if="toolExecution" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-3">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-lg">{{ toolIcon }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        Tool: {{ toolExecution.tool }}
                    </span>
                    <span
                        class="px-2 py-0.5 text-xs rounded-full"
                        :class="toolExecution.success
                            ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                            : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'"
                    >
                        {{ toolExecution.success ? t('success') : t('failed') }}
                    </span>
                </div>

                <!-- Tool Parameters -->
                <div v-if="toolExecution.params && Object.keys(toolExecution.params).length" class="space-y-1 text-sm">
                    <div v-for="(value, key) in toolExecution.params" :key="key" class="flex items-start gap-2">
                        <span class="text-gray-500 dark:text-gray-400 font-mono">{{ key }}:</span>
                        <template v-if="key === 'url'">
                            <a
                                :href="value"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-entity-500 hover:text-entity-600 dark:text-entity-400 hover:underline break-all"
                            >
                                {{ value }}
                            </a>
                        </template>
                        <span v-else class="text-gray-700 dark:text-gray-300 break-all">{{ value }}</span>
                    </div>
                </div>

                <!-- Result Preview -->
                <div v-if="toolExecution.result_preview" class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('result') }}:</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-mono text-xs break-all">
                        {{ toolExecution.result_preview }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Simple Action (no tool) -->
        <div v-else-if="thought.led_to_action" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>{{ t('action') }}: {{ thought.action_taken }}</span>
            </div>
        </div>

        <!-- Intensity Bar -->
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ t('intensity') }}:</span>
            <div class="flex-1 progress-bar h-1">
                <div
                    class="progress-bar-fill"
                    :style="{ width: (thought.intensity * 100) + '%' }"
                ></div>
            </div>
        </div>
    </div>
</template>
