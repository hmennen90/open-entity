<script setup>
import { computed } from 'vue';
import { useSettingsStore } from '../stores/settings';

const settingsStore = useSettingsStore();

const currentTheme = computed(() => settingsStore.theme);
const effectiveTheme = computed(() => settingsStore.getEffectiveTheme());

const themeIcon = computed(() => {
    if (currentTheme.value === 'system') {
        return 'computer';
    }
    return effectiveTheme.value === 'dark' ? 'moon' : 'sun';
});

const themeLabel = computed(() => {
    const labels = {
        light: 'Light',
        dark: 'Dark',
        system: 'System',
    };
    return labels[currentTheme.value];
});

function handleClick() {
    settingsStore.cycleTheme();
}
</script>

<template>
    <button
        @click="handleClick"
        class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors
               bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
               text-gray-700 dark:text-gray-300"
        :title="`Theme: ${themeLabel} (click to change)`"
    >
        <!-- Sun Icon -->
        <svg
            v-if="themeIcon === 'sun'"
            class="w-5 h-5 text-yellow-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
            />
        </svg>

        <!-- Moon Icon -->
        <svg
            v-if="themeIcon === 'moon'"
            class="w-5 h-5 text-indigo-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
            />
        </svg>

        <!-- Computer/System Icon -->
        <svg
            v-if="themeIcon === 'computer'"
            class="w-5 h-5 text-gray-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
        </svg>

        <span class="text-sm font-medium hidden sm:inline">{{ themeLabel }}</span>
    </button>
</template>
