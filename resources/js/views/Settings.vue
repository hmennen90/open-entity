<script setup>
import { ref, onMounted } from 'vue';
import { useEntityStore } from '../stores/entity';
import { useSettingsStore } from '../stores/settings';

const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

const personality = ref({});
const isLoading = ref(true);

async function loadSettings() {
    isLoading.value = true;
    await entityStore.fetchState();
    personality.value = { ...entityStore.personality };
    isLoading.value = false;
}

onMounted(loadSettings);
</script>

<template>
    <div class="p-8 bg-gray-50 dark:bg-gray-950 min-h-full transition-colors duration-200">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2 flex items-center gap-3 text-gray-900 dark:text-gray-100">
                    <svg class="w-8 h-8 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </h1>
                <p class="text-gray-500 dark:text-gray-400">
                    View and configure entity settings.
                </p>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="text-center py-12">
                <div class="animate-spin w-8 h-8 border-4 border-entity-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                <p class="text-gray-500 dark:text-gray-400">Loading settings...</p>
            </div>

            <div v-else class="space-y-6">
                <!-- Appearance Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Appearance</h2>
                    </div>
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-gray-900 dark:text-gray-100 font-medium">Theme</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Choose between light, dark, or system theme
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="settingsStore.setTheme('light')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="settingsStore.theme === 'light'
                                        ? 'bg-entity-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                                >
                                    Light
                                </button>
                                <button
                                    @click="settingsStore.setTheme('dark')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="settingsStore.theme === 'dark'
                                        ? 'bg-entity-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                                >
                                    Dark
                                </button>
                                <button
                                    @click="settingsStore.setTheme('system')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="settingsStore.theme === 'system'
                                        ? 'bg-entity-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                                >
                                    System
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Entity Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Entity Information</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Name</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ personality.name || entityStore.name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Status</span>
                            <span class="capitalize font-medium text-gray-900 dark:text-gray-100">{{ entityStore.status }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">LLM Driver</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">Ollama</span>
                        </div>
                    </div>
                </div>

                <!-- Personality Traits Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Personality Traits</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div v-for="(value, trait) in personality.traits" :key="trait">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-gray-500 dark:text-gray-400 capitalize">{{ trait }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ Math.round(value * 100) }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div
                                    class="progress-bar-fill"
                                    :style="{ width: (value * 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Communication Style Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Communication Style</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div v-for="(value, style) in personality.communication_style" :key="style">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-gray-500 dark:text-gray-400 capitalize">{{ style }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ Math.round(value * 100) }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div
                                    class="h-full rounded-full bg-purple-500 transition-all"
                                    :style="{ width: (value * 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Core Values Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Core Values</h2>
                    </div>
                    <div class="card-body">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="value in personality.core_values"
                                :key="value"
                                class="badge badge-primary"
                            >
                                {{ value }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Self Description Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Self Description</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-gray-600 dark:text-gray-300 italic">
                            "{{ personality.self_description }}"
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
