<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useEntityStore } from '../stores/entity';
import { useSettingsStore } from '../stores/settings';

const route = useRoute();
const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

const t = (key, params = {}) => settingsStore.t(key, params);

const navItems = computed(() => [
    { name: t('home'), path: '/', icon: 'home' },
    { name: t('chat'), path: '/chat', icon: 'chat' },
    { name: t('mind'), path: '/mind', icon: 'brain' },
    { name: t('memory'), path: '/memory', icon: 'memory' },
    { name: t('goals'), path: '/goals', icon: 'target' },
    { name: t('settings'), path: '/settings', icon: 'settings' },
]);

const isActive = (path) => route.path === path;

// Computed to ensure name has a fallback
const entityName = computed(() => entityStore.name || 'Entity');
</script>

<template>
    <aside class="w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col transition-colors duration-200">
        <!-- Logo/Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-entity-500 to-purple-600 flex items-center justify-center shadow-lg">
                    <span class="text-xl">{{ entityStore.moodEmoji }}</span>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-gray-900 dark:text-gray-100">{{ entityName }}</h1>
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span
                            class="status-indicator"
                            :class="entityStore.isAwake ? 'status-awake' : 'status-sleeping'"
                        ></span>
                        {{ t(entityStore.status) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-4">
            <ul class="space-y-2">
                <li v-for="item in navItems" :key="item.path">
                    <router-link
                        :to="item.path"
                        class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors duration-200"
                        :class="isActive(item.path)
                            ? 'bg-entity-600 text-white shadow-md'
                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200'"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <template v-if="item.icon === 'home'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </template>
                            <template v-else-if="item.icon === 'chat'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </template>
                            <template v-else-if="item.icon === 'brain'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </template>
                            <template v-else-if="item.icon === 'memory'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </template>
                            <template v-else-if="item.icon === 'target'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </template>
                            <template v-else-if="item.icon === 'settings'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </template>
                        </svg>
                        <span>{{ item.name }}</span>
                    </router-link>
                </li>
            </ul>
        </nav>

        <!-- Wake/Sleep Controls -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            <button
                v-if="!entityStore.isAwake"
                @click="entityStore.wake"
                class="w-full btn btn-primary"
            >
                {{ t('wakeUp') }}
            </button>
            <button
                v-else
                @click="entityStore.sleep"
                class="w-full btn btn-secondary"
            >
                {{ t('goToSleep') }}
            </button>
        </div>
    </aside>
</template>
