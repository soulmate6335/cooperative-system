import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'

import type { ThemeMode } from './theme'

export const THEME_STORAGE_KEY = 'cooperative-theme'

const DARK_CLASS = 'cs-theme-dark'

interface ThemeModeContextValue {
  mode: ThemeMode
  toggleTheme: () => void
  setTheme: (mode: ThemeMode) => void
}

const ThemeModeContext = createContext<ThemeModeContextValue | null>(null)

function readStoredMode(): ThemeMode | null {
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY)
    if (stored === 'light' || stored === 'dark') {
      return stored
    }
  } catch {
    // localStorage unavailable — fall back to the system preference
  }
  return null
}

function systemPrefersDark(): boolean {
  return typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches
}

function getInitialMode(): ThemeMode {
  return readStoredMode() ?? (systemPrefersDark() ? 'dark' : 'light')
}

function applyDocumentClass(mode: ThemeMode): void {
  document.documentElement.classList.toggle(DARK_CLASS, mode === 'dark')
}

export function ThemeModeProvider({ children }: { children: ReactNode }): ReactNode {
  const [mode, setModeState] = useState<ThemeMode>(getInitialMode)
  const [explicit, setExplicit] = useState<boolean>(() => readStoredMode() !== null)

  // Keep the document-level class in sync so global CSS (color-scheme, stray
  // native surfaces) matches the active theme without waiting for a render.
  useEffect(() => {
    applyDocumentClass(mode)
  }, [mode])

  // Follow OS theme changes only until the user chooses a theme manually.
  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = (event: MediaQueryListEvent): void => {
      if (!explicit && !readStoredMode()) {
        setModeState(event.matches ? 'dark' : 'light')
      }
    }
    media.addEventListener('change', onChange)
    return () => media.removeEventListener('change', onChange)
  }, [explicit])

  const setTheme = useCallback((next: ThemeMode) => {
    setModeState(next)
    setExplicit(true)
    try {
      localStorage.setItem(THEME_STORAGE_KEY, next)
    } catch {
      // Persistence is best-effort; the in-memory theme still applies.
    }
  }, [])

  const toggleTheme = useCallback(() => {
    setTheme(mode === 'dark' ? 'light' : 'dark')
  }, [mode, setTheme])

  return <ThemeModeContext.Provider value={{ mode, toggleTheme, setTheme }}>{children}</ThemeModeContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components -- hook co-located with provider
export function useThemeMode(): ThemeModeContextValue {
  const context = useContext(ThemeModeContext)
  if (!context) {
    throw new Error('useThemeMode must be used within ThemeModeProvider')
  }
  return context
}