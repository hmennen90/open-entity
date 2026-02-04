<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import { useChatStore } from '../stores/chat';
import { useEntityStore } from '../stores/entity';
import { useSettingsStore } from '../stores/settings';

const chatStore = useChatStore();
const entityStore = useEntityStore();
const settingsStore = useSettingsStore();

const t = (key, params = {}) => settingsStore.t(key, params);

// Computed to ensure name has a fallback
const entityName = computed(() => entityStore.name || 'OpenEntity');

const messageInput = ref('');
const messagesContainer = ref(null);

const canSend = computed(() => messageInput.value.trim() && !chatStore.isLoading);

async function sendMessage() {
    if (!canSend.value) return;

    const content = messageInput.value;
    messageInput.value = '';

    await chatStore.sendMessage(content);
    await scrollToBottom();
}

async function retryMessage() {
    await chatStore.retryLastMessage();
    await scrollToBottom();
}

async function scrollToBottom() {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

onMounted(() => {
    chatStore.subscribeToMessages();
});
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-gray-50 dark:bg-gray-950 transition-colors duration-200">
        <!-- Chat Header -->
        <div class="flex-shrink-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4 transition-colors duration-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-entity-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <span class="text-xl">{{ entityStore.moodEmoji }}</span>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Chat with {{ entityName }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ entityStore.isAwake ? 'Active' : 'Sleeping' }}
                        </p>
                    </div>
                </div>
                <button
                    @click="chatStore.startNewConversation"
                    class="btn btn-secondary text-sm"
                >
                    {{ t('newConversation') }}
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div
            ref="messagesContainer"
            class="flex-1 min-h-0 overflow-y-auto p-6 space-y-4 scroll-smooth"
        >
            <!-- Empty State -->
            <div v-if="chatStore.messages.length === 0" class="text-center py-12">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                    <span class="text-4xl">{{ entityStore.moodEmoji }}</span>
                </div>
                <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-gray-100">{{ t('startConversation') }}</h3>
                <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                    {{ t('chatIntro', { name: entityName }) }}
                </p>
            </div>

            <!-- Messages -->
            <div
                v-for="message in chatStore.messages"
                :key="message.id"
                class="flex"
                :class="{
                    'justify-start': message.role === 'entity' || message.role === 'system',
                    'justify-end': message.role === 'human',
                    'justify-center': message.isError
                }"
            >
                <!-- System Error Message -->
                <div
                    v-if="message.isError"
                    class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-4 py-3 max-w-md"
                >
                    <div class="flex items-center gap-2 text-red-700 dark:text-red-400">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-sm font-medium">{{ message.content }}</p>
                    </div>
                    <p class="text-xs mt-1 text-red-500 dark:text-red-500">
                        {{ formatTime(message.created_at) }}
                    </p>
                </div>

                <!-- Normal Message (Entity/Human) -->
                <div
                    v-else
                    class="chat-message"
                    :class="message.role === 'entity' ? 'chat-message-entity' : 'chat-message-human'"
                >
                    <p class="whitespace-pre-wrap">{{ message.content }}</p>
                    <p class="text-xs mt-1 opacity-60">
                        {{ formatTime(message.created_at) }}
                    </p>.
                </div>
            </div>

            <!-- Retry Button -->
            <div v-if="chatStore.lastFailedMessageId && !chatStore.isLoading" class="flex justify-center">
                <button
                    @click="retryMessage"
                    class="flex items-center gap-2 px-4 py-2 bg-entity-500 hover:bg-entity-600 text-white rounded-lg transition-colors text-sm font-medium"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ t('retry') }}
                </button>
            </div>

            <!-- Typing Indicator -->
            <div v-if="chatStore.isLoading" class="flex justify-start">
                <div class="chat-message chat-message-entity">
                    <div class="flex gap-1.5 py-1">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="flex-shrink-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 p-4 transition-colors duration-200">
            <form @submit.prevent="sendMessage" class="flex gap-3">
                <input
                    v-model="messageInput"
                    type="text"
                    class="input flex-1"
                    :placeholder="t('typeMessage')"
                    :disabled="chatStore.isLoading"
                />
                <button
                    type="submit"
                    class="btn btn-primary px-6"
                    :disabled="!canSend"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</template>
