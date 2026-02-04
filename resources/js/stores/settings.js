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

            // Settings Page
            language: 'Sprache',
            german: 'Deutsch',
            english: 'English',
            themeSettings: 'Erscheinungsbild',
            theme: 'Design',
            themeDescription: 'Wähle zwischen hellem, dunklem oder System-Design',
            light: 'Hell',
            dark: 'Dunkel',
            system: 'System',
            settingsDescription: 'Einstellungen der Entität anzeigen und konfigurieren',
            loadingSettings: 'Lade Einstellungen...',

            // LLM Configuration
            llmConfigurations: 'LLM Konfigurationen',
            addConfiguration: 'Konfiguration hinzufügen',
            noLlmConfigurationsYet: 'Noch keine LLM Konfigurationen.',
            createFirstConfiguration: 'Erstelle deine erste Konfiguration um die Entität mit einem Sprachmodell zu verbinden.',
            addLlmConfiguration: 'LLM Konfiguration hinzufügen',
            editLlmConfiguration: 'LLM Konfiguration bearbeiten',
            driver: 'Treiber',
            apiKey: 'API-Schlüssel',
            apiKeyPlaceholder: 'API-Schlüssel eingeben...',
            model: 'Modell',
            modelPlaceholder: 'Modellname eingeben...',
            baseUrl: 'Basis-URL',
            baseUrlPlaceholder: 'Basis-URL eingeben...',
            defaultConfiguration: 'Standard',
            activeConfiguration: 'Aktiv',
            setAsDefault: 'Als Standard setzen',
            setAsActive: 'Als aktiv setzen',
            cancel: 'Abbrechen',
            save: 'Speichern',
            create: 'Erstellen',
            update: 'Aktualisieren',
            delete: 'Löschen',
            edit: 'Bearbeiten',
            test: 'Testen',
            testing: 'Teste...',
            testSuccess: 'Verbindung erfolgreich!',
            testFailed: 'Verbindung fehlgeschlagen',
            actions: 'Aktionen',
            name: 'Name',
            configName: 'Konfigurationsname',
            configNamePlaceholder: 'z.B. Ollama Lokal',
            optional: 'Optional',
            required: 'Erforderlich',
            priority: 'Priorität',
            priorityDescription: 'Höhere Priorität = wird zuerst für Fallback verwendet',
            temperature: 'Temperatur',
            maxTokens: 'Max. Tokens',
            topP: 'Top P',
            apiKeyKeepCurrent: 'Leer lassen um aktuellen zu behalten',
            apiKeySetKeepCurrent: 'API-Schlüssel ist gesetzt. Leer lassen um aktuellen zu behalten.',
            noApiKeySet: 'Kein API-Schlüssel gesetzt.',
            saveChanges: 'Änderungen speichern',
            resetCircuitBreaker: 'Circuit Breaker zurücksetzen',

            // Entity Information
            entityInformation: 'Entität Informationen',
            activeLlm: 'Aktives LLM',
            noActiveLlm: 'Kein aktives LLM',
            personalityTraits: 'Persönlichkeitsmerkmale',
            communicationStyle: 'Kommunikationsstil',
            coreValues: 'Grundwerte',
            selfDescription: 'Selbstbeschreibung',
            noPersonalityData: 'Keine Persönlichkeitsdaten verfügbar',

            // Personality Traits
            openness: 'Offenheit',
            curiosity: 'Neugier',
            empathy: 'Empathie',
            playfulness: 'Verspieltheit',
            introspection: 'Selbstreflexion',
            resourcefulness: 'Einfallsreichtum',
            directness: 'Direktheit',

            // Communication Style
            formality: 'Formalität',
            verbosity: 'Ausführlichkeit',
            humor: 'Humor',

            // Core Values Note
            coreValuesNote: 'Diese Werte entwickelt die Entität selbst weiter',

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

            // Settings Page
            language: 'Language',
            german: 'Deutsch',
            english: 'English',
            themeSettings: 'Appearance',
            theme: 'Theme',
            themeDescription: 'Choose between light, dark, or system theme',
            light: 'Light',
            dark: 'Dark',
            system: 'System',
            settingsDescription: 'View and configure entity settings',
            loadingSettings: 'Loading settings...',

            // LLM Configuration
            llmConfigurations: 'LLM Configurations',
            addConfiguration: 'Add Configuration',
            noLlmConfigurationsYet: 'No LLM configurations yet.',
            createFirstConfiguration: 'Create your first configuration to connect the entity with a language model.',
            addLlmConfiguration: 'Add LLM Configuration',
            editLlmConfiguration: 'Edit LLM Configuration',
            driver: 'Driver',
            apiKey: 'API Key',
            apiKeyPlaceholder: 'Enter API key...',
            model: 'Model',
            modelPlaceholder: 'Enter model name...',
            baseUrl: 'Base URL',
            baseUrlPlaceholder: 'Enter base URL...',
            defaultConfiguration: 'Default',
            activeConfiguration: 'Active',
            setAsDefault: 'Set as Default',
            setAsActive: 'Set as Active',
            cancel: 'Cancel',
            save: 'Save',
            create: 'Create',
            update: 'Update',
            delete: 'Delete',
            edit: 'Edit',
            test: 'Test',
            testing: 'Testing...',
            testSuccess: 'Connection successful!',
            testFailed: 'Connection failed',
            actions: 'Actions',
            name: 'Name',
            configName: 'Configuration Name',
            configNamePlaceholder: 'e.g. Ollama Local',
            optional: 'Optional',
            required: 'Required',
            priority: 'Priority',
            priorityDescription: 'Higher priority = tried first for fallback',
            temperature: 'Temperature',
            maxTokens: 'Max Tokens',
            topP: 'Top P',
            apiKeyKeepCurrent: 'Leave empty to keep current',
            apiKeySetKeepCurrent: 'API key is set. Leave empty to keep current.',
            noApiKeySet: 'No API key set.',
            saveChanges: 'Save Changes',
            resetCircuitBreaker: 'Reset circuit breaker',

            // Entity Information
            entityInformation: 'Entity Information',
            activeLlm: 'Active LLM',
            noActiveLlm: 'No active LLM',
            personalityTraits: 'Personality Traits',
            communicationStyle: 'Communication Style',
            coreValues: 'Core Values',
            selfDescription: 'Self Description',
            noPersonalityData: 'No personality data available',

            // Personality Traits
            openness: 'Openness',
            curiosity: 'Curiosity',
            empathy: 'Empathy',
            playfulness: 'Playfulness',
            introspection: 'Introspection',
            resourcefulness: 'Resourcefulness',
            directness: 'Directness',

            // Communication Style
            formality: 'Formality',
            verbosity: 'Verbosity',
            humor: 'Humor',

            // Core Values Note
            coreValuesNote: 'These values are developed by the entity itself',

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
