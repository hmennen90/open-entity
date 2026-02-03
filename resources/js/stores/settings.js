import { defineStore } from 'pinia';
import { ref, watch } from 'vue';

export const useSettingsStore = defineStore('settings', () => {
    // Theme state
    const theme = ref(localStorage.getItem('theme') || 'dark');
    const systemTheme = ref(getSystemTheme());

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
    }

    return {
        theme,
        systemTheme,
        getEffectiveTheme,
        setTheme,
        toggleTheme,
        cycleTheme,
        applyTheme,
        init,
    };
});
