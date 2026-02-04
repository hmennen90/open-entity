import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useSettingsStore = defineStore('settings', () => {
    // Theme state
    const theme = ref(localStorage.getItem('theme') || 'dark');
    const systemTheme = ref(getSystemTheme());

    // Language state
    const language = ref(localStorage.getItem('language') || 'de');

    // Translations
    const translations = {
        de: {
            // Navigation
            home: 'Start',
            chat: 'Chat',
            mind: 'Gedanken',
            memory: 'Erinnerungen',
            goals: 'Ziele',
            settings: 'Einstellungen',

            // Status
            awake: 'Wach',
            sleeping: 'Schlafend',
            active: 'Aktiv',

            // Wake/Sleep buttons
            wakeUp: 'Aufwecken',
            goToSleep: 'Schlafen legen',

            // Status Bar
            status: 'Status',
            uptime: 'Laufzeit',
            mood: 'Stimmung',
            energy: 'Energie',

            // Home Page
            welcomeToOpenEntity: 'Willkommen bei {name}',
            observeConsciousness: 'Beobachte {name}s Bewusstsein in Echtzeit.',
            currentMood: 'Aktuelle Stimmung',
            neutral: 'Neutral',
            activeGoals: 'Aktive Ziele',
            recentThoughts: 'Letzte Gedanken',
            noThoughtsYet: 'Noch keine Gedanken. Wecke die Entität um mit dem Denken zu beginnen.',

            // Chat
            sendMessage: 'Nachricht senden',
            typeMessage: 'Schreibe etwas...',
            newConversation: 'Neues Gespräch',
            startConversation: 'Starte ein Gespräch',
            chatIntro: 'Sag hallo zu {name}. Das ist kein Chatbot - es ist eine Entität mit eigenen Gedanken, Interessen und Persönlichkeit.',
            retry: 'Erneut versuchen',

            // Mind Viewer
            mindViewer: 'Gedanken-Viewer',
            watchThoughts: 'Beobachte {name}s Gedanken in Echtzeit',
            live: 'Live',
            noThoughts: 'Noch keine Gedanken',
            waitingThought: 'Warte auf den nächsten Denk-Zyklus...',
            wakeEntity: 'Wecke die Entität um zu denken.',
            loadingThoughts: 'Lade Gedanken...',

            // Thought types
            all: 'Alle',
            observation: 'Beobachtung',
            observations: 'Beobachtungen',
            reflection: 'Reflexion',
            reflections: 'Reflexionen',
            curiosity: 'Neugier',
            curiosities: 'Neugier',
            emotion: 'Emotion',
            emotions: 'Emotionen',
            decision: 'Entscheidung',
            decisions: 'Entscheidungen',
            thought: 'Gedanke',

            // Actions
            action: 'Aktion',
            intensity: 'Intensität',
            success: 'Erfolgreich',
            failed: 'Fehlgeschlagen',
            result: 'Ergebnis',

            // Settings
            language: 'Sprache',
            german: 'Deutsch',
            english: 'English',
            themeSettings: 'Erscheinungsbild',
            light: 'Hell',
            dark: 'Dunkel',
            system: 'System',

            // Time
            justNow: 'gerade eben',
            minutesAgo: 'vor {n} Min.',
            hoursAgo: 'vor {n} Std.',
        },
        en: {
            // Navigation
            home: 'Home',
            chat: 'Chat',
            mind: 'Mind',
            memory: 'Memory',
            goals: 'Goals',
            settings: 'Settings',

            // Status
            awake: 'Awake',
            sleeping: 'Sleeping',
            active: 'Active',

            // Wake/Sleep buttons
            wakeUp: 'Wake Up',
            goToSleep: 'Go to Sleep',

            // Status Bar
            status: 'Status',
            uptime: 'Uptime',
            mood: 'Mood',
            energy: 'Energy',

            // Home Page
            welcomeToOpenEntity: 'Welcome to {name}',
            observeConsciousness: 'Observe {name}\'s consciousness in real-time.',
            currentMood: 'Current Mood',
            neutral: 'Neutral',
            activeGoals: 'Active Goals',
            recentThoughts: 'Recent Thoughts',
            noThoughtsYet: 'No thoughts yet. Wake up the entity to start thinking.',

            // Chat
            sendMessage: 'Send message',
            typeMessage: 'Say something...',
            newConversation: 'New Conversation',
            startConversation: 'Start a Conversation',
            chatIntro: 'Say hello to {name}. This isn\'t just a chatbot - it\'s an entity with its own thoughts, interests, and personality.',
            retry: 'Retry',

            // Mind Viewer
            mindViewer: 'Mind Viewer',
            watchThoughts: 'Watch {name}\'s thoughts in real-time',
            live: 'Live',
            noThoughts: 'No thoughts yet',
            waitingThought: 'Waiting for the next thought cycle...',
            wakeEntity: 'Wake up the entity to start thinking.',
            loadingThoughts: 'Loading thoughts...',

            // Thought types
            all: 'All',
            observation: 'Observation',
            observations: 'Observations',
            reflection: 'Reflection',
            reflections: 'Reflections',
            curiosity: 'Curiosity',
            curiosities: 'Curiosities',
            emotion: 'Emotion',
            emotions: 'Emotions',
            decision: 'Decision',
            decisions: 'Decisions',
            thought: 'Thought',

            // Actions
            action: 'Action',
            intensity: 'Intensity',
            success: 'Success',
            failed: 'Failed',
            result: 'Result',

            // Settings
            language: 'Language',
            german: 'Deutsch',
            english: 'English',
            themeSettings: 'Appearance',
            light: 'Light',
            dark: 'Dark',
            system: 'System',

            // Time
            justNow: 'just now',
            minutesAgo: '{n}m ago',
            hoursAgo: '{n}h ago',
        }
    };

    // Translation function
    function t(key, params = {}) {
        let text = translations[language.value]?.[key] || translations['en']?.[key] || key;
        // Replace placeholders like {name}, {n}
        Object.keys(params).forEach(param => {
            text = text.replace(`{${param}}`, params[param]);
        });
        return text;
    }

    // Set language and persist to backend
    async function setLanguage(newLang) {
        language.value = newLang;
        localStorage.setItem('language', newLang);

        // Update USER.md on the backend
        try {
            await axios.post('/api/v1/settings/language', { language: newLang });
        } catch (error) {
            console.error('Failed to save language preference:', error);
        }
    }

    // Load language from backend on init
    async function loadLanguageFromBackend() {
        try {
            const response = await axios.get('/api/v1/settings/language');
            if (response.data.language) {
                language.value = response.data.language;
                localStorage.setItem('language', response.data.language);
            }
        } catch (error) {
            // Use localStorage fallback
            console.log('Using localStorage language preference');
        }
    }

    // Computed effective theme (respects system preference if set to 'system')
    function getEffectiveTheme() {
        if (theme.value === 'system') {
            return systemTheme.value;
        }
        return theme.value;
    }

    // Get system color scheme preference
    function getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    // Apply theme to document
    function applyTheme() {
        const effectiveTheme = getEffectiveTheme();
        const root = document.documentElement;

        if (effectiveTheme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }

        // Store preference
        localStorage.setItem('theme', theme.value);
    }

    // Set theme
    function setTheme(newTheme) {
        theme.value = newTheme;
        applyTheme();
    }

    // Toggle between light and dark
    function toggleTheme() {
        if (theme.value === 'dark') {
            setTheme('light');
        } else if (theme.value === 'light') {
            setTheme('dark');
        } else {
            // If system, toggle to opposite of current system theme
            setTheme(systemTheme.value === 'dark' ? 'light' : 'dark');
        }
    }

    // Cycle through themes: light -> dark -> system
    function cycleTheme() {
        const themes = ['light', 'dark', 'system'];
        const currentIndex = themes.indexOf(theme.value);
        const nextIndex = (currentIndex + 1) % themes.length;
        setTheme(themes[nextIndex]);
    }

    // Listen for system theme changes
    function initSystemThemeListener() {
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                systemTheme.value = e.matches ? 'dark' : 'light';
                if (theme.value === 'system') {
                    applyTheme();
                }
            });
        }
    }

    // Initialize on store creation
    function init() {
        initSystemThemeListener();
        applyTheme();
        loadLanguageFromBackend();
    }

    return {
        // Theme
        theme,
        systemTheme,
        getEffectiveTheme,
        setTheme,
        toggleTheme,
        cycleTheme,
        applyTheme,

        // Language
        language,
        t,
        setLanguage,
        loadLanguageFromBackend,

        // Init
        init,
    };
});
