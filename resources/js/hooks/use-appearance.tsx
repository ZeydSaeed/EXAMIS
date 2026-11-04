export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

// The app is locked to the light theme so every device shows the same
// colours, whatever the operating system's dark-mode setting is.
const applyLightTheme = (): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Clear any preference saved before the theme was locked.
    localStorage.removeItem('appearance');
    document.cookie = 'appearance=;path=/;max-age=0;SameSite=Lax';

    applyLightTheme();
}

export function useAppearance(): UseAppearanceReturn {
    return {
        appearance: 'light',
        resolvedAppearance: 'light',
        updateAppearance: applyLightTheme,
    } as const;
}
