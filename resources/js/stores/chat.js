import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useChatStore = defineStore('chat', () => {
    // State
    const messages = ref([]);
    const conversationId = ref(null);
    const isLoading = ref(false);
    const error = ref(null);
    const participantName = ref('Anonymous');
    const lastFailedMessageId = ref(null);

    // Actions
    async function sendMessage(content) {
        if (!content.trim()) return;

        // Optimistic UI update - Nachricht sofort anzeigen
        const tempId = Date.now();
        messages.value.push({
            id: tempId,
            role: 'human',
            content: content,
            created_at: new Date().toISOString(),
        });

        isLoading.value = true;
        error.value = null;
        lastFailedMessageId.value = null;

        try {
            const response = await axios.post('/api/v1/chat', {
                message: content,
                conversation_id: conversationId.value,
                participant_name: participantName.value,
            });

            conversationId.value = response.data.conversation_id;

            // Update temp message with real ID
            const tempMessage = messages.value.find(m => m.id === tempId);
            if (tempMessage) {
                tempMessage.id = response.data.user_message_id;
            }

            // Add response message
            const isError = response.data.error === true;
            messages.value.push({
                id: response.data.id || Date.now() + 1,
                role: isError ? 'system' : 'entity',
                content: response.data.message,
                thought_process: response.data.thought_process,
                created_at: response.data.created_at,
                isError: isError,
            });

            if (isError) {
                lastFailedMessageId.value = response.data.user_message_id;
                error.value = response.data.error_details || 'LLM nicht erreichbar';
            }

        } catch (err) {
            error.value = err.response?.data?.message || err.message;
            // Add system error message
            messages.value.push({
                id: Date.now() + 1,
                role: 'system',
                content: 'Ich kann gerade nicht antworten. Bitte prüfe die Modelanbindung.',
                created_at: new Date().toISOString(),
                isError: true,
            });
            // Find the user message we just added
            const userMsg = messages.value.find(m => m.id === tempId);
            if (userMsg) {
                lastFailedMessageId.value = userMsg.id;
            }
        } finally {
            isLoading.value = false;
        }
    }

    async function retryLastMessage() {
        if (!lastFailedMessageId.value) return;

        // Remove the error message
        messages.value = messages.value.filter(m => !m.isError);

        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/v1/chat/retry', {
                message_id: lastFailedMessageId.value,
            });

            conversationId.value = response.data.conversation_id;

            const isError = response.data.error === true;
            messages.value.push({
                id: response.data.id || Date.now() + 1,
                role: isError ? 'system' : 'entity',
                content: response.data.message,
                thought_process: response.data.thought_process,
                created_at: response.data.created_at,
                isError: isError,
            });

            if (!isError) {
                lastFailedMessageId.value = null;
            } else {
                error.value = response.data.error_details || 'LLM nicht erreichbar';
            }

        } catch (err) {
            error.value = err.response?.data?.message || err.message;
            messages.value.push({
                id: Date.now() + 1,
                role: 'system',
                content: 'Ich kann gerade nicht antworten. Bitte prüfe die Modelanbindung.',
                created_at: new Date().toISOString(),
                isError: true,
            });
        } finally {
            isLoading.value = false;
        }
    }

    async function loadHistory() {
        if (!conversationId.value) return;

        isLoading.value = true;

        try {
            const response = await axios.get('/api/v1/chat/history', {
                params: { conversation_id: conversationId.value },
            });

            if (response.data.conversation) {
                messages.value = response.data.conversation.messages || [];
            }

            error.value = null;
        } catch (err) {
            error.value = err.message;
        } finally {
            isLoading.value = false;
        }
    }

    function startNewConversation() {
        messages.value = [];
        conversationId.value = null;
        lastFailedMessageId.value = null;
        error.value = null;
    }

    function subscribeToMessages() {
        if (!window.Echo) return;

        window.Echo.channel('entity.chat')
            .listen('.message.received', (data) => {
                if (data.conversation_id === conversationId.value) {
                    const exists = messages.value.some(m => m.id === data.id);
                    if (!exists) {
                        messages.value.push(data);
                    }
                }
            });
    }

    return {
        // State
        messages,
        conversationId,
        isLoading,
        error,
        participantName,
        lastFailedMessageId,

        // Actions
        sendMessage,
        retryLastMessage,
        loadHistory,
        startNewConversation,
        subscribeToMessages,
    };
});
