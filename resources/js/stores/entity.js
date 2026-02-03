import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useEntityStore = defineStore('entity', () => {
    // State
    const name = ref('Nova');
    const status = ref('sleeping');
    const uptime = ref(null);
    const lastThoughtAt = ref(null);
    const personality = ref({});
    const currentMood = ref({});
    const activeGoals = ref([]);
    const recentThoughts = ref([]);
    const isLoading = ref(false);
    const error = ref(null);
    const notifications = ref([]);
    const pendingQuestion = ref(null);

    // Getters
    const isAwake = computed(() => status.value === 'awake');

    const moodEmoji = computed(() => {
        const state = currentMood.value.state;
        return {
            curious: '🤔',
            contemplative: '💭',
            emotional: '💗',
            determined: '💪',
            observant: '👀',
            neutral: '😐',
        }[state] || '🌟';
    });

    // Actions
    async function fetchStatus() {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/v1/entity');
            name.value = response.data.name;
            status.value = response.data.status;
            uptime.value = response.data.uptime;
            lastThoughtAt.value = response.data.last_thought_at;
            error.value = null;
        } catch (err) {
            error.value = err.message;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchState() {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/v1/entity/state');
            personality.value = response.data.personality;
            currentMood.value = response.data.current_mood;
            activeGoals.value = response.data.active_goals;
            recentThoughts.value = response.data.recent_thoughts;
            error.value = null;
        } catch (err) {
            error.value = err.message;
        } finally {
            isLoading.value = false;
        }
    }

    async function wake() {
        try {
            await axios.post('/api/v1/entity/wake');
            status.value = 'awake';
        } catch (err) {
            error.value = err.message;
        }
    }

    async function sleep() {
        try {
            await axios.post('/api/v1/entity/sleep');
            status.value = 'sleeping';
        } catch (err) {
            error.value = err.message;
        }
    }

    function addThought(thought) {
        recentThoughts.value.unshift(thought);
        if (recentThoughts.value.length > 50) {
            recentThoughts.value.pop();
        }
    }

    function addNotification(notification) {
        const id = Date.now();
        notifications.value.push({
            id,
            ...notification,
            read: false,
            createdAt: new Date().toISOString(),
        });

        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            dismissNotification(id);
        }, 10000);
    }

    function dismissNotification(id) {
        const index = notifications.value.findIndex(n => n.id === id);
        if (index > -1) {
            notifications.value.splice(index, 1);
        }
    }

    function handleEntityQuestion(data) {
        pendingQuestion.value = data;
        addNotification({
            type: 'question',
            title: `${name.value} wants to ask you something`,
            message: data.question,
            thoughtId: data.thought_id,
        });
    }

    function clearPendingQuestion() {
        pendingQuestion.value = null;
    }

    function subscribeToUpdates() {
        if (!window.Echo) return;

        // Status Updates
        window.Echo.channel('entity.status')
            .listen('.status.changed', (data) => {
                status.value = data.status;
            });

        // Thought Updates
        window.Echo.channel('entity.mind')
            .listen('.thought.occurred', (data) => {
                addThought(data);
            });

        // Notification Updates (entity questions)
        window.Echo.channel('entity.notifications')
            .listen('.entity.question', (data) => {
                handleEntityQuestion(data);
            });
    }

    return {
        // State
        name,
        status,
        uptime,
        lastThoughtAt,
        personality,
        currentMood,
        activeGoals,
        recentThoughts,
        isLoading,
        error,
        notifications,
        pendingQuestion,

        // Getters
        isAwake,
        moodEmoji,

        // Actions
        fetchStatus,
        fetchState,
        wake,
        sleep,
        addThought,
        addNotification,
        dismissNotification,
        handleEntityQuestion,
        clearPendingQuestion,
        subscribeToUpdates,
    };
});
