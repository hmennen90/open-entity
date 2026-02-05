import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useEntityStore = defineStore('entity', () => {
    // State
    const name = ref('Entity');
    const status = ref('sleeping');
    const uptime = ref(null);
    const lastThoughtAt = ref(null);
    const personality = ref({});
    const currentMood = ref({});
    const energy = ref({ level: 0.5, percent: 50, state: 'normal', hours_awake: 0, needs_sleep: false });
    const activeGoals = ref([]);
    const recentThoughts = ref([]);
    const isLoading = ref(false);
    const error = ref(null);
    const notifications = ref([]);
    const pendingQuestion = ref(null);
    const updateInfo = ref(null);

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
            const response = await axios.get('/api/v1/entity/status');
            name.value = response.data.name || 'Entity';
            status.value = response.data.status;
            uptime.value = response.data.uptime;
            lastThoughtAt.value = response.data.last_thought_at;
            if (response.data.energy) {
                energy.value = response.data.energy;
                // Also update mood energy for consistency
                currentMood.value = {
                    ...currentMood.value,
                    energy: response.data.energy.level,
                    energy_state: response.data.energy.state,
                };
            }
            error.value = null;
        } catch (err) {
            error.value = err.message;
            console.error('Failed to fetch entity status:', err);
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchState() {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/v1/entity/state');
            // Also update name and status if provided
            if (response.data.name) {
                name.value = response.data.name;
            }
            if (response.data.status) {
                status.value = response.data.status;
            }
            personality.value = response.data.personality || {};
            currentMood.value = response.data.current_mood || {};
            activeGoals.value = response.data.active_goals || [];
            recentThoughts.value = response.data.recent_thoughts || [];
            error.value = null;
        } catch (err) {
            error.value = err.message;
            console.error('Failed to fetch entity state:', err);
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

        // Auto-dismiss after 10 seconds (unless persistent)
        if (!notification.persistent) {
            setTimeout(() => {
                dismissNotification(id);
            }, 10000);
        }
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

    function handleUpdateAvailable(data) {
        updateInfo.value = {
            currentVersion: data.current_version,
            latestVersion: data.latest_version,
            releaseUrl: data.release_url,
            changelog: data.changelog,
        };
        addNotification({
            type: 'update',
            title: 'Update Available',
            message: `Version ${data.latest_version} is available (current: ${data.current_version})`,
            releaseUrl: data.release_url,
            persistent: true, // Don't auto-dismiss update notifications
        });
    }

    function dismissUpdate() {
        updateInfo.value = null;
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

        // Notification Updates (entity questions and updates)
        window.Echo.channel('entity.notifications')
            .listen('.entity.question', (data) => {
                handleEntityQuestion(data);
            })
            .listen('.update.available', (data) => {
                handleUpdateAvailable(data);
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
        energy,
        activeGoals,
        recentThoughts,
        isLoading,
        error,
        notifications,
        pendingQuestion,
        updateInfo,

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
        handleUpdateAvailable,
        dismissUpdate,
        subscribeToUpdates,
    };
});
