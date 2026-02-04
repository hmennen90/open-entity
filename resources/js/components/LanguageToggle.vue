<script setup>
import { useSettingsStore } from '../stores/settings';

const settingsStore = useSettingsStore();

function toggleLanguage() {
    const newLang = settingsStore.language === 'de' ? 'en' : 'de';
    settingsStore.setLanguage(newLang);
}
</script>

<template>
    <button
        @click="toggleLanguage"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200"
        :title="settingsStore.language === 'de' ? 'Switch to English' : 'Zu Deutsch wechseln'"
    >
        <span class="text-lg">{{ settingsStore.language === 'de' ? '🇩🇪' : '🇬🇧' }}</span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ settingsStore.language === 'de' ? 'DE' : 'EN' }}
        </span>
    </button>
</template>
