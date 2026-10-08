import { create } from 'zustand';

export type ThemeMode = 'light' | 'dark' | 'system';

interface ThemeState {
    theme: ThemeMode;
    setTheme: (theme: ThemeMode) => void;
}

const applyThemeClasses = (mode: ThemeMode) => {
    if (typeof globalThis === 'undefined' || !document?.documentElement) return;

    const root = document.documentElement;
    root.removeAttribute('data-theme');

    let isDark = false;
    if (mode === 'system') {
        isDark = globalThis.matchMedia('(prefers-color-scheme: dark)').matches;
    } else {
        isDark = mode === 'dark';
    }

    if (isDark) {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
};

const getInitialTheme = (): ThemeMode => {
    if (typeof globalThis !== 'undefined') {
        const saved = localStorage.getItem('cop_theme') as ThemeMode;
        if (saved === 'dark' || saved === 'light' || saved === 'system') {
            return saved;
        }
    }
    return 'light';
};

export const useThemeStore = create<ThemeState>((set) => {
    if (typeof globalThis !== 'undefined') {
        globalThis.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const saved = localStorage.getItem('cop_theme') as ThemeMode;
            if (saved === 'system') {
                applyThemeClasses('system');
            }
        });
    }

    return {
        theme: getInitialTheme(),
        setTheme: (theme) => {
            if (typeof globalThis !== 'undefined') {
                localStorage.setItem('cop_theme', theme);
                applyThemeClasses(theme);
            }
            set({ theme });
        },
    };
});

if (typeof globalThis !== 'undefined') {
    applyThemeClasses(getInitialTheme());
}

export default useThemeStore;
