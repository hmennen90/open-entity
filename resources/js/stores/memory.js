import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useMemoryStore = defineStore('memory', () => {
    // State
    const memories = ref([]);
    const selectedMemory = ref(null);
    const isLoading = ref(false);
    const error = ref(null);
    const searchQuery = ref('');
    const activeFilter = ref('all');
    const pagination = ref({
        currentPage: 1,
        perPage: 20,
        total: 0,
        lastPage: 1,
    });

    // Memory types with colors and icons
    const memoryTypes = {
        experience: { label: 'Experience', color: 'blue', icon: 'star' },
        conversation: { label: 'Conversation', color: 'green', icon: 'chat' },
        learned: { label: 'Learned', color: 'purple', icon: 'book' },
        social: { label: 'Social', color: 'yellow', icon: 'users' },
        reflection: { label: 'Reflection', color: 'indigo', icon: 'lightbulb' },
    };

    // Computed
    const filteredMemories = computed(() => {
        let result = memories.value;

        // Filter by type
        if (activeFilter.value !== 'all') {
            result = result.filter(m => m.type === activeFilter.value);
        }

        // Filter by search query (client-side for instant feedback)
        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase();
            result = result.filter(m =>
                m.content?.toLowerCase().includes(query) ||
                m.summary?.toLowerCase().includes(query) ||
                m.related_entity?.toLowerCase().includes(query)
            );
        }

        return result;
    });

    const hasMorePages = computed(() => pagination.value.currentPage < pagination.value.lastPage);

    const totalMemories = computed(() => pagination.value.total);

    const importantMemories = computed(() =>
        memories.value.filter(m => m.importance >= 0.7).slice(0, 10)
    );

    const recentMemories = computed(() =>
        [...memories.value]
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 10)
    );

    // Actions
    async function fetchMemories(options = {}) {
        const { page = 1, type = null, search = null, append = false } = options;

        isLoading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            params.append('page', page);
            params.append('per_page', pagination.value.perPage);

            if (type && type !== 'all') {
                params.append('type', type);
            }

            if (search) {
                params.append('search', search);
            }

            const response = await axios.get(`/api/v1/memory?${params.toString()}`);

            if (append) {
                memories.value = [...memories.value, ...response.data.data];
            } else {
                memories.value = response.data.data || response.data;
            }

            // Update pagination if available
            if (response.data.meta) {
                pagination.value = {
                    currentPage: response.data.meta.current_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                    lastPage: response.data.meta.last_page,
                };
            }

            return memories.value;
        } catch (err) {
            console.error('Failed to fetch memories:', err);
            error.value = err.response?.data?.message || 'Failed to load memories';
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function searchMemories(query) {
        searchQuery.value = query;

        if (!query.trim()) {
            return fetchMemories({ type: activeFilter.value });
        }

        return fetchMemories({
            search: query,
            type: activeFilter.value !== 'all' ? activeFilter.value : null,
        });
    }

    async function fetchMemoryById(id) {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/v1/memory/${id}`);
            selectedMemory.value = response.data.data || response.data;
            return selectedMemory.value;
        } catch (err) {
            console.error('Failed to fetch memory:', err);
            error.value = err.response?.data?.message || 'Failed to load memory';
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function loadMore() {
        if (!hasMorePages.value || isLoading.value) return;

        return fetchMemories({
            page: pagination.value.currentPage + 1,
            type: activeFilter.value !== 'all' ? activeFilter.value : null,
            search: searchQuery.value || null,
            append: true,
        });
    }

    function setFilter(filter) {
        activeFilter.value = filter;
        pagination.value.currentPage = 1;
        fetchMemories({
            type: filter !== 'all' ? filter : null,
            search: searchQuery.value || null,
        });
    }

    function selectMemory(memory) {
        selectedMemory.value = memory;
    }

    function clearSelection() {
        selectedMemory.value = null;
    }

    function clearSearch() {
        searchQuery.value = '';
        fetchMemories({ type: activeFilter.value !== 'all' ? activeFilter.value : null });
    }

    // Get related memories based on content similarity or entity
    async function fetchRelatedMemories(memoryId) {
        try {
            const response = await axios.get(`/api/v1/memory/${memoryId}/related`);
            return response.data.data || response.data;
        } catch (err) {
            console.error('Failed to fetch related memories:', err);
            return [];
        }
    }

    // Get memory statistics
    async function fetchStatistics() {
        try {
            const response = await axios.get('/api/v1/memory/statistics');
            return response.data;
        } catch (err) {
            console.error('Failed to fetch memory statistics:', err);
            return null;
        }
    }

    return {
        // State
        memories,
        selectedMemory,
        isLoading,
        error,
        searchQuery,
        activeFilter,
        pagination,
        memoryTypes,

        // Computed
        filteredMemories,
        hasMorePages,
        totalMemories,
        importantMemories,
        recentMemories,

        // Actions
        fetchMemories,
        searchMemories,
        fetchMemoryById,
        loadMore,
        setFilter,
        selectMemory,
        clearSelection,
        clearSearch,
        fetchRelatedMemories,
        fetchStatistics,
    };
});
