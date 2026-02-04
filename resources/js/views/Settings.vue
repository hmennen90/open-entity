<script setup>
import { ref, onMounted, computed } from 'vue';
import { useEntityStore } from '../stores/entity';
import { useSettingsStore } from '../stores/settings';
import axios from 'axios';

const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

const t = (key, params = {}) => settingsStore.t(key, params);

const personality = ref({});
const isLoading = ref(true);

// LLM Configuration State
const llmConfigurations = ref([]);
const llmDrivers = ref({});
const isLoadingLlm = ref(true);
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingConfig = ref(null);
const testResult = ref(null);
const isTesting = ref(null);

// New configuration form
const newConfig = ref({
    name: '',
    driver: 'nvidia',
    model: '',
    api_key: '',
    base_url: '',
    is_active: true,
    is_default: false,
    priority: 50,
    options: {
        temperature: 1.0,
        max_tokens: 4096,
        top_p: 1.0,
    },
});

async function loadSettings() {
    isLoading.value = true;
    await entityStore.fetchState();
    personality.value = { ...entityStore.personality };
    isLoading.value = false;
}

async function loadLlmConfigurations() {
    isLoadingLlm.value = true;
    try {
        const [configResponse, driversResponse] = await Promise.all([
            axios.get('/api/v1/llm/configurations'),
            axios.get('/api/v1/llm/drivers'),
        ]);
        llmConfigurations.value = configResponse.data.data;
        llmDrivers.value = driversResponse.data.data;
    } catch (error) {
        console.error('Failed to load LLM configurations:', error);
    }
    isLoadingLlm.value = false;
}

async function createConfiguration() {
    try {
        await axios.post('/api/v1/llm/configurations', newConfig.value);
        showAddModal.value = false;
        resetNewConfig();
        await loadLlmConfigurations();
    } catch (error) {
        console.error('Failed to create configuration:', error);
        alert('Failed to create configuration: ' + (error.response?.data?.message || error.message));
    }
}

async function updateConfiguration() {
    if (!editingConfig.value) return;
    try {
        await axios.put(`/api/v1/llm/configurations/${editingConfig.value.id}`, editingConfig.value);
        showEditModal.value = false;
        editingConfig.value = null;
        await loadLlmConfigurations();
    } catch (error) {
        console.error('Failed to update configuration:', error);
        alert('Failed to update configuration: ' + (error.response?.data?.message || error.message));
    }
}

async function deleteConfiguration(config) {
    if (!confirm(`Delete configuration "${config.name}"?`)) return;
    try {
        await axios.delete(`/api/v1/llm/configurations/${config.id}`);
        await loadLlmConfigurations();
    } catch (error) {
        console.error('Failed to delete configuration:', error);
        alert('Failed to delete configuration: ' + (error.response?.data?.message || error.message));
    }
}

async function testConfiguration(config) {
    isTesting.value = config.id;
    testResult.value = null;
    try {
        const response = await axios.post(`/api/v1/llm/configurations/${config.id}/test`);
        testResult.value = { configId: config.id, success: true, ...response.data.data };
    } catch (error) {
        testResult.value = {
            configId: config.id,
            success: false,
            error: error.response?.data?.error || error.message
        };
    }
    isTesting.value = null;
}

async function setAsDefault(config) {
    try {
        await axios.post(`/api/v1/llm/configurations/${config.id}/default`);
        await loadLlmConfigurations();
    } catch (error) {
        console.error('Failed to set default:', error);
    }
}

async function resetCircuitBreaker(config) {
    try {
        await axios.post(`/api/v1/llm/configurations/${config.id}/reset`);
        await loadLlmConfigurations();
    } catch (error) {
        console.error('Failed to reset circuit breaker:', error);
    }
}

function openEditModal(config) {
    editingConfig.value = { ...config, api_key: '' };
    if (!editingConfig.value.options) {
        editingConfig.value.options = { temperature: 1.0, max_tokens: 4096, top_p: 1.0 };
    }
    showEditModal.value = true;
}

function resetNewConfig() {
    newConfig.value = {
        name: '',
        driver: 'nvidia',
        model: '',
        api_key: '',
        base_url: '',
        is_active: true,
        is_default: false,
        priority: 50,
        options: {
            temperature: 1.0,
            max_tokens: 4096,
            top_p: 1.0,
        },
    };
}

