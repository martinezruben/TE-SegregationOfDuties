import { useCallback, useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'system';

const KEY = 'theme';

export function readTheme(): Theme {
    try {
        const v = localStorage.getItem(KEY);
        return v === 'light' || v === 'dark' ? v : 'system';
    } catch {
        return 'system';
    }
}

/** Aplica la clase `dark` en <html> según la preferencia (o el sistema operativo). */
export function applyTheme(theme: Theme): boolean {
    const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    return dark;
}

export function useTheme() {
    const [theme, setThemeState] = useState<Theme>(readTheme);
    const [dark, setDark] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

    const setTheme = useCallback((t: Theme) => {
        try {
            if (t === 'system') localStorage.removeItem(KEY);
            else localStorage.setItem(KEY, t);
        } catch {
            // sin almacenamiento: solo aplica en esta sesión
        }
        setThemeState(t);
        setDark(applyTheme(t));
    }, []);

    // Si la preferencia es "sistema", sigue los cambios del sistema operativo.
    useEffect(() => {
        if (theme !== 'system') return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => setDark(applyTheme('system'));
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [theme]);

    return { theme, dark, setTheme };
}