const selectedDriverInfo = computed(() => {
    return llmDrivers.value[newConfig.value.driver] || {};
});

const editDriverInfo = computed(() => {
    return editingConfig.value ? (llmDrivers.value[editingConfig.value.driver] || {}) : {};
});

function getStatusColor(status) {
    const colors = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        error: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        disabled: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };
    return colors[status] || colors.ready;
}

function getDriverIcon(driver) {
    const icons = {
        ollama: '🦙',
        openai: '🤖',
        openrouter: '🌐',
        nvidia: '💚',
    };
    return icons[driver] || '🔧';
}

onMounted(() => {
    loadSettings();
    loadLlmConfigurations();
});
</script>

<template>
    <div class="p-8 bg-gray-50 dark:bg-gray-950 h-full overflow-y-auto transition-colors duration-200">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2 flex items-center gap-3 text-gray-900 dark:text-gray-100">
                    <svg class="w-8 h-8 text-entity-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{ t('settings') }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400">
                    {{ t('settingsDescription') }}
                </p>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="text-center py-12">
                <div class="animate-spin w-8 h-8 border-4 border-entity-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                <p class="text-gray-500 dark:text-gray-400">{{ t('loadingSettings') }}</p>
            </div>

            <div v-else class="space-y-6">
                <!-- LLM Configurations Card -->
                <div class="card">
                    <div class="card-header flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('llmConfigurations') }}</h2>
                        <button
                            @click="showAddModal = true"
                            class="px-4 py-2 bg-entity-600 text-white rounded-lg text-sm font-medium hover:bg-entity-700 transition-colors"
                        >
                            + {{ t('addConfiguration') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div v-if="isLoadingLlm" class="text-center py-8">
                            <div class="animate-spin w-6 h-6 border-4 border-entity-500 border-t-transparent rounded-full mx-auto"></div>
                        </div>
                        <div v-else-if="llmConfigurations.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            {{ t('noLlmConfigurationsYet') }} {{ t('createFirstConfiguration') }}
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="config in llmConfigurations"
                                :key="config.id"
                                class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-entity-300 dark:hover:border-entity-700 transition-colors"
                                :class="{ 'ring-2 ring-entity-500': config.is_default }"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">{{ getDriverIcon(config.driver) }}</span>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-medium text-gray-900 dark:text-gray-100">{{ config.name }}</h3>
                                                <span v-if="config.is_default" class="px-2 py-0.5 text-xs font-medium bg-entity-100 text-entity-800 dark:bg-entity-900 dark:text-entity-300 rounded">
                                                    {{ t('defaultConfiguration') }}
                                                </span>
                                                <span :class="getStatusColor(config.status)" class="px-2 py-0.5 text-xs font-medium rounded capitalize">
                                                    {{ config.status }}
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ config.driver }} / {{ config.model }}
                                            </p>
                                            <p v-if="config.last_used_at" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                Last used: {{ new Date(config.last_used_at).toLocaleString() }}
                                            </p>
                                            <p v-if="config.last_error" class="text-xs text-red-500 mt-1">
                                                Error: {{ config.last_error.substring(0, 100) }}...
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="testConfiguration(config)"
                                            :disabled="isTesting === config.id"
                                            class="p-2 text-gray-500 hover:text-entity-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                            :title="t('test')"
                                        >
                                            <span v-if="isTesting === config.id" class="animate-spin">⏳</span>
                                            <span v-else>🧪</span>
                                        </button>
                                        <button
                                            v-if="!config.is_default"
                                            @click="setAsDefault(config)"
                                            class="p-2 text-gray-500 hover:text-entity-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                            :title="t('setAsDefault')"
                                        >
                                            ⭐
                                        </button>
                                        <button
                                            v-if="config.status === 'error'"
                                            @click="resetCircuitBreaker(config)"
                                            class="p-2 text-gray-500 hover:text-green-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                            :title="t('resetCircuitBreaker')"
                                        >
                                            🔄
                                        </button>
                                        <button
                                            @click="openEditModal(config)"
                                            class="p-2 text-gray-500 hover:text-blue-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                            :title="t('edit')"
                                        >
                                            ✏️
                                        </button>
                                        <button
                                            @click="deleteConfiguration(config)"
                                            class="p-2 text-gray-500 hover:text-red-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                            :title="t('delete')"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                                <!-- Test Result -->
                                <div v-if="testResult && testResult.configId === config.id" class="mt-3 p-3 rounded-lg text-sm" :class="testResult.success ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                                    <p v-if="testResult.success" class="text-green-700 dark:text-green-300">
                                        ✅ Test successful ({{ testResult.duration_ms }}ms): "{{ testResult.response }}"
                                    </p>
                                    <p v-else class="text-red-700 dark:text-red-300">
                                        ❌ Test failed: {{ testResult.error }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appearance Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('themeSettings') }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ t('theme') }}</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('themeDescription') }}
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
                                    {{ t('light') }}
                                </button>
                                <button
                                    @click="settingsStore.setTheme('dark')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="settingsStore.theme === 'dark'
                                        ? 'bg-entity-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                                >
                                    {{ t('dark') }}
                                </button>
                                <button
                                    @click="settingsStore.setTheme('system')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="settingsStore.theme === 'system'
                                        ? 'bg-entity-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                                >
                                    {{ t('system') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Entity Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('entityInformation') }}</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('name') }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ personality.name || entityStore.name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('status') }}</span>
                            <span class="capitalize font-medium text-gray-900 dark:text-gray-100">{{ t(entityStore.status) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('activeLlm') }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ llmConfigurations.find(c => c.is_default)?.name || t('noActiveLlm') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Personality Traits Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('personalityTraits') }}</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div v-for="(value, trait) in personality.traits" :key="trait">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-gray-500 dark:text-gray-400">{{ t(trait) }}</span>
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
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('communicationStyle') }}</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <template v-for="(value, style) in personality.communication_style" :key="style">
                            <div v-if="typeof value === 'number'">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-gray-500 dark:text-gray-400">{{ t(style) }}</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ Math.round(value * 100) }}%</span>
                                </div>
                                <div class="progress-bar">
                                    <div
                                        class="h-full rounded-full bg-purple-500 transition-all"
                                        :style="{ width: (value * 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Core Values Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('coreValues') }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="value in personality.core_values"
                                :key="value"
                                class="badge badge-primary text-sm"
                            >
                                {{ value }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                            {{ t('coreValuesNote') }}
                        </p>
                    </div>
                </div>

                <!-- Self Description Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ t('selfDescription') }}</h2>
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

    <!-- Add Configuration Modal -->
    <div v-if="showAddModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ t('addLlmConfiguration') }}</h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('name') }}</label>
                    <input
                        v-model="newConfig.name"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        :placeholder="t('configNamePlaceholder')"
                    />
                </div>

                <!-- Driver -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('driver') }}</label>
                    <select
                        v-model="newConfig.driver"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                    >
                        <option v-for="(info, driver) in llmDrivers" :key="driver" :value="driver">
                            {{ info.name }} - {{ info.description }}
                        </option>
                    </select>
                </div>

                <!-- Model -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('model') }}</label>
                    <input
                        v-model="newConfig.model"
                        type="text"
                        list="popular-models"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        :placeholder="selectedDriverInfo.popular_models?.[0] || t('modelPlaceholder')"
                    />
                    <datalist id="popular-models">
                        <option v-for="model in selectedDriverInfo.popular_models" :key="model" :value="model" />
                    </datalist>
                    <p v-if="selectedDriverInfo.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ selectedDriverInfo.notes }}
                    </p>
                </div>

                <!-- API Key -->
                <div v-if="selectedDriverInfo.requires_api_key">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('apiKey') }}</label>
                    <input
                        v-model="newConfig.api_key"
                        type="password"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        :placeholder="t('apiKeyPlaceholder')"
                    />
                </div>

                <!-- Base URL -->
                <div v-if="selectedDriverInfo.requires_base_url">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('baseUrl') }}</label>
                    <input
                        v-model="newConfig.base_url"
                        type="url"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        :placeholder="selectedDriverInfo.default_base_url || 'https://...'"
                    />
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('priority') }} (0-100)</label>
                    <input
                        v-model.number="newConfig.priority"
                        type="number"
                        min="0"
                        max="100"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('priorityDescription') }}</p>
                </div>

                <!-- Options -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('temperature') }}</label>
                        <input
                            v-model.number="newConfig.options.temperature"
                            type="number"
                            step="0.1"
                            min="0"
                            max="2"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('maxTokens') }}</label>
                        <input
                            v-model.number="newConfig.options.max_tokens"
                            type="number"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('topP') }}</label>
                        <input
                            v-model.number="newConfig.options.top_p"
                            type="number"
                            step="0.1"
                            min="0"
                            max="1"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        />
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="newConfig.is_active" type="checkbox" class="w-4 h-4 text-entity-600 rounded focus:ring-entity-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('activeConfiguration') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="newConfig.is_default" type="checkbox" class="w-4 h-4 text-entity-600 rounded focus:ring-entity-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('setAsDefault') }}</span>
                    </label>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                <button
                    @click="showAddModal = false; resetNewConfig()"
                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                >
                    {{ t('cancel') }}
                </button>
                <button
                    @click="createConfiguration"
                    class="px-4 py-2 bg-entity-600 text-white rounded-lg hover:bg-entity-700 transition-colors"
                >
                    {{ t('create') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Configuration Modal -->
    <div v-if="showEditModal && editingConfig" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ t('editLlmConfiguration') }}</h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('name') }}</label>
                    <input
                        v-model="editingConfig.name"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                    />
                </div>

                <!-- Driver -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('driver') }}</label>
                    <select
                        v-model="editingConfig.driver"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                    >
                        <option v-for="(info, driver) in llmDrivers" :key="driver" :value="driver">
                            {{ info.name }}
                        </option>
                    </select>
                </div>

                <!-- Model -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('model') }}</label>
                    <input
                        v-model="editingConfig.model"
                        type="text"
                        list="edit-popular-models"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                    />
                    <datalist id="edit-popular-models">
                        <option v-for="model in editDriverInfo.popular_models" :key="model" :value="model" />
                    </datalist>
                </div>

                <!-- API Key -->
                <div v-if="editDriverInfo.requires_api_key">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('apiKey') }}</label>
                    <input
                        v-model="editingConfig.api_key"
                        type="password"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        :placeholder="t('apiKeyKeepCurrent')"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ editingConfig.has_api_key ? t('apiKeySetKeepCurrent') : t('noApiKeySet') }}
                    </p>
                </div>

                <!-- Base URL -->
                <div v-if="editDriverInfo.requires_base_url">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('baseUrl') }}</label>
                    <input
                        v-model="editingConfig.base_url"
                        type="url"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        :placeholder="editDriverInfo.default_base_url || 'https://...'"
                    />
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('priority') }} (0-100)</label>
                    <input
                        v-model.number="editingConfig.priority"
                        type="number"
                        min="0"
                        max="100"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                    />
                </div>

                <!-- Options -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('temperature') }}</label>
                        <input
                            v-model.number="editingConfig.options.temperature"
                            type="number"
                            step="0.1"
                            min="0"
                            max="2"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('maxTokens') }}</label>
                        <input
                            v-model.number="editingConfig.options.max_tokens"
                            type="number"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('topP') }}</label>
                        <input
                            v-model.number="editingConfig.options.top_p"
                            type="number"
                            step="0.1"
                            min="0"
                            max="1"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-entity-500 focus:border-entity-500"
                        />
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="editingConfig.is_active" type="checkbox" class="w-4 h-4 text-entity-600 rounded focus:ring-entity-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('activeConfiguration') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="editingConfig.is_default" type="checkbox" class="w-4 h-4 text-entity-600 rounded focus:ring-entity-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('setAsDefault') }}</span>
                    </label>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                <button
                    @click="showEditModal = false; editingConfig = null"
                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                >
                    {{ t('cancel') }}
                </button>
                <button
                    @click="updateConfiguration"
                    class="px-4 py-2 bg-entity-600 text-white rounded-lg hover:bg-entity-700 transition-colors"
                >
                    {{ t('saveChanges') }}
                </button>
            </div>
        </div>
    </div>
</template>
